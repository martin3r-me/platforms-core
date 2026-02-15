<?php

namespace Platform\Core\Tools\DataRead\Providers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Mcp\McpSessionTeamManager;
use Platform\Core\Tools\DataRead\EntityReadProvider;

class PlannerTaskProvider implements EntityReadProvider
{
    public function key(): string { return 'task'; }
    public function model(): string { return 'Platform\\Planner\\Models\\PlannerTask'; }

    public function readableFields(): array
    {
        return [
            'id','uuid','title','description','due_date','is_done','is_frog','story_points','priority','order','created_at','updated_at','user_id','user_in_charge_id','team_id','project_id','task_group_id'
        ];
    }

    public function allowedFilters(): array
    {
        return [
            'id' => ['eq','ne','in'],
            'title' => ['eq','ne','like'],
            'is_done' => ['eq'],
            'is_frog' => ['eq'],
            'due_date' => ['eq','ne','gte','lte','between','is_null'],
            'user_id' => ['eq','ne','in'],
            'user_in_charge_id' => ['eq','ne','in','is_null'],
            'team_id' => ['eq','ne','in'],
            'project_id' => ['eq','ne','in','is_null'],
            'task_group_id' => ['eq','ne','in','is_null'],
            'created_at' => ['gte','lte','between'],
            'updated_at' => ['gte','lte','between'],
        ];
    }

    public function allowedSorts(): array
    {
        return ['id','title','due_date','created_at','updated_at','order'];
    }

    public function relationsWhitelist(): array
    {
        return ['user','team','project','taskGroup','userInCharge'];
    }

    public function searchFields(): array
    {
        return ['title','description'];
    }

    public function defaultProjection(): array
    {
        return ['id','title','description','due_date','is_done','is_frog'];
    }

    public function teamScopedQuery(): Builder
    {
        $model = $this->model();
        $q = $model::query();
        // MCP Session-Team-Override berücksichtigen (gesetzt durch core.team.switch)
        $teamId = null;
        $sessionId = McpSessionTeamManager::resolveSessionId();
        if ($sessionId) {
            $teamId = McpSessionTeamManager::getTeamOverrideId($sessionId);
        }
        if (!$teamId) {
            $teamId = Auth::user()?->currentTeam?->id;
        }
        if ($teamId) { $q->where('team_id', $teamId); }
        return $q;
    }

    public function applyDomainDefaults(Builder $query, array &$options): void
    {
        $hasIsDone = collect($options['filters'] ?? [])->contains(fn($f) => ($f['field'] ?? null) === 'is_done');
        if (!$hasIsDone) { $query->where('is_done', false); }

        if (empty($options['sort'])) {
            $query->orderBy('due_date','asc')->orderBy('created_at','desc');
        }
    }

    public function mapFilter(array $filter): ?array
    {
        if (($filter['field'] ?? null) !== 'status') { return $filter; }
        $op = $filter['op'] ?? 'eq';
        $value = $filter['value'] ?? null;
        if ($value === null) { return null; }
        $completed = is_string($value) ? strtolower($value) === 'completed' : (bool)$value;
        if ($op === 'eq') { return ['field' => 'is_done','op' => 'eq','value' => $completed]; }
        if ($op === 'ne') { return ['field' => 'is_done','op' => 'eq','value' => !$completed]; }
        if ($op === 'in' && is_array($value)) {
            $hasCompleted = collect($value)->contains(fn($v) => strtolower((string)$v) === 'completed');
            return ['field' => 'is_done','op' => 'eq','value' => $hasCompleted];
        }
        return null;
    }
}
