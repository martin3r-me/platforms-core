<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\ToolExecutor;
use Platform\Core\Tools\ToolRegistry;

/**
 * Claude Tool Loop Runner
 *
 * Standalone agentic loop using the Anthropic Messages API with native tool use.
 * Uses the MCP pattern: a small set of meta-tools (discover + execute) that give
 * access to the entire ToolRegistry, plus optional direct tool definitions.
 *
 * Claude gets ~5 tools:
 *   - execute_tool: execute any registered tool (discovery via tool_registry.SEARCH)
 *   - + direct action tools (e.g. create_signal, do_nothing)
 *
 * Security: ToolExecutor handles permissions via ToolPermissionService.
 */
class ClaudeToolLoopRunner
{
    protected string $baseUrl = 'https://api.anthropic.com/v1';
    protected string $apiVersion = '2023-06-01';
    protected string $defaultModel = 'claude-sonnet-4-6';
    protected int $defaultMaxTokens = 4096;
    protected int $maxIterations = 50;
    protected int $timeoutSeconds = 120;

    protected ToolRegistry $registry;
    protected ToolExecutor $executor;

    public function __construct(ToolRegistry $registry, ToolExecutor $executor)
    {
        $this->registry = $registry;
        $this->executor = $executor;
    }

    public static function make(): static
    {
        // Ensure registry is resolved (module providers register tools in boot()).
        $registry = app(ToolRegistry::class);
        if (count($registry->all()) === 0) {
            app()->forgetInstance(ToolRegistry::class);
        }

        return resolve(static::class);
    }

    /**
     * Run the agentic tool loop.
     *
     * @param  array  $messages  Messages in Anthropic format
     * @param  ToolContext  $context  Tool execution context
     * @param  array  $options  {
     *   model?: string,
     *   max_tokens?: int,
     *   max_iterations?: int,
     *   system?: string,
     *   tools?: string[],          // Direct tools exposed as individual Anthropic tool definitions
     *   include_meta_tools?: bool, // Include discover_tools + execute_tool (default: true)
     *   temperature?: float,
     *   thinking?: array,
     *   on_tool_call?: callable,
     *   on_tool_result?: callable,
     *   on_iteration?: callable,
     * }
     */
    public function run(array $messages, ToolContext $context, array $options = []): array
    {
        // Establish auth context for ToolPermissionService (uses Auth::user()).
        // In queue jobs there is no authenticated user, so we set it from ToolContext.
        $previousUser = Auth::user();
        $hadTeamOverride = TeamContext::hasOverride();

        if ($context->user) {
            Auth::setUser($context->user);
        }
        if ($context->team) {
            TeamContext::setTeam($context->team);
        }

        try {
            return $this->executeLoop($messages, $context, $options);
        } finally {
            // Restore previous auth state
            if ($previousUser) {
                Auth::setUser($previousUser);
            } else {
                Auth::guard()->forgetUser();
            }
            if (! $hadTeamOverride) {
                TeamContext::clear();
            }
        }
    }

