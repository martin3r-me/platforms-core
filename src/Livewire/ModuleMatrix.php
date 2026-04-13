<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Module;
use Platform\Core\Models\User;
use Platform\Core\Enums\TeamRole;

class ModuleMatrix extends Component
{
    public $matrixUsers = [];
    public $matrixModules = [];
    public $userModuleMap = [];
    public $isRootTeam = false;

    public function mount()
    {
        $user = Auth::user();
        $currentTeam = $user?->currentTeamRelation;

        if (!$currentTeam) {
            abort(403);
        }

        // Owner-only
        $userRole = $currentTeam->users()->where('user_id', $user->id)->first()?->pivot->role;
        if ($userRole !== TeamRole::OWNER->value) {
            abort(403);
        }

        $this->isRootTeam = $currentTeam->isRootTeam();
        $this->loadMatrixData();
    }

    public function toggleMatrix($userId, $moduleId)
    {
        $user = Auth::user();
        $currentTeam = $user->currentTeamRelation;
        if (!$currentTeam) {
            return;
        }

        $userRole = $currentTeam->users()->where('user_id', $user->id)->first()?->pivot->role;
        if ($userRole !== TeamRole::OWNER->value) {
            return;
        }

        $targetUser = User::findOrFail($userId);
        $module = Module::findOrFail($moduleId);

        if ($module->isRootScoped()) {
            $rootTeam = $currentTeam->getRootTeam();

            if ($currentTeam->id !== $rootTeam->id) {
                return;
            }

            $teamId = $rootTeam->id;
        } else {
            $teamId = $currentTeam->id;
        }

        $alreadyAssigned = $targetUser->modules()
            ->where('module_id', $moduleId)
            ->wherePivot('team_id', $teamId)
            ->exists();

        if ($alreadyAssigned) {
            $targetUser->modules()->newPivotStatement()
                ->where('modulable_id', $targetUser->id)
                ->where('modulable_type', User::class)
                ->where('module_id', $moduleId)
                ->where('team_id', $teamId)
                ->delete();
        } else {
            $targetUser->modules()->attach($moduleId, [
                'role' => null,
                'enabled' => true,
                'guard' => 'web',
                'team_id' => $teamId,
            ]);
        }

        $this->loadMatrixData();
    }

    public function loadMatrixData()
    {
        $user = Auth::user();
        $currentTeam = $user->currentTeamRelation;
        if (!$currentTeam) {
            $this->matrixUsers = [];
            $this->matrixModules = [];
            $this->userModuleMap = [];
            return;
        }

        $teamId = $currentTeam->id;
        $rootTeam = $currentTeam->getRootTeam();
        $rootTeamId = $rootTeam->id;

        $this->matrixUsers = User::whereHas('teams', function ($q) use ($teamId) {
            $q->where('teams.id', $teamId);
        })->get();

        $this->matrixModules = Module::all();

        $userIds = $this->matrixUsers->pluck('id')->all();
        $moduleIds = $this->matrixModules->pluck('id')->all();

        if (empty($userIds) || empty($moduleIds)) {
            $this->userModuleMap = [];
            return;
        }

        // Single query: fetch all enabled modulable rows for these users/modules
        $teamIds = array_unique([$teamId, $rootTeamId]);
        $rows = DB::table('modulables')
            ->whereIn('modulable_id', $userIds)
            ->where('modulable_type', User::class)
            ->whereIn('module_id', $moduleIds)
            ->where('enabled', true)
            ->whereIn('team_id', $teamIds)
            ->get(['modulable_id', 'module_id', 'team_id']);

        // Build map: user_id => [module_id, ...]
        // Respect scope: root-scoped modules only count if team_id matches rootTeamId,
        // team-scoped modules only count if team_id matches current teamId
        $rootScopedModuleIds = $this->matrixModules
            ->filter(fn ($m) => $m->isRootScoped())
            ->pluck('id')
            ->all();

        $map = [];
        foreach ($userIds as $uid) {
            $map[$uid] = [];
        }

        foreach ($rows as $row) {
            $isRootScoped = in_array($row->module_id, $rootScopedModuleIds);
            if ($isRootScoped && $row->team_id == $rootTeamId) {
                $map[$row->modulable_id][] = $row->module_id;
            } elseif (!$isRootScoped && $row->team_id == $teamId) {
                $map[$row->modulable_id][] = $row->module_id;
            }
        }

        $this->userModuleMap = $map;
    }

    public function render()
    {
        return view('platform::livewire.module-matrix')
            ->layout('platform::layouts.app');
    }
}
