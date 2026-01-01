<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Events\ToolExecuted;
use Platform\Core\Events\ToolFailed;
use Platform\Core\Services\ToolIdempotencyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Generischer Tool-Executor
 * 
 * Führt Tools aus, validiert Parameter und behandelt Fehler
 */
class ToolExecutor
{
    private ?ToolValidationService $validationService = null;
    private ?ToolRateLimitService $rateLimitService = null;
    private ?ToolIdempotencyService $idempotencyService = null;

    public function __construct(
        private ToolRegistry $registry,
        private ?ToolCacheService $cacheService = null,
        private ?ToolTimeoutService $timeoutService = null
    ) {
        // Lazy-Loading: Cache-Service nur wenn verfügbar
        if ($this->cacheService === null) {
            try {
                $this->cacheService = app(ToolCacheService::class);
            } catch (\Throwable $e) {
                // Service noch nicht verfügbar - wird später verwendet
                $this->cacheService = null;
            }
        }

        // Lazy-Loading: Timeout-Service nur wenn verfügbar
        if ($this->timeoutService === null) {
            try {
                $this->timeoutService = app(ToolTimeoutService::class);
            } catch (\Throwable $e) {
                // Service noch nicht verfügbar
                $this->timeoutService = null;
            }
        }

        // Lazy-Loading: Validation-Service
        try {
            $this->validationService = app(ToolValidationService::class);
        } catch (\Throwable $e) {
            // Service noch nicht verfügbar - nutze einfache Validierung
            $this->validationService = null;
        }

        // Lazy-Loading: Rate-Limit-Service
        try {
            $this->rateLimitService = app(ToolRateLimitService::class);
        } catch (\Throwable $e) {
            // Service noch nicht verfügbar
            $this->rateLimitService = null;
        }
        
        // Lazy-Loading: Idempotency-Service
        try {
            $this->idempotencyService = app(ToolIdempotencyService::class);
        } catch (\Throwable $e) {
            // Service noch nicht verfügbar
            $this->idempotencyService = null;
        }
    }

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
        $memoryStart = memory_get_usage();
        $retries = 0;
        $cacheHit = false;
        $idempotencyKey = null;
        
        // Tool aus Registry holen (muss vor Idempotency-Prüfung passieren)
        $tool = $this->registry->get($toolName);
        if (!$tool) {
            Log::warning("[ToolExecutor] Tool '{$toolName}' nicht gefunden", ['trace_id' => $traceId]);
            return ToolResult::error("Tool '{$toolName}' nicht gefunden", 'TOOL_NOT_FOUND', ['trace_id' => $traceId]);
        }
        
        // Generiere Idempotency-Key (wenn Service verfügbar)
        if ($this->idempotencyService) {
            $idempotencyKey = $this->idempotencyService->generateKey($toolName, $arguments, $context);
            
            // Prüfe auf Duplikat (nur bei idempotenten Tools)
            if ($tool instanceof \Platform\Core\Contracts\ToolMetadataContract) {
                $metadata = $tool->getMetadata();
                $isIdempotent = (bool)($metadata['idempotent'] ?? false);
                
                if ($isIdempotent) {
                    $duplicate = $this->idempotencyService->checkDuplicate($idempotencyKey);
                    if ($duplicate) {
                        // Duplikat gefunden - gebe vorheriges Ergebnis zurück
                        Log::info("[ToolExecutor] Duplikat erkannt, gebe vorheriges Ergebnis zurück", [
                            'tool' => $toolName,
                            'idempotency_key' => substr($idempotencyKey, 0, 16) . '...',
                            'original_execution_id' => $duplicate->id,
                        ]);
                        
                        $duration = microtime(true) - $start;
                        $memoryUsage = memory_get_usage() - $memoryStart;
                        
                        // Erstelle Result aus vorherigem Execution
                        $result = ToolResult::success(
                            $duplicate->result_data ?? [],
                            [
                                'message' => 'Duplikat erkannt - vorheriges Ergebnis zurückgegeben',
                                'original_execution_id' => $duplicate->id,
                                'idempotency_key' => $idempotencyKey,
                            ]
                        );
                        
                        // Event feuern
                        event(new ToolExecuted(
                            $toolName,
                            $arguments,
                            $context,
                            $result,
                            $duration,
                            $memoryUsage,
                            $traceId,
                            $retries,
                            false, // Nicht aus Cache, sondern Duplikat
                            $idempotencyKey
                        ));
                        
                        return $result;
                    }
                }
            }
        }

