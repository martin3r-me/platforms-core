<?php

namespace Platform\Core\Tools\DataRead\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Tools\DataRead\EntityReadProvider;

class OkrKeyResultProvider implements EntityReadProvider
{
    public function key(): string { return 'okr.key_result'; }
    public function model(): string { return 'Platform\\Okr\\Models\\OkrKeyResult'; }

    public function readableFields(): array { return ['id','uuid','title','progress','owner_id','team_id','due_date','created_at','updated_at']; }
    public function allowedFilters(): array { return ['id'=>['eq','ne','in'],'owner_id'=>['eq','in'],'team_id'=>['eq'],'due_date'=>['gte','lte','between']]; }
    public function allowedSorts(): array { return ['due_date','created_at','updated_at','title']; }
    public function relationsWhitelist(): array { return ['owner','team','objective']; }
    public function searchFields(): array { return ['title']; }
    public function defaultProjection(): array { return ['id','title','progress','due_date']; }

    public function teamScopedQuery(): Builder
    {
        $model = $this->model();
        $q = $model::query();
        $team = Auth::user()?->currentTeam;
        if ($team) { $q->where('team_id', $team->id); }
        return $q;
    }

    public function applyDomainDefaults(Builder $query, array &$options): void
    {
        if (empty($options['sort'])) {
            $query->orderBy('due_date','asc');
        }
    }

    public function mapFilter(array $filter): ?array
    {
        return $filter; // no mapping for now
    }
}