    /**
     * Internal: execute the agentic tool loop.
     */
    protected function executeLoop(array $messages, ToolContext $context, array $options): array
    {
        $apiKey = $this->resolveApiKey();
        $model = $options['model'] ?? $this->defaultModel;
        $maxTokens = $options['max_tokens'] ?? $this->defaultMaxTokens;
        $maxIterations = $options['max_iterations'] ?? $this->maxIterations;
        $system = $options['system'] ?? null;
        $temperature = $options['temperature'] ?? null;
        $thinking = $options['thinking'] ?? null;
        $onToolCall = $options['on_tool_call'] ?? null;
        $onToolResult = $options['on_tool_result'] ?? null;
        $onIteration = $options['on_iteration'] ?? null;
        $includeMetaTools = $options['include_meta_tools'] ?? true;

        // Direct tools: exposed as individual Anthropic tool definitions
        $directToolNames = $options['tools'] ?? [];

        // Build tool definitions
        $toolDefinitions = $this->buildToolDefinitions($directToolNames, $includeMetaTools);

        $totalUsage = [
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_creation_input_tokens' => 0,
            'cache_read_input_tokens' => 0,
        ];
        $allToolCalls = [];
        $assistantText = '';
        $stopReason = '';
        $iteration = 0;

        while ($iteration < $maxIterations) {
            $iteration++;

            $payload = [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
            ];

            if ($system !== null) {
                $payload['system'] = $system;
            }

            if (! empty($toolDefinitions)) {
                $payload['tools'] = $toolDefinitions;
            }

            if ($temperature !== null) {
                $payload['temperature'] = $temperature;
            }

            if ($thinking !== null) {
                $payload['thinking'] = $thinking;
                if (isset($thinking['budget_tokens'])) {
                    $payload['max_tokens'] = max($maxTokens, $thinking['budget_tokens'] + 4096);
                }
            }

            $response = $this->callApi($apiKey, $payload);

            // Accumulate usage
            if (isset($response['usage'])) {
                foreach ($totalUsage as $key => &$value) {
                    $value += $response['usage'][$key] ?? 0;
                }
                unset($value);
            }

            $stopReason = $response['stop_reason'] ?? 'end_turn';
            $content = $response['content'] ?? [];

            if ($onIteration) {
                $onIteration($iteration, $response);
            }

            // Process response content blocks
            $toolUseBlocks = [];

            foreach ($content as $block) {
                if ($block['type'] === 'text') {
                    $assistantText .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolUseBlocks[] = $block;
                }
            }

            if (empty($toolUseBlocks)) {
                break;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $content,
            ];

            // Execute each tool call
            $toolResultBlocks = [];

            foreach ($toolUseBlocks as $toolUse) {
                $rawToolName = $toolUse['name'];
                $toolArgs = $toolUse['input'] ?? [];
                $toolUseId = $toolUse['id'];
                $targetArgs = $toolArgs;

                // Handle meta-tool
                if ($rawToolName === 'execute_tool') {
                    $canonicalName = $toolArgs['tool'] ?? '';
                    $targetArgs = $toolArgs['arguments'] ?? [];
                    if (is_string($targetArgs)) {
                        $targetArgs = json_decode($targetArgs, true) ?? [];
                    }

                    // ToolExecutor handles permissions — no allowlist needed
                    $result = $this->executeTool($canonicalName, $targetArgs, $context);
                } else {
                    // Direct tool call
                    $canonicalName = $this->resolveToolName($rawToolName);
                    $result = $this->executeTool($canonicalName, $toolArgs, $context);
                }

                if ($onToolCall) {
                    $onToolCall($canonicalName, $targetArgs);
                }

                Log::debug('[ClaudeToolLoop] Tool executed', [
                    'iteration' => $iteration,
                    'tool' => $canonicalName,
                    'ok' => $result['ok'] ?? false,
                    'error' => ($result['ok'] ?? false) ? null : ($result['error'] ?? null),
                ]);

                $allToolCalls[] = [
                    'tool' => $canonicalName,
                    'args' => $targetArgs,
                    'result' => $result,
                    'iteration' => $iteration,
                ];

                if ($onToolResult) {
                    $onToolResult($canonicalName, $targetArgs, $result);
                }

                $toolResultBlocks[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUseId,
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }

            $messages[] = [
                'role' => 'user',
                'content' => $toolResultBlocks,
            ];
        }

        if ($iteration >= $maxIterations) {
            Log::warning('[ClaudeToolLoop] Max iterations reached', [
                'max_iterations' => $maxIterations,
                'tool_calls' => count($allToolCalls),
            ]);
        }

        return [
            'assistant_text' => $assistantText,
            'iterations' => $iteration,
            'tool_calls' => $allToolCalls,
            'token_usage' => $totalUsage,
            'model' => $model,
            'stop_reason' => $stopReason,
        ];
    }

    /**
     * Build tool definitions: direct tools + execute_tool meta-tool.
     */
    protected function buildToolDefinitions(array $directToolNames, bool $includeMetaTools): array
    {
        $definitions = [];

        // 1. Direct tools (individual definitions, called by their own name)
        foreach ($directToolNames as $name) {
            $tool = $this->registry->get($name);
            if (! $tool) {
                continue;
            }

            $definitions[] = [
                'name' => $this->normalizeToolName($name),
                'description' => $tool->getDescription(),
                'input_schema' => $tool->getSchema(),
            ];
        }

        // 2. execute_tool: runs any registered tool (discovery via tool_registry.SEARCH)
        if ($includeMetaTools) {
            $definitions[] = [
                'name' => 'execute_tool',
                'description' => 'Führt ein beliebiges registriertes Tool aus. Nutze execute_tool(tool="tool_registry.SEARCH", arguments={"query": "..."}) um verfügbare Tools zu finden.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'tool' => [
                            'type' => 'string',
                            'description' => 'Tool-Name mit Punkten (z.B. "organization.entities.GET", "tool_registry.SEARCH").',
                        ],
                        'arguments' => [
                            'type' => 'object',
                            'description' => 'Argumente für das Tool als JSON-Objekt.',
                        ],
                    ],
                    'required' => ['tool'],
                ],
            ];
        }

        return $definitions;
    }

    /**
     * Normalize tool name for Anthropic API (dots -> underscores, max 64 chars).
     */
    protected function normalizeToolName(string $name): string
    {
        return substr(str_replace('.', '_', $name), 0, 64);
    }

    /**
     * Resolve normalized tool name back to canonical name.
     */
    protected function resolveToolName(string $normalizedName): string
    {
        $canonical = str_replace('_', '.', $normalizedName);

        if ($this->registry->has($canonical)) {
            return $canonical;
        }

        foreach ($this->registry->names() as $registeredName) {
            if ($this->normalizeToolName($registeredName) === $normalizedName) {
                return $registeredName;
            }
        }

        return $canonical;
    }

    /**
     * Execute a tool via ToolExecutor (handles permissions, validation, rate limiting).
     */
    protected function executeTool(string $toolName, array $arguments, ToolContext $context): array
    {
        try {
            $result = $this->executor->execute($toolName, $arguments, $context);
            return $result->toArray();
        } catch (\Throwable $e) {
            Log::error('[ClaudeToolLoop] Tool execution failed', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => [
                    'code' => 'EXECUTION_ERROR',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Call the Anthropic Messages API with retry on rate limit (429).
     */
    protected function callApi(string $apiKey, array $payload): array
    {
        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $this->apiVersion,
                    'content-type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/messages", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            $statusCode = $response->status();
            $body = $response->json();
            $errorMessage = $body['error']['message'] ?? $response->body();
            $errorType = $body['error']['type'] ?? 'api_error';

            // Retry on rate limit (429) or overloaded (529) with exponential backoff
            if (in_array($statusCode, [429, 529]) && $attempt < $maxRetries) {
                $retryAfter = (int) ($response->header('retry-after') ?: 0);
                $waitSeconds = max($retryAfter, $attempt * 30);

                Log::warning('[ClaudeToolLoop] Rate limited, retrying', [
                    'status' => $statusCode,
                    'attempt' => $attempt,
                    'wait_seconds' => $waitSeconds,
                ]);

                sleep($waitSeconds);

                continue;
            }

            Log::error('[ClaudeToolLoop] API call failed', [
                'status' => $statusCode,
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'attempt' => $attempt,
            ]);

            throw new \RuntimeException(
                "Anthropic API error ({$statusCode}): [{$errorType}] {$errorMessage}"
            );
        }

        throw new \RuntimeException('Anthropic API: max retries exhausted');
    }

    /**
     * Resolve the Anthropic API key from config.
     */
    protected function resolveApiKey(): string
    {
        $key = config('ai.anthropic.api_key', '');

        if ($key === '' || $key === null) {
            $key = env('ANTHROPIC_API_KEY', '');
        }

        if (empty($key)) {
            throw new \RuntimeException(
                'ANTHROPIC_API_KEY is not configured. Set it in .env or config/services.php.'
            );
        }

        return $key;
    }
}
