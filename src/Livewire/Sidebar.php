<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\PlatformCore;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Gate;

class Sidebar extends Component
{
    public string $moduleKey;
    public array $items = [];

    #[On('updateSidebar')]
    public function updateSidebar(): void
    {
        $this->refreshSidebar();
    }

    public function mount(string $moduleKey): void
    {
        $this->moduleKey = $moduleKey;
        $this->refreshSidebar();
    }

    protected function refreshSidebar(): void
    {
        $module = PlatformCore::getModule($this->moduleKey);
        $sidebarConfig = $module['sidebar'] ?? [];
        $this->items = $this->resolveSidebar($sidebarConfig);
    }

    protected function resolveSidebar(array $config): array
    {
        return collect($config)->map(function ($group) {
            $items = collect($group['items'] ?? [])->map(function ($item) {
                return [
                    'label'   => $item['label'] ?? 'Unbenannt',
                    'route'   => $item['route'] ?? null,
                    'params'  => $item['params'] ?? [],
                    'icon'    => $item['icon'] ?? null,
                    'badge'   => $item['badge'] ?? null,
                ];
            })->toArray();

            // Dynamische Items anhängen (z.B. pro Model-Instanz)
            if (!empty($group['dynamic']) && is_array($group['dynamic'])) {
                $items = array_merge($items, $this->resolveDynamicItems($group['dynamic']));
            }

            return [
                'group' => $group['group'] ?? 'Navigation',
                'items' => $items,
            ];
        })->toArray();
    }

    protected function resolveDynamicItems(array $dynamic): array
    {
        $model = $dynamic['model'] ?? null;
        if (!$model || !class_exists($model)) {
            return [];
        }

        $query = $model::query();

        // Optional Team-Filter und Sortierung
        $user = auth()->user();
        if (!empty($dynamic['team_based']) && $user && $user->currentTeam) {
            $query->where('team_id', $user->currentTeam->id);
        }
        if (!empty($dynamic['order_by'])) {
            $query->orderBy($dynamic['order_by']);
        }

        $records = $query->get();

        $labelField = $dynamic['label_key'] ?? 'name';
        $route = $dynamic['route'] ?? null;
        $icon = $dynamic['icon'] ?? null;

        // POLICY-FILTER: Nur anzeigen, wenn User "view" darf
        return $records
            ->filter(function ($record) use ($user) {
                if (!$user) return false;
                return Gate::allows('view', $record);
            })
            ->map(function ($record) use ($labelField, $route, $icon) {
                return [
                    'label'   => $record->{$labelField} ?? 'Unbenannt',
                    'route'   => $route,
                    'params'  => [$record->id],
                    'icon'    => $icon,
                    'badge'   => null,
                ];
            })->values()->toArray();
    }

    public function render()
    {
        return view('platform::livewire.sidebar', [
            'items' => $this->items,
        ]);
    }
}