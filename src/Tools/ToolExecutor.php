<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Generischer Tool-Executor
 * 
 * Führt Tools aus, validiert Parameter und behandelt Fehler
 */
class ToolExecutor
{
    public function __construct(
        private ToolRegistry $registry
    ) {}

    /**
     * Führt ein Tool aus
     * 
     * @param string $toolName Name des Tools
     * @param array $arguments Argumente für das Tool
     * @param ToolContext $context Kontext für die Ausführung
     * @return ToolResult Ergebnis der Ausführung
     */
    public function execute(string $toolName, array $arguments, ToolContext $context): ToolResult
    {
        $traceId = bin2hex(random_bytes(8));
        $start = microtime(true);

        // Tool aus Registry holen
        $tool = $this->registry->get($toolName);
        if (!$tool) {
            Log::warning("[ToolExecutor] Tool '{$toolName}' nicht gefunden", ['trace_id' => $traceId]);
            return ToolResult::error("Tool '{$toolName}' nicht gefunden", 'TOOL_NOT_FOUND', ['trace_id' => $traceId]);
        }

        // Parameter validieren
        $validationResult = $this->validate($tool, $arguments);
        if (!$validationResult['valid']) {
            Log::warning("[ToolExecutor] Validierung fehlgeschlagen", [
                'tool' => $toolName,
                'errors' => $validationResult['errors'],
                'trace_id' => $traceId
            ]);
            return ToolResult::error(
                'Validierung fehlgeschlagen: ' . implode(', ', $validationResult['errors']),
                'VALIDATION_ERROR',
                ['trace_id' => $traceId, 'errors' => $validationResult['errors']]
            );
        }

        // Tool ausführen
        try {
            $result = $tool->execute($validationResult['data'], $context);
            
            $duration = (int)((microtime(true) - $start) * 1000);
            Log::info("[ToolExecutor] Tool ausgeführt", [
                'tool' => $toolName,
                'success' => $result->success,
                'duration_ms' => $duration,
                'trace_id' => $traceId
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = (int)((microtime(true) - $start) * 1000);
            Log::error("[ToolExecutor] Tool-Fehler", [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $duration,
                'trace_id' => $traceId
            ]);

            return ToolResult::error(
                'Tool-Ausführung fehlgeschlagen: ' . $e->getMessage(),
                'EXECUTION_ERROR',
                ['trace_id' => $traceId]
            );
        }
    }

    /**
     * Validiert Tool-Parameter gegen JSON Schema
     * 
     * @return array{valid: bool, data: array, errors: array}
     */
    private function validate(ToolContract $tool, array $arguments): array
    {
        $schema = $tool->getSchema();
        
        // Einfache Validierung: Prüfe required fields
        $required = $schema['required'] ?? [];
        $properties = $schema['properties'] ?? [];
        
        $errors = [];
        
        // Prüfe required fields
        foreach ($required as $field) {
            if (!isset($arguments[$field])) {
                $errors[] = "Feld '{$field}' ist erforderlich";
            }
        }

        // Prüfe Typen (vereinfacht)
        foreach ($arguments as $key => $value) {
            if (!isset($properties[$key])) {
                continue; // Unbekannte Felder werden ignoriert (könnte später strikter sein)
            }

            $property = $properties[$key];
            $expectedType = $property['type'] ?? null;

            if ($expectedType && !$this->validateType($value, $expectedType)) {
                $errors[] = "Feld '{$key}' hat falschen Typ (erwartet: {$expectedType})";
            }
        }

        return [
            'valid' => empty($errors),
            'data' => $arguments,
            'errors' => $errors
        ];
    }

    /**
     * Validiert einen Wert gegen einen Typ
     */
    private function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            default => true // Unbekannte Typen werden akzeptiert
        };
    }
}

