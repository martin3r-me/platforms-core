<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Services\ToolNameMapper;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolRegistry;

/**
 * Minimaler, CLI-tauglicher Agent-Runner mit Tool-Loop (Responses API best practice).
 *
 * - ruft OpenAI Responses API per stream an (damit wir response_id + tool calls zuverlässig bekommen)
 * - sammelt function_call(s) aus dem Stream
 * - führt Tools aus
 * - setzt tool outputs als next input und chain't per previous_response_id
 * - wiederholt bis keine Tool-Calls mehr kommen oder max iterations erreicht ist
 */
class AiToolLoopRunner
{
    public function __construct(
        private readonly OpenAiService $openAi,
        private readonly ToolExecutor $executor,
        private readonly ToolNameMapper $nameMapper,
    ) {}

    public static function make(): self
    {
        // Ensure registry is resolved (module providers register tools in boot()).
        $registry = app(ToolRegistry::class);
        if (count($registry->all()) === 0) {
            // Some console contexts end up with an empty singleton – re-resolve once.
            app()->forgetInstance(ToolRegistry::class);
            $registry = app(ToolRegistry::class);
        }

        return new self(
            app(OpenAiService::class),
            app(ToolExecutor::class),
            app(ToolNameMapper::class),
        );
    }

    /**
     * @param array<int, array<string,mixed>> $messages Chat Messages (role/content) for first iteration.
     * @return array{assistant:string, iterations:int, previous_response_id:?string, next_input:?array, last_tool_calls:array<int,array<string,mixed>>}
     */
    public function run(array $messages, string $model, ToolContext $context, array $options = []): array
    {
        $maxIterations = (int)($options['max_iterations'] ?? 50);
        if ($maxIterations < 1) { $maxIterations = 1; }
        if ($maxIterations > 200) { $maxIterations = 200; }

        $maxOutputTokens = (int)($options['max_output_tokens'] ?? ($options['max_tokens'] ?? 2000));
        if ($maxOutputTokens < 64) { $maxOutputTokens = 64; }
        if ($maxOutputTokens > 200000) { $maxOutputTokens = 200000; }

        $includeWebSearch = (bool)($options['include_web_search'] ?? true);
        $reasoning = $options['reasoning'] ?? ['effort' => 'medium'];

        // Important: start from discovery-only tool set, then allow tools.GET to expand.
        $this->openAi->resetDynamicallyLoadedTools();

        // Autonomous agents (AutoPilot) preload all needed tools and should not
        // expose discovery tools (core.teams.GET etc.) that can confuse the LLM.
        $skipDiscovery = !empty($options['skip_discovery_tools']);
        if ($skipDiscovery) {
            $this->openAi->setSkipDiscoveryTools(true);
        }

        try {
        $preloadTools = $options['preload_tools'] ?? [];
        if (!empty($preloadTools) && is_array($preloadTools)) {
            $this->openAi->loadToolsDynamically($preloadTools);
        }

        $previousResponseId = null;
        $messagesForApi = $messages;
        $assistantFull = '';
        $lastToolCalls = [];
        $allToolCallNames = [];

        // Auto continuation for max_output_tokens (no tool calls) – keep small to avoid runaway.
        $maxOutputContinuations = (int)($options['max_output_continuations'] ?? 8);
        if ($maxOutputContinuations < 0) { $maxOutputContinuations = 0; }
        if ($maxOutputContinuations > 20) { $maxOutputContinuations = 20; }

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $assistant = '';
            $toolCallsCollector = [];
            $currentResponseId = null;
            $incompleteReason = null;

            // Map item_id -> call_id for robust argument assembly.
            $toolCallIdByItemId = [];

            $this->openAi->streamChat(
                $messagesForApi,
                function (string $delta) use (&$assistant) {
                    $assistant .= $delta;
                },
                $model,
                [
                    'with_context' => false,
                    'include_web_search' => $includeWebSearch,
                    'max_tokens' => $maxOutputTokens,
                    'previous_response_id' => $previousResponseId,
                    'reasoning' => is_array($reasoning) ? $reasoning : ['effort' => 'medium'],
                    // Prevent hanging runs (caller can override with should_abort).
                    'should_abort' => $options['should_abort'] ?? null,
                    'on_debug' => function (?string $event, array $decoded) use (
                        &$toolCallsCollector,
                        &$toolCallIdByItemId,
                        &$currentResponseId,
                        &$incompleteReason
                    ) {
                        $event = $event ?? '';

                        if ($event === 'response.created') {
                            $rid = $decoded['response']['id'] ?? null;
                            if (is_string($rid) && $rid !== '') { $currentResponseId = $rid; }
                        }

                        if ($event === 'response.incomplete') {
                            $reason = $decoded['response']['incomplete_details']['reason'] ?? null;
                            if (is_string($reason) && $reason !== '') { $incompleteReason = $reason; }
                        }

                        // Collect function calls (Responses API streaming)
                        if ($event === 'response.output_item.added' && ($decoded['item']['type'] ?? null) === 'function_call') {
                            $openAiToolName = $decoded['item']['name'] ?? null;
                            $itemId = $decoded['item']['id'] ?? null;
                            $callId = $decoded['item']['call_id'] ?? null;

                            if (is_string($itemId) && $itemId !== '' && is_string($callId) && $callId !== '') {
                                $toolCallIdByItemId[$itemId] = $callId;
                            }

                            if (is_string($openAiToolName) && $openAiToolName !== '' && is_string($callId) && $callId !== '') {
                                $toolCallsCollector[$callId] = [
                                    'id' => is_string($itemId) ? $itemId : null,
                                    'call_id' => $callId,
                                    'name' => $openAiToolName,
                                    'arguments' => '',
                                ];
                            }
                        } elseif ($event === 'response.function_call_arguments.delta') {
                            $delta = $decoded['delta'] ?? '';
                            $itemId = $decoded['item_id'] ?? null;
                            $callId = (is_string($itemId) && isset($toolCallIdByItemId[$itemId])) ? $toolCallIdByItemId[$itemId] : null;

                            if (is_string($callId) && $callId !== '' && is_string($delta) && isset($toolCallsCollector[$callId])) {
                                $toolCallsCollector[$callId]['arguments'] .= $delta;
                            }
                        } elseif ($event === 'response.function_call_arguments.done') {
                            $args = $decoded['arguments'] ?? '';
                            $itemId = $decoded['item_id'] ?? null;
                            $callId = (is_string($itemId) && isset($toolCallIdByItemId[$itemId])) ? $toolCallIdByItemId[$itemId] : null;

                            if (is_string($callId) && $callId !== '' && is_string($args) && isset($toolCallsCollector[$callId])) {
                                $toolCallsCollector[$callId]['arguments'] = $args;
                            }
                        }
                    },
                ]
            );

            $assistantFull .= $assistant;
            $lastToolCalls = array_values($toolCallsCollector);
            foreach ($lastToolCalls as $call) {
                $rawName = $call['name'] ?? '?';
                $allToolCallNames[] = $this->nameMapper->toCanonical($rawName);
            }

            // on_iteration callback
            $onIteration = $options['on_iteration'] ?? null;
            if (is_callable($onIteration)) {
                $iterToolNames = array_map(fn($c) => $this->nameMapper->toCanonical($c['name'] ?? '?'), $lastToolCalls);
                $onIteration($iteration, $iterToolNames, strlen($assistant));
            }

            // Auto-continue truncated output (only when there are no tool calls).
            $continuationPass = 0;
            while (empty($toolCallsCollector) && $incompleteReason === 'max_output_tokens' && is_string($currentResponseId) && $currentResponseId !== '') {
                $continuationPass++;
                if ($continuationPass > $maxOutputContinuations) { break; }

                $assistantMore = '';
                $toolCallsCollector = [];
                $toolCallIdByItemId = [];
                $previousResponseId = $currentResponseId;
                $messagesForApi = [];
                $currentResponseId = null;
                $incompleteReason = null;

                $this->openAi->streamChat(
                    $messagesForApi,
                    function (string $delta) use (&$assistantMore) {
                        $assistantMore .= $delta;
                    },
                    $model,
                    [
                        'with_context' => false,
                        'include_web_search' => $includeWebSearch,
                        'max_tokens' => $maxOutputTokens,
                        'previous_response_id' => $previousResponseId,
                        'reasoning' => is_array($reasoning) ? $reasoning : ['effort' => 'medium'],
                        'should_abort' => $options['should_abort'] ?? null,
                        'on_debug' => function (?string $event, array $decoded) use (
                            &$toolCallsCollector,
                            &$toolCallIdByItemId,
                            &$currentResponseId,
                            &$incompleteReason
                        ) {
                            $event = $event ?? '';
                            if ($event === 'response.created') {
                                $rid = $decoded['response']['id'] ?? null;
                                if (is_string($rid) && $rid !== '') { $currentResponseId = $rid; }
                            }
                            if ($event === 'response.incomplete') {
                                $reason = $decoded['response']['incomplete_details']['reason'] ?? null;
                                if (is_string($reason) && $reason !== '') { $incompleteReason = $reason; }
                            }
                            if ($event === 'response.output_item.added' && ($decoded['item']['type'] ?? null) === 'function_call') {
                                $openAiToolName = $decoded['item']['name'] ?? null;
                                $itemId = $decoded['item']['id'] ?? null;
                                $callId = $decoded['item']['call_id'] ?? null;
                                if (is_string($itemId) && $itemId !== '' && is_string($callId) && $callId !== '') {
                                    $toolCallIdByItemId[$itemId] = $callId;
                                }
                                if (is_string($openAiToolName) && $openAiToolName !== '' && is_string($callId) && $callId !== '') {
                                    $toolCallsCollector[$callId] = [
                                        'id' => is_string($itemId) ? $itemId : null,
                                        'call_id' => $callId,
                                        'name' => $openAiToolName,
                                        'arguments' => '',
                                    ];
                                }
                            } elseif ($event === 'response.function_call_arguments.delta') {
                                $delta = $decoded['delta'] ?? '';
                                $itemId = $decoded['item_id'] ?? null;
                                $callId = (is_string($itemId) && isset($toolCallIdByItemId[$itemId])) ? $toolCallIdByItemId[$itemId] : null;
                                if (is_string($callId) && $callId !== '' && is_string($delta) && isset($toolCallsCollector[$callId])) {
                                    $toolCallsCollector[$callId]['arguments'] .= $delta;
                                }
                            } elseif ($event === 'response.function_call_arguments.done') {
                                $args = $decoded['arguments'] ?? '';
                                $itemId = $decoded['item_id'] ?? null;
                                $callId = (is_string($itemId) && isset($toolCallIdByItemId[$itemId])) ? $toolCallIdByItemId[$itemId] : null;
                                if (is_string($callId) && $callId !== '' && is_string($args) && isset($toolCallsCollector[$callId])) {
                                    $toolCallsCollector[$callId]['arguments'] = $args;
                                }
                            }
                        },
                    ]
                );

                $assistantFull .= $assistantMore;
                $lastToolCalls = array_values($toolCallsCollector);
                foreach ($lastToolCalls as $call) {
                    $rawName = $call['name'] ?? '?';
                    $allToolCallNames[] = $this->nameMapper->toCanonical($rawName);
                }

                if (!empty($toolCallsCollector)) {
                    // tool calls exist → break auto-continuation and continue with tool execution below.
                    break;
                }
            }

            // No tool calls: final answer complete.
            if (empty($toolCallsCollector)) {
                return [
                    'assistant' => trim($assistantFull),
                    'iterations' => $iteration,
                    'previous_response_id' => null,
                    'next_input' => null,
                    'last_tool_calls' => $lastToolCalls,
                    'all_tool_call_names' => $allToolCallNames,
                ];
            }

            // Execute tool calls and prepare next input (function_call_output).
            $toolOutputsForNextIteration = [];
            foreach ($toolCallsCollector as $callId => $call) {
                $openAiToolName = (string)($call['name'] ?? '');
                $canonical = $this->nameMapper->toCanonical($openAiToolName);
                $argsJson = (string)($call['arguments'] ?? '');
                $args = json_decode($argsJson, true);
                if (!is_array($args)) { $args = []; }

                $result = null;
                try {
                    $result = $this->executor->execute($canonical, $args, $context);
                    $toolArray = $result->toArray();
                } catch (\Throwable $e) {
                    $toolArray = [
                        'ok' => false,
                        'error' => ['code' => 'EXECUTION_ERROR', 'message' => $e->getMessage()],
                    ];
                }

                // Dynamic tool loading: after tools.GET, allow the model to "expand" its tool set.
                try {
                    if ($canonical === 'tools.GET') {
                        $module = $args['module'] ?? null;
                        $search = $args['search'] ?? null;
                        $hasExplicitRequest = !empty($module) || !empty($search);
                        if ($hasExplicitRequest) {
                            $requestedTools = [];
                            $toolsData = $toolArray['data']['tools'] ?? $toolArray['tools'] ?? [];
                            if (is_array($toolsData)) {
                                foreach ($toolsData as $t) {
                                    $n = $t['name'] ?? null;
                                    if (is_string($n) && $n !== '') { $requestedTools[] = $n; }
                                }
                            }
                            $requestedTools = array_values(array_unique($requestedTools));
                            if (!empty($requestedTools)) {
                                $this->openAi->loadToolsDynamically($requestedTools);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore tool loading errors
                }

                $toolOutputsForNextIteration[] = [
                    'type' => 'function_call_output',
                    'call_id' => $callId,
                    'output' => json_encode($toolArray, JSON_UNESCAPED_UNICODE),
                ];
            }

            // Continue chaining: tool outputs only, plus previous_response_id.
            $previousResponseId = $currentResponseId;
            $messagesForApi = $toolOutputsForNextIteration;

            if (!is_string($previousResponseId) || $previousResponseId === '') {
                // Safety: without response id we cannot chain; abort gracefully.
                Log::warning('[AiToolLoopRunner] Missing response id for chaining; aborting tool loop', [
                    'iteration' => $iteration,
                    'tool_calls_count' => count($toolCallsCollector),
                ]);
                return [
                    'assistant' => trim($assistantFull),
                    'iterations' => $iteration,
                    'previous_response_id' => null,
                    'next_input' => null,
                    'last_tool_calls' => $lastToolCalls,
                    'all_tool_call_names' => $allToolCallNames,
                ];
            }
        }

        // Max iterations reached: allow caller to continue later.
        return [
            'assistant' => trim($assistantFull),
            'iterations' => $maxIterations,
            'previous_response_id' => $previousResponseId,
            'next_input' => $messagesForApi,
            'last_tool_calls' => $lastToolCalls,
            'all_tool_call_names' => $allToolCallNames,
        ];

        } finally {
            // Always reset the flag so subsequent non-autonomous calls get discovery tools again.
            if ($skipDiscovery) {
                $this->openAi->setSkipDiscoveryTools(false);
            }
        }
    }
}

