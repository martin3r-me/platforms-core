<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Platform\Core\Tools\DataRead\ProviderRegistry;

class CoreWriteProxy
{
    public function __construct(private ProviderRegistry $registry) {}

    public function executeCommand(string $entity, string $operation, array $input = [], array $context = []): array
    {
        $traceId = $context['trace_id'] ?? bin2hex(random_bytes(8));
        $provider = $this->registry->get($entity);
        if (!$provider) {
            return $this->error('ENTITY_NOT_FOUND', "Unknown entity '{$entity}'", $traceId);
        }

        $modelClass = $provider->model();
        $data = (array)($input['data'] ?? []);
        $id = isset($input['id']) ? (int)$input['id'] : null;

        try {
            return match ($operation) {
                'create' => $this->create($provider, $modelClass, $data, $traceId),
                'update' => $this->update($provider, $modelClass, $id, $data, $traceId),
                'delete' => $this->delete($provider, $modelClass, $id, $traceId),
                default => $this->error('VALIDATION_ERROR', 'Unsupported write operation', $traceId)
            };
        } catch (\Throwable $e) {
            Log::error('[CoreWriteProxy] error', ['trace_id' => $traceId, 'message' => $e->getMessage()]);
            return $this->error('INTERNAL_ERROR', 'Write failed', $traceId);
        }
    }

    private function create($provider, string $modelClass, array $data, string $traceId): array
    {
        Gate::authorize('create', $modelClass);
        $fillable = method_exists($provider, 'fillableFields') ? $provider->fillableFields() : [];
        $readonly = method_exists($provider, 'readonlyFields') ? $provider->readonlyFields() : [];

        $payload = $this->filterPayload($data, $fillable, $readonly);
        // Scope inject
        $payload = $this->injectScope($provider, $payload, injectUser: true);

        $row = DB::transaction(function () use ($modelClass, $payload) {
            /** @var \Illuminate\Database\Eloquent\Model $m */
            $m = new $modelClass();
            $m->fill($payload);
            $m->save();
            return $m->fresh();
        });

        return [
            'ok' => true,
            'data' => [ 'record' => $row->toArray(), '_source' => [ 'entity' => $provider->key(), 'model' => $modelClass, 'updated_at' => now()->toISOString() ] ],
            'message' => 'Created'
        ];
    }

    private function update($provider, string $modelClass, ?int $id, array $data, string $traceId): array
    {
        if (!$id) { return $this->error('VALIDATION_ERROR', 'id is required for update', $traceId); }
        $row = $provider->teamScopedQuery()->where('id', $id)->first();
        if (!$row) { return $this->error('ROW_NOT_FOUND', 'Record not found', $traceId); }

        Gate::authorize('update', $row);

        $fillable = method_exists($provider, 'fillableFields') ? $provider->fillableFields() : [];
        $readonly = method_exists($provider, 'readonlyFields') ? $provider->readonlyFields() : [];
        $payload = $this->filterPayload($data, $fillable, $readonly);
        // prevent scope tampering
        unset($payload['team_id'], $payload['user_id']);

        DB::transaction(function () use ($row, $payload) { $row->fill($payload)->save(); });
        $row = $row->fresh();

        return [
            'ok' => true,
            'data' => [ 'record' => $row->toArray(), '_source' => [ 'entity' => $provider->key(), 'model' => $modelClass, 'updated_at' => now()->toISOString() ] ],
            'message' => 'Updated'
        ];
    }

    private function delete($provider, string $modelClass, ?int $id, string $traceId): array
    {
        if (!$id) { return $this->error('VALIDATION_ERROR', 'id is required for delete', $traceId); }
        $row = $provider->teamScopedQuery()->where('id', $id)->first();
        if (!$row) { return $this->error('ROW_NOT_FOUND', 'Record not found', $traceId); }

        Gate::authorize('delete', $row);
        DB::transaction(function () use ($row) { $row->delete(); });

        return [ 'ok' => true, 'data' => [ 'deleted_id' => $id ], 'message' => 'Deleted' ];
    }

    private function filterPayload(array $data, array $fillable, array $readonly): array
    {
        $payload = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $readonly, true)) { continue; }
            if (!empty($fillable) && !in_array($k, $fillable, true)) { continue; }
            $payload[$k] = $v;
        }
        return $payload;
    }

    private function injectScope($provider, array $payload, bool $injectUser = true): array
    {
        $fields = method_exists($provider, 'readableFields') ? $provider->readableFields() : [];
        $teamId = auth()->user()?->currentTeam?->id;
        if ($teamId && in_array('team_id', $fields, true)) { $payload['team_id'] = $teamId; }
        if ($injectUser) {
            $userId = auth()->id();
            if ($userId && in_array('user_id', $fields, true)) { $payload['user_id'] = $userId; }
        }
        return $payload;
    }

    private function error(string $code, string $message, string $traceId): array
    {
        return [ 'ok' => false, 'error' => [ 'code' => $code, 'message' => $message, 'trace_id' => $traceId ] ];
    }
}
