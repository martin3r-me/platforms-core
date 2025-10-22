<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Tools\DataRead\ProviderRegistry;

class CoreDataProxy
{
    private const MAX_PER_PAGE = 200;

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

        // Policy: viewAny for list/search/describe
        try {
            $modelClass = $provider->model();
            if (in_array($operation, ['list','search','describe'], true)) {
                Gate::authorize('viewAny', $modelClass);
            }
        } catch (\Throwable $e) {
            return $this->error('AUTHZ_DENIED', 'Not authorized to view this entity', $traceId);
        }

        // Sanitize and validate options
        $options = $this->sanitizeOptions($options, $provider);
        $validationError = $this->validateOptions($provider, $operation, $options);
        if ($validationError) {
            return $this->error('VALIDATION_ERROR', $validationError, $traceId);
        }

        // For get: fetch real model with team scope and authorize 'view' on it first
        if ($operation === 'get') {
            $id = (int)($options['id'] ?? 0);
            if ($id <= 0) {
                return $this->error('VALIDATION_ERROR', 'id is required for get', $traceId);
            }
            try {
                $row = $provider->teamScopedQuery()->where('id', $id)->first();
                if (!$row) {
                    return $this->error('ROW_NOT_FOUND', 'Record not found', $traceId);
                }
                Gate::authorize('view', $row);
            } catch (\Throwable $e) {
                return $this->error('AUTHZ_DENIED', 'Not authorized to view this row', $traceId);
            }
        }

        // Execute
        $result = match ($operation) {
            'describe' => $this->dataRead->describe($entity),
            'list' => $this->dataRead->list($entity, $options),
            'get' => $this->dataRead->get($entity, (int)($options['id'] ?? 0)),
            'search' => $this->dataRead->search($entity, (string)($options['query'] ?? ''), $options),
            default => $this->error('VALIDATION_ERROR', 'Unsupported operation', $traceId)
        };

        // Redact PII fields
        if (($result['ok'] ?? false) === true) {
            $piiFields = method_exists($provider, 'piiFields') ? $provider->piiFields() : [];
            if (!empty($piiFields)) {
                if (!empty($result['data']['records']) && is_array($result['data']['records'])) {
                    foreach ($result['data']['records'] as &$row) {
                        foreach ($piiFields as $f) { if (array_key_exists($f, $row)) { $row[$f] = $this->maskPii($row[$f]); } }
                    }
                    unset($row);
                }
                if (!empty($result['data']['record']) && is_array($result['data']['record'])) {
                    foreach ($piiFields as $f) { if (array_key_exists($f, $result['data']['record'])) { $result['data']['record'][$f] = $this->maskPii($result['data']['record'][$f]); } }
                }
            }
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
        } catch (\Throwable $e) {}

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

    private function sanitizeOptions(array $options, $provider): array
    {
        // Remove team/user filters; scope enforced server-side
        if (!empty($options['filters'])) {
            $options['filters'] = array_values(array_filter($options['filters'], function ($f) {
                $field = $f['field'] ?? '';
                return $field !== 'team_id' && $field !== 'user_id';
            }));
        }
        // per_page cap
        if (!empty($options['per_page'])) {
            $pp = (int) $options['per_page'];
            $options['per_page'] = max(1, min(self::MAX_PER_PAGE, $pp));
        }
        // fields/include sanitize to arrays
        if (!empty($options['fields']) && !is_array($options['fields'])) { $options['fields'] = []; }
        if (!empty($options['include']) && !is_array($options['include'])) { $options['include'] = []; }
        return $options;
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
        // fields
        if (!empty($options['fields'])) {
            $allowedFields = $provider->readableFields();
            foreach ($options['fields'] as $fld) {
                if (!in_array($fld, $allowedFields, true)) { return 'Field not allowed: '.$fld; }
            }
        }
        // include
        if (!empty($options['include'])) {
            $allowedRels = $provider->relationsWhitelist();
            foreach ($options['include'] as $rel) {
                if (!in_array($rel, $allowedRels, true)) { return 'Relation not allowed: '.$rel; }
            }
        }
        // id for get checked separately
        return null;
    }

    private function error(string $code, string $message, string $traceId): array
    {
        return [
            'ok' => false,
            'error' => [ 'code' => $code, 'message' => $message, 'trace_id' => $traceId ]
        ];
    }

    private function maskPii($value): string
    {
        if (is_string($value) && str_contains($value, '@')) {
            return preg_replace('/(.{2}).*(@.*)/', '$1***$2', $value);
        }
        return '***';
    }
}
