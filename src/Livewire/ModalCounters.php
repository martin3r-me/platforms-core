<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;
use Platform\Core\Models\TeamCounterDefinition;
use Platform\Core\Models\TeamCounterEvent;

class ModalCounters extends Component
{
    public $modalShow = false;

    /** @var \Platform\Core\Models\Team|null */
    public $baseTeam;

    /** @var \Platform\Core\Models\Team|null */
    public $rootTeam;

    public bool $canManage = false;

    public array $counters = [];
    public array $countsToday = [];
    public array $countsAllTime = [];

    // Settings: create
    public string $newLabel = '';
    public string $newDescription = '';

    // Settings: edit
    public array $editLabel = [];
    public array $editDescription = [];

    protected $listeners = ['open-modal-counters' => 'openModal'];

    public function mount(): void
    {
        $this->hydrateContext();
        $this->loadCounters();
    }

    public function openModal(): void
    {
        $this->modalShow = true;
        $this->hydrateContext();
        $this->loadCounters();
    }

    protected function hydrateContext(): void
    {
        $user = auth()->user();
        $this->baseTeam = $user?->currentTeamRelation;
        $this->rootTeam = $this->baseTeam?->getRootTeam();

        $this->canManage = false;
        if ($user && $this->rootTeam) {
            $role = $this->rootTeam
                ->users()
                ->where('user_id', $user->id)
                ->first()
                ?->pivot
                ?->role;

            $this->canManage = in_array($role, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true);
        }
    }

    public function loadCounters(): void
    {
        if (!$this->rootTeam) {
            $this->counters = [];
            $this->countsToday = [];
            $this->countsAllTime = [];
            return;
        }

        $defs = TeamCounterDefinition::query()
            ->where('scope_team_id', $this->rootTeam->id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $this->counters = $defs->toArray();

        $teamIds = $this->rootTeam->getAllTeamIdsIncludingChildren();
        $today = now()->toDateString();

        $this->countsToday = TeamCounterEvent::query()
            ->select('team_counter_definition_id', DB::raw('COALESCE(SUM(delta),0) as total'))
            ->whereIn('team_id', $teamIds)
            ->where('occurred_on', $today)
            ->groupBy('team_counter_definition_id')
            ->pluck('total', 'team_counter_definition_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $this->countsAllTime = TeamCounterEvent::query()
            ->select('team_counter_definition_id', DB::raw('COALESCE(SUM(delta),0) as total'))
            ->whereIn('team_id', $teamIds)
            ->groupBy('team_counter_definition_id')
            ->pluck('total', 'team_counter_definition_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        foreach ($defs as $def) {
            $id = (int) $def->id;
            $this->editLabel[$id] = (string) $def->label;
            $this->editDescription[$id] = (string) ($def->description ?? '');
        }
    }

    public function increment(int $definitionId): void
    {
        $user = auth()->user();
        if (!$user || !$this->baseTeam || !$this->rootTeam) {
            return;
        }

        $def = TeamCounterDefinition::query()
            ->where('id', $definitionId)
            ->where('scope_team_id', $this->rootTeam->id)
            ->where('is_active', true)
            ->first();

        if (!$def) {
            return;
        }

        TeamCounterEvent::create([
            'team_counter_definition_id' => $def->id,
            'team_id' => $this->baseTeam->id,
            'user_id' => $user->id,
            'delta' => 1,
            'occurred_on' => now()->toDateString(),
            'occurred_at' => now(),
        ]);

        $this->loadCounters();
    }

    public function createCounter(): void
    {
        if (!$this->canManage || !$this->rootTeam) {
            return;
        }

        $this->validate([
            'newLabel' => 'required|string|max:120',
            'newDescription' => 'nullable|string|max:500',
        ]);

        $baseSlug = Str::slug($this->newLabel);
        if ($baseSlug === '') {
            $baseSlug = 'counter';
        }

        $slug = $baseSlug;
        $i = 2;
        while (TeamCounterDefinition::where('scope_team_id', $this->rootTeam->id)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        $maxSort = (int) TeamCounterDefinition::where('scope_team_id', $this->rootTeam->id)->max('sort_order');

        TeamCounterDefinition::create([
            'scope_team_id' => $this->rootTeam->id,
            'slug' => $slug,
            'label' => $this->newLabel,
            'description' => $this->newDescription ?: null,
            'is_active' => true,
            'sort_order' => $maxSort + 1,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->newLabel = '';
        $this->newDescription = '';

        $this->loadCounters();
    }

    public function saveCounter(int $definitionId): void
    {
        if (!$this->canManage || !$this->rootTeam) {
            return;
        }

        $def = TeamCounterDefinition::query()
            ->where('id', $definitionId)
            ->where('scope_team_id', $this->rootTeam->id)
            ->first();

        if (!$def) {
            return;
        }

        $label = (string) ($this->editLabel[$definitionId] ?? $def->label);
        $description = (string) ($this->editDescription[$definitionId] ?? ($def->description ?? ''));

        $this->validate([
            'editLabel.' . $definitionId => 'required|string|max:120',
            'editDescription.' . $definitionId => 'nullable|string|max:500',
        ]);

        $def->update([
            'label' => $label,
            'description' => $description !== '' ? $description : null,
        ]);

        $this->loadCounters();
    }

    public function toggleActive(int $definitionId): void
    {
        if (!$this->canManage || !$this->rootTeam) {
            return;
        }

        $def = TeamCounterDefinition::query()
            ->where('id', $definitionId)
            ->where('scope_team_id', $this->rootTeam->id)
            ->first();

        if (!$def) {
            return;
        }

        $def->is_active = !$def->is_active;
        $def->save();

        $this->loadCounters();
    }

    public function render()
    {
        return view('platform::livewire.modal-counters');
    }
}


