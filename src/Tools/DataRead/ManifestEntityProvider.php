<?php

namespace Platform\Core\Tools\DataRead;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ManifestEntityProvider implements EntityReadProvider
{
    private string $entityKey;
    private string $modelClass;
    private array $fields; // [name => ['type'=>..., 'fillable'=>bool, 'readonly'=>bool, 'pii'=>bool]]
    private array $defaults; // ['sort'=>[], 'filters'=>[]]
    private bool $teamScoped;

    public function __construct(array $manifest)
    {
        $this->entityKey = $manifest['entity'] ?? '';
        $this->modelClass = $manifest['model'] ?? '';
        $this->fields = $manifest['fields'] ?? [];
        $this->defaults = $manifest['defaults'] ?? [];
        $this->teamScoped = (bool)($manifest['team_scoped'] ?? false);
    }

    public function key(): string { return $this->entityKey; }
    public function model(): string { return $this->modelClass; }

    public function readableFields(): array { return array_keys($this->fields); }

    public function fillableFields(): array
    {
        $out = [];
        foreach ($this->fields as $name => $meta) {
            if (!empty($meta['fillable'])) { $out[] = $name; }
        }
        return $out;
    }

    public function readonlyFields(): array
    {
        $out = [];
        foreach ($this->fields as $name => $meta) {
            if (!empty($meta['readonly'])) { $out[] = $name; }
        }
        return $out;
    }

    public function allowedFilters(): array
    {
        $result = [];
        foreach ($this->fields as $name => $meta) {
            $type = strtolower((string)($meta['type'] ?? 'string'));
            $ops = match (true) {
                str_contains($type, 'bool') => ['eq'],
                str_contains($type, 'int') || str_contains($type, 'float') || str_contains($type, 'decimal') || $type === 'integer' || $type === 'number' => ['eq','ne','in','gte','lte'],
                str_contains($type, 'date') => ['eq','ne','gte','lte','between','is_null'],
                default => ['eq','ne','like','in']
            };
            $result[$name] = $ops;
        }
        return $result;
    }

    public function allowedSorts(): array
    {
        $candidates = ['id','title','due_date','created_at','updated_at','order'];
        $fields = $this->readableFields();
        $sorts = array_values(array_intersect($candidates, $fields));
        if (empty($sorts)) { $sorts = $fields; }
        return $sorts;
    }

    public function relationsWhitelist(): array { return []; }

    public function searchFields(): array
    {
        $fields = $this->readableFields();
        $out = [];
        foreach (['title','name','description'] as $f) {
            if (in_array($f, $fields, true)) { $out[] = $f; }
        }
        return $out;
    }

    public function defaultProjection(): array
    {
        $fields = $this->readableFields();
        $pref = ['id','title','name','description','due_date','is_done','created_at'];
        $out = array_values(array_intersect($pref, $fields));
        if (empty($out)) { $out = array_slice($fields, 0, 6); }
        return $out;
    }

    public function teamScopedQuery(): Builder
    {
        $model = $this->model();
        $q = $model::query();
        if ($this->teamScoped && in_array('team_id', $this->readableFields(), true)) {
            $teamId = Auth::user()?->currentTeam?->id;
            if ($teamId) { $q->where('team_id', $teamId); }
        }
        return $q;
    }

    public function applyDomainDefaults(Builder $query, array &$options): void
    {
        // Apply default filters
        foreach (($this->defaults['filters'] ?? []) as $filter) {
            // Only apply if not overridden
            $exists = collect($options['filters'] ?? [])->contains(fn($f) => ($f['field'] ?? null) === ($filter['field'] ?? null));
            if (!$exists) {
                $options['filters'][] = $filter;
            }
        }
        // Apply default sort if none provided
        if (empty($options['sort']) && !empty($this->defaults['sort'])) {
            foreach ($this->defaults['sort'] as $s) {
                $field = $s['field'] ?? null; $dir = $s['dir'] ?? 'asc';
                if ($field) { $query->orderBy($field, $dir); }
            }
        }
    }

    public function mapFilter(array $filter): ?array
    {
        return $filter; // No mapping by default
    }

    public function piiFields(): array
    {
        $out = [];
        foreach ($this->fields as $name => $meta) {
            if (!empty($meta['pii'])) { $out[] = $name; }
        }
        return $out;
    }
}
