<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Platform\Core\PlatformCore;
use Platform\Core\Models\TeamBillableUsage;
use Platform\Core\Models\TeamUserLastModule;
use Platform\Core\Models\Module;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\CheckinTodo;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    /** Freigeschaltete Module (für „Weiterarbeiten"). */
    public array $modules = [];

    /** UI-Team des Users. */
    public $currentTeam;

    /** Key des zuletzt genutzten Moduls (Hervorhebung in „Weiterarbeiten"). */
    public ?string $lastModuleKey = null;

    // --- „Mein Tag" ---
    public string $firstName = '';
    public string $greeting = '';
    public int $streak = 0;

    /** Heutiger Check-in als Array (oder null, wenn noch keiner gemacht). */
    public ?array $todayCheckin = null;

    /** Offene Todos: [['id' => int, 'title' => string], ...]. */
    public array $openTodos = [];

    // --- Team-Kontext (dezent im Fuß) ---
    public int $memberCount = 0;
    public float $monthlyTotal = 0.0;

    public function mount(): void
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        $this->currentTeam = $user->currentTeam;

        if (!$this->currentTeam) {
            return;
        }

        $this->firstName = trim(explode(' ', (string) $user->name)[0] ?? '');
        $this->greeting = $this->greetingForHour((int) now()->format('G'));

        $baseTeam = $user->currentTeamRelation;

        $this->lastModuleKey = TeamUserLastModule::getLastModule($user->id, $this->currentTeam->id);
        $this->modules = $this->loadAccessibleModules($user, $baseTeam);

        $this->memberCount = $this->currentTeam->users()->count();
        $this->loadDay();
        $this->loadMonthlyCosts();
    }

    /**
     * Lädt heutigen Check-in, Streak und offene Todos neu.
     * Wird auch nach dem Speichern eines Check-ins aufgerufen.
     */
    #[On('checkin-saved')]
    public function loadDay(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            return;
        }

        $this->streak = Checkin::currentStreak($userId);

        $checkin = Checkin::where('user_id', $userId)
            ->where('date', now()->toDateString())
            ->first();
        $this->todayCheckin = $checkin?->toArray();

        $this->openTodos = CheckinTodo::whereHas(
                'checkin',
                fn ($q) => $q->where('user_id', $userId)
            )
            ->where('done', false)
            ->orderByDesc('checkin_id')
            ->limit(12)
            ->get()
            ->map(fn (CheckinTodo $t) => ['id' => $t->id, 'title' => $t->title])
            ->all();
    }

    /**
     * Todo abhaken/wieder öffnen. Nur eigene Todos.
     */
    public function toggleTodo(int $id): void
    {
        $todo = CheckinTodo::with('checkin')->find($id);

        if (!$todo || (int) $todo->checkin?->user_id !== (int) Auth::id()) {
            return;
        }

        $todo->update(['done' => !$todo->done]);
        $this->loadDay();
    }

    protected function greetingForHour(int $hour): string
    {
        return match (true) {
            $hour < 11 => 'Guten Morgen',
            $hour < 18 => 'Guten Tag',
            default    => 'Guten Abend',
        };
    }

    /**
     * Liefert die im Code registrierten Module, gefiltert auf jene,
     * auf die der User im aktuellen Team Zugriff hat.
     */
    protected function loadAccessibleModules($user, $baseTeam): array
    {
        if (!$baseTeam) {
            return [];
        }

        $registered = PlatformCore::getVisibleModules();
        if (empty($registered)) {
            return [];
        }

        $keys = array_values(array_filter(array_map(fn ($m) => $m['key'] ?? null, $registered)));
        $modelsByKey = Module::whereIn('key', $keys)->get()->keyBy('key');

        return collect($registered)
            ->filter(function ($module) use ($modelsByKey, $user, $baseTeam) {
                $model = $modelsByKey->get($module['key'] ?? null);
                return $model && $model->hasAccess($user, $baseTeam);
            })
            ->sortBy(fn ($module) => $module['navigation']['order'] ?? 999)
            ->values()
            ->all();
    }

    protected function loadMonthlyCosts(): void
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $sum = TeamBillableUsage::where('team_id', $this->currentTeam->id)
            ->whereBetween('usage_date', [$startOfMonth, $endOfMonth])
            ->sum('total_cost');

        $this->monthlyTotal = is_numeric($sum) ? (float) $sum : 0.0;
    }

    public function render()
    {
        return view('platform::livewire.dashboard')->layout('platform::layouts.app');
    }
}
