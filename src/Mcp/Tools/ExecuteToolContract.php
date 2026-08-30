<?php

namespace Platform\Core\Mcp\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Events\ToolExecuted;
use Platform\Core\Events\ToolFailed;
use Platform\Core\Tools\ToolRegistry;
use Platform\Core\Tools\ToolMetadataResolver;
use Platform\Core\Services\ToolPermissionService;
use Illuminate\Support\Facades\Log;

/**
 * Universelles Execute Tool
 *
 * Ermöglicht das Ausführen beliebiger Tools über ein einziges MCP-Tool.
 * Löst das Problem, dass Claude.ai keine dynamisch nachgeladenen Tools nutzen kann.
 *
 * Beispiel: execute(tool="planner.projects.GET", arguments={"limit": 10})
 */
class ExecuteToolContract implements ToolContract
{
    public function getName(): string
    {
        return 'execute';
    }

    public function getDescription(): string
    {
        return 'Führt ein beliebiges Tool aus. Nutze tools__GET(module="...") um verfügbare Tools zu sehen, ' .
            'dann execute(tool="toolname", arguments={...}) um es auszuführen. ' .
            'Beispiel: execute(tool="planner.projects.GET", arguments={"limit": 10})';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tool' => [
                    'type' => 'string',
                    'description' => 'Name des Tools (mit Punkten). Beispiel: "planner.projects.GET", "helpdesk.tickets.POST"',
                ],
                'arguments' => [
                    'type' => 'object',
                    'description' => 'Argumente für das Tool als JSON-Objekt. Siehe Tool-Schema via tools__GET.',
                ],
            ],
            'required' => ['tool'],
            'additionalProperties' => false,
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $unknownKeys = array_diff(array_keys($arguments), ['tool', 'arguments']);
            if (!empty($unknownKeys)) {
                $hint = in_array('params', $unknownKeys, true)
                    ? ' Meintest du "arguments" statt "params"?'
                    : '';
                return ToolResult::failure(
                    'Unbekannte(r) Payload-Key(s): "' . implode('", "', $unknownKeys) . '". ' .
                    'Erlaubt sind nur "tool" und "arguments".' . $hint,
                    'INVALID_PAYLOAD'
                );
            }

            $toolName = $arguments['tool'] ?? null;
            $toolArguments = $arguments['arguments'] ?? [];

            // Handle case where arguments arrives as JSON string instead of object
            if (is_string($toolArguments)) {
                $decoded = json_decode($toolArguments, true);
                $toolArguments = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? $decoded
                    : [];
            }

            if (!is_string($toolName) || trim($toolName) === '') {
                return ToolResult::failure(
                    'Parameter "tool" ist erforderlich. Beispiel: execute(tool="planner.projects.GET")',
                    'MISSING_PARAMETER'
                );
            }

            $toolName = trim($toolName);

            // Tool in Registry finden
            $registry = app(ToolRegistry::class);

            // Stelle sicher, dass Tools geladen sind
            if (count($registry->all()) === 0) {
                $this->loadToolsIntoRegistry($registry);
            }

            // Tool finden (mit oder ohne Punkte)
            if (!$registry->has($toolName)) {
                // Versuche mit Underscores statt Punkte
                $altName = str_replace('__', '.', $toolName);
                if ($registry->has($altName)) {
                    $toolName = $altName;
                } else {
                    return ToolResult::failure(
                        "Tool '{$toolName}' nicht gefunden. Nutze tools__GET(module=\"...\") um verfügbare Tools zu sehen.",
                        'TOOL_NOT_FOUND'
                    );
                }
            }

            $tool = $registry->get($toolName);

            // Berechtigungsprüfung: Hat der User Zugriff auf das Modul?
            try {
                $permissionService = app(ToolPermissionService::class);
                if (!$permissionService->hasAccess($toolName)) {
                    return ToolResult::failure(
                        "Kein Zugriff auf Tool '{$toolName}'. Das Modul ist für dein Team nicht freigeschaltet.",
                        'ACCESS_DENIED'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('[MCP Execute] Permission-Check fehlgeschlagen, erlaube Zugriff', [
                    'tool' => $toolName,
                    'error' => $e->getMessage(),
                ]);
            }

            // READ-ONLY-SCOPE: ein Token OHNE Schreibrecht darf nur read_only-Tools ausführen (Lese-
            // Ausweis, z.B. für den persönlichen Assistenten: sehen mit dem read-Token des Principals,
            // handeln mit dem eigenen). Bestehende Voll-Token (scope "*") + Session/TransientToken sind
            // nicht betroffen (tokenCan('*') → true). Nur echte read-scoped Token werden eingeschränkt.
            // Sicherheits-Prüfung → fail-CLOSED: kann read_only nicht ermittelt werden, wird gesperrt.
            $user = $context->user;
            if (method_exists($user, 'tokenCan') && !$user->tokenCan('*') && !$user->tokenCan('write')) {
                $isReadOnly = false;
                try {
                    $isReadOnly = !empty((new ToolMetadataResolver())->resolve($tool)['read_only']);
                } catch (\Throwable $e) {
                    Log::warning('[MCP Execute] read_only nicht ermittelbar → sperre für read-only-Token', [
                        'tool' => $toolName,
                        'error' => $e->getMessage(),
                    ]);
                }
                if (!$isReadOnly) {
                    return ToolResult::failure(
                        "Token ist read-only: '{$toolName}' ist ein schreibendes Tool und erfordert den 'write'-Scope.",
                        'SCOPE_DENIED'
                    );
                }
            }

            // Tool ausführen
            Log::info('[MCP Execute] Tool wird ausgeführt', [
                'tool' => $toolName,
                'arguments' => array_keys($toolArguments),
            ]);

            $start = microtime(true);
            $memStart = memory_get_usage();
            $traceId = bin2hex(random_bytes(8));

            $result = $tool->execute($toolArguments, $context);

            $duration = microtime(true) - $start;
            $memUsage = memory_get_usage() - $memStart;

            try {
                $trackedResult = new ToolResult(
                    success: $result->success,
                    data: $result->data,
                    error: $result->error,
                    errorCode: $result->errorCode,
                    metadata: array_merge($result->metadata, [
                        'source' => 'mcp_execute',
                        'token_estimate_input' => (int) (mb_strlen(json_encode($toolArguments)) / 4),
                        'token_estimate_output' => (int) (mb_strlen(json_encode($result->data ?? [])) / 4),
                    ]),
                );

                event(new ToolExecuted(
                    toolName: $toolName,
                    arguments: $toolArguments,
                    context: $context,
                    result: $trackedResult,
                    duration: $duration,
                    memoryUsage: $memUsage,
                    traceId: $traceId,
                ));
            } catch (\Throwable $e) {
                Log::warning('[MCP Execute] Event-Tracking fehlgeschlagen', ['error' => $e->getMessage()]);
            }

            return $result;

        } catch (\Throwable $e) {
            $duration = isset($start) ? microtime(true) - $start : 0;
            $memUsage = isset($memStart) ? memory_get_usage() - $memStart : 0;

            try {
                event(new ToolFailed(
                    toolName: $toolName ?? 'unknown',
                    arguments: $toolArguments ?? [],
                    context: $context,
                    errorMessage: $e->getMessage(),
                    errorCode: 'EXECUTION_ERROR',
                    exception: $e,
                    duration: $duration,
                    memoryUsage: $memUsage,
                    traceId: $traceId ?? null,
                ));
            } catch (\Throwable $eventError) {
                // Silent
            }

            Log::error('[MCP Execute] Fehler', [
                'tool' => $toolName ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return ToolResult::failure('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Lädt Tools in die Registry
     */
    private function loadToolsIntoRegistry(ToolRegistry $registry): void
    {
        try {
            // Core-Tools laden
            $coreTools = \Platform\Core\Tools\ToolLoader::loadCoreTools();
            foreach ($coreTools as $tool) {
                if (!$registry->has($tool->getName())) {
                    $registry->register($tool);
                }
            }

            // Module-Tools laden
            $modulesPath = realpath(__DIR__ . '/../../../../modules');
            if ($modulesPath && is_dir($modulesPath)) {
                $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
                foreach ($moduleTools as $tool) {
                    if (!$registry->has($tool->getName())) {
                        $registry->register($tool);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[MCP Execute] Tool-Loading fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