        // Rate Limiting prüfen (wenn aktiviert)
        if ($this->rateLimitService && $this->rateLimitService->isEnabled()) {
            $rateLimitCheck = $this->rateLimitService->check($toolName, $context);
            if (!$rateLimitCheck['allowed']) {
                Log::warning("[ToolExecutor] Rate Limit überschritten", [
                    'tool' => $toolName,
                    'limit_type' => $rateLimitCheck['limit_type'] ?? 'unknown',
                    'trace_id' => $traceId,
                ]);
                return ToolResult::error(
                    "Rate Limit überschritten. Bitte warte {$rateLimitCheck['reset_at']} Sekunden.",
                    'RATE_LIMITED',
                    [
                        'trace_id' => $traceId,
                        'reset_at' => $rateLimitCheck['reset_at'],
                        'limit_type' => $rateLimitCheck['limit_type'] ?? 'unknown',
                    ]
                );
            }
        }

        // Parameter validieren (mit erweitertem Service, falls verfügbar)
        if ($this->validationService) {
            $validationResult = $this->validationService->validate($tool, $arguments);
        } else {
            $validationResult = $this->validate($tool, $arguments);
        }
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

        // Prüfe Cache (nur für read-only Tools)
        if ($this->cacheService) {
            $cachedResult = $this->cacheService->get($toolName, $validationResult['data'], $context, $tool);
            if ($cachedResult !== null) {
                $duration = microtime(true) - $start;
                $memoryUsage = memory_get_usage() - $memoryStart;
                
                // Event feuern: Tool erfolgreich ausgeführt (aus Cache)
                $cacheHit = true;
                event(new ToolExecuted(
                    $toolName,
                    $validationResult['data'],
                    $context,
                    $cachedResult,
                    $duration,
                    $memoryUsage,
                    $traceId,
                    $retries,
                    $cacheHit,
                    $idempotencyKey
                ));
                
                Log::info("[ToolExecutor] Tool aus Cache geladen", [
                    'tool' => $toolName,
                    'duration_ms' => (int)($duration * 1000),
                    'trace_id' => $traceId
                ]);

                return $cachedResult;
            }
        }

        // Tool ausführen (mit Timeout, falls aktiviert)
        try {
            $timeoutSeconds = $this->timeoutService?->getTimeoutForTool($toolName) ?? 30;
            
            if ($this->timeoutService && $this->timeoutService->isEnabled()) {
                $result = $this->timeoutService->executeWithTimeout(
                    fn() => $tool->execute($validationResult['data'], $context),
                    $timeoutSeconds,
                    $toolName
                );
            } else {
                $result = $tool->execute($validationResult['data'], $context);
            }
            
            $duration = microtime(true) - $start;
            $memoryUsage = memory_get_usage() - $memoryStart;
            
            // Cache Result (nur für read-only Tools)
            if ($this->cacheService && $result->success) {
                $this->cacheService->put($toolName, $validationResult['data'], $context, $tool, $result);
            }
            
            // Speichere Idempotency-Key (wenn Service verfügbar und erfolgreich)
            if ($this->idempotencyService && $idempotencyKey && $result->success) {
                // Wird später in Listener gespeichert (mit execution_id)
                $result->metadata['idempotency_key'] = $idempotencyKey;
            }
            
            // Event feuern: Tool erfolgreich ausgeführt
            event(new ToolExecuted(
                $toolName,
                $validationResult['data'],
                $context,
                $result,
                $duration,
                $memoryUsage,
                $traceId,
                $retries,
                $cacheHit,
                $idempotencyKey
            ));
            
            Log::info("[ToolExecutor] Tool ausgeführt", [
                'tool' => $toolName,
                'success' => $result->success,
                'duration_ms' => (int)($duration * 1000),
                'trace_id' => $traceId,
                'idempotency_key' => $idempotencyKey ? substr($idempotencyKey, 0, 16) . '...' : null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $memoryUsage = memory_get_usage() - $memoryStart;
            
            // Kategorisiere Fehler-Typ
            $errorType = $this->categorizeError($e, $validationResult['errors'] ?? []);
            $willRetry = $this->shouldRetry($e, $tool);
            
            // Event feuern: Tool fehlgeschlagen
            event(new ToolFailed(
                $toolName,
                $validationResult['data'],
                $context,
                $e->getMessage(),
                'EXECUTION_ERROR',
                $e,
                $duration,
                $memoryUsage,
                $traceId,
                $retries,
                $willRetry,
                $errorType
            ));
            
            Log::error("[ToolExecutor] Tool-Fehler", [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => (int)($duration * 1000),
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
     * Kategorisiert einen Fehler-Typ basierend auf Exception und Validierungsfehlern
     */
    private function categorizeError(\Throwable $e, array $validationErrors): string
    {
        if (!empty($validationErrors)) {
            return 'validation';
        }
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return 'authorization';
        }
        if ($e instanceof \Illuminate\Http\Client\ConnectionException || str_contains($e->getMessage(), 'timeout')) {
            return 'timeout';
        }
        if (str_contains($e->getMessage(), 'Rate Limit') || str_contains($e->getMessage(), 'rate limit')) {
            return 'rate_limit';
        }
        // Weitere Kategorien nach Bedarf
        return 'execution';
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

