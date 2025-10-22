<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Log;
use Platform\Core\Tools\DataRead\ProviderRegistry;

class CoreDataProxy
{
    public function __construct(
        private ProviderRegistry $registry,
        private DataReadTool $dataRead
    ) {}

    public function executeRead(string $entity, string $operation, array $options = [], array $context = []): array
    {
        $traceId = $context['trace_id'] ?? bin2hex(random_bytes(8));
        $start = microtime(true);

        $provider = $this->registry->get($entity);
        if (!$provider) {
            return $this->error('ENTITY_NOT_FOUND', "Unknown entity '{$entity}'", $traceId);
        }

        $allowedOps = ['describe','list','get','search'];
        if (!in_array($operation, $allowedOps, true)) {
            return $this->error('VALIDATION_ERROR', 'Invalid operation', $traceId);
        }

        // Validate and sanitize options
        $validationError = $this->validateOptions($provider, $operation, $options);
        if ($validationError) {
            return $this->error('VALIDATION_ERROR', $validationError, $traceId);
        }

        // Enforce team/user scope: remove any external team filters; provider applies scope
        if (!empty($options['filters'])) {
            $options['filters'] = array_values(array_filter($options['filters'], function ($f) {
                return ($f['field'] ?? '') !== 'team_id' && ($f['field'] ?? '') !== 'user_id';
            }));
        }

        // Execute
        $result = match ($operation) {
            'describe' => $this->dataRead->describe($entity),
            'list' => $this->dataRead->list($entity, $options),
            'get' => $this->dataRead->get($entity, (int)($options['id'] ?? 0)),
            'search' => $this->dataRead->search($entity, (string)($options['query'] ?? ''), $options),
            default => $this->error('VALIDATION_ERROR', 'Unsupported operation', $traceId)
        };

        // Redact PII fields if present in manifest
        if (($result['ok'] ?? false) === true) {
            $piiFields = array_keys(array_filter($provider->readableFields(), function ($f) { return false; }));
            // Above placeholder: ManifestEntityProvider exposes pii via fields meta; for now rely on DataReadTool redaction or none.
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        try {
            $count = $result['data']['meta']['total'] ?? (isset($result['data']['record']) ? 1 : 0);
            Log::info('[CoreDataProxy] read', [
                'trace_id' => $traceId,
                'entity' => $entity,
                'operation' => $operation,
                'result_count' => $count,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }

        // Attach _source if missing
        if (($result['ok'] ?? false) === true) {
            $module = strstr($entity, '.', true) ?: null;
            $model = $provider->model();
            $result['data']['_source'] = $result['data']['_source'] ?? [];
            $result['data']['_source'] = array_merge($result['data']['_source'], [
                'module' => $module,
                'entity' => $entity,
                'model' => $model,
                'updated_at' => now()->toISOString(),
            ]);
        }

        return $result;
    }

    private function validateOptions($provider, string $operation, array $options): ?string
    {
        // filters
        foreach ($options['filters'] ?? [] as $f) {
            $field = $f['field'] ?? null; $op = $f['op'] ?? null;
            if (!$field || !$op) { return 'Filter requires field and op'; }
            $allowed = $provider->allowedFilters();
            if (!isset($allowed[$field]) || !in_array($op, $allowed[$field], true)) {
                return "Filter not allowed: {$field} {$op}";
            }
        }
        // sort
        foreach ($options['sort'] ?? [] as $s) {
            $field = $s['field'] ?? null; $dir = strtolower($s['dir'] ?? 'asc');
            if (!$field) { return 'Sort requires field'; }
            if (!in_array($field, $provider->allowedSorts(), true)) { return 'Sort field not allowed'; }
            if (!in_array($dir, ['asc','desc'], true)) { return 'Sort dir invalid'; }
        }
        // id for get
        if ($operation === 'get' && empty($options['id'])) { return 'id is required for get'; }
        return null;
    }

    private function error(string $code, string $message, string $traceId): array
    {
        return [
            'ok' => false,
            'error' => [ 'code' => $code, 'message' => $message, 'trace_id' => $traceId ]
        ];
    }
}
