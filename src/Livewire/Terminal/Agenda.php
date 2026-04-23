<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaItem;
use Platform\Core\Models\TerminalAgendaMember;
use Platform\Core\Models\TerminalAgendaSlot;

class Agenda extends Component
{
    use WithTerminalContext;

    public ?int $activeAgendaId = null;
    public string $agendaView = 'board'; // 'board' | 'day'
    public ?string $agendaDayDate = null;

    protected function onContextChanged(): void
    {
        unset($this->agendaItems, $this->agendaSlots, $this->agendaBacklogItems, $this->agendaDoneItems);
    }

    /**
     * Receive agenda navigation state from parent Terminal.
     */
    #[On('terminal-agenda-state')]
    public function receiveAgendaState(?int $agendaId = null, string $view = 'board', ?string $dayDate = null): void
    {
        $this->activeAgendaId = $agendaId;
        $this->agendaView = $view;
        $this->agendaDayDate = $dayDate;
        unset($this->agendaItems, $this->agendaSlots, $this->agendaBacklogItems, $this->agendaDoneItems, $this->myDayItems, $this->myDayBacklogItems);
    }

    #[On('terminal-agenda-open-my-day')]
    public function openMyDayFromEvent(): void
    {
        $this->openMyDay();
    }

    // ── Computed Properties ──────────────────────────────────

    #[Computed]
    public function agendas(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        return TerminalAgenda::forTeam($teamId)
            ->whereHas('members', fn ($q) => $q->where('user_id', auth()->id()))
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon ?? '📋',
                'item_count' => $a->item_count,
                'role' => $a->members()->where('user_id', auth()->id())->value('role') ?? 'member',
            ])
            ->toArray();
    }

    #[Computed]
    public function agendaItems(): array
    {
        if (! $this->activeAgendaId) {
            return [];
        }

        $agenda = TerminalAgenda::find($this->activeAgendaId);
        if (! $agenda) {
            return [];
        }

        return $agenda->items()
            ->ordered()
            ->get()
            ->map(fn ($item) => $this->formatAgendaItem($item))
            ->toArray();
    }

    #[Computed]
    public function myDayItems(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        $date = $this->agendaDayDate ?: now()->toDateString();

        $agendaIds = TerminalAgendaMember::where('user_id', auth()->id())
            ->whereHas('agenda', fn ($q) => $q->where('team_id', $teamId))
            ->pluck('agenda_id');

        if ($agendaIds->isEmpty()) {
            return [];
        }

        return TerminalAgendaItem::whereIn('agenda_id', $agendaIds)
            ->forDate($date)
            ->ordered()
            ->with('agenda:id,name,icon')
            ->get()
            ->map(fn ($item) => $this->formatAgendaItem($item, true))
            ->toArray();
    }

    #[Computed]
    public function myDayBacklogItems(): array
    {
        $teamId = $this->teamId();
        if (! $teamId) {
            return [];
        }

        $agendaIds = TerminalAgendaMember::where('user_id', auth()->id())
            ->whereHas('agenda', fn ($q) => $q->where('team_id', $teamId))
            ->pluck('agenda_id');

        if ($agendaIds->isEmpty()) {
            return [];
        }

        return TerminalAgendaItem::whereIn('agenda_id', $agendaIds)
            ->backlog()
            ->open()
            ->ordered()
            ->with('agenda:id,name,icon')
            ->get()
            ->map(fn ($item) => $this->formatAgendaItem($item, true))
            ->toArray();
    }

    #[Computed]
    public function agendaSlots(): array
    {
        if (! $this->activeAgendaId) {
            return [];
        }

        return TerminalAgendaSlot::where('agenda_id', $this->activeAgendaId)
            ->orderBy('order')
            ->get()
            ->map(fn ($slot) => [
                'id' => $slot->id,
                'name' => $slot->name,
                'order' => $slot->order,
                'color' => $slot->color,
                'items' => $slot->items()
                    ->where('is_done', false)
                    ->ordered()
                    ->get()
                    ->map(fn ($item) => $this->formatAgendaItem($item))
                    ->toArray(),
            ])
            ->toArray();
    }

    #[Computed]
    public function agendaBacklogItems(): array
    {
        if (! $this->activeAgendaId) {
            return [];
        }

        return TerminalAgendaItem::where('agenda_id', $this->activeAgendaId)
            ->whereNull('agenda_slot_id')
            ->where('is_done', false)
            ->ordered()
            ->get()
            ->map(fn ($item) => $this->formatAgendaItem($item))
            ->toArray();
    }

    #[Computed]
    public function agendaDoneItems(): array
    {
        if (! $this->activeAgendaId) {
            return [];
        }

        return TerminalAgendaItem::where('agenda_id', $this->activeAgendaId)
            ->where('is_done', true)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn ($item) => $this->formatAgendaItem($item))
            ->toArray();
    }

    // ── Actions ──────────────────────────────────────────────

    public function selectAgenda(int $agendaId): void
    {
        $this->activeAgendaId = $agendaId;
        if ($this->agendaView === 'day') {
            $this->agendaView = 'board';
        }
        unset($this->agendaItems, $this->agendaSlots, $this->agendaBacklogItems, $this->agendaDoneItems);
    }

    public function openMyDay(): void
    {
        $this->activeAgendaId = null;
        $this->agendaView = 'day';
        $this->agendaDayDate = now()->toDateString();
        unset($this->myDayItems, $this->myDayBacklogItems);
    }

    public function navigateDay(string $direction): void
    {
        $current = $this->agendaDayDate ?: now()->toDateString();
        $date = \Carbon\Carbon::parse($current);

        $this->agendaDayDate = $direction === 'next'
            ? $date->addDay()->toDateString()
            : $date->subDay()->toDateString();

        unset($this->myDayItems);
    }

    public function createAgenda(string $name, ?string $description = null, ?string $icon = null): void
    {
        $teamId = $this->teamId();
        if (! $teamId || empty(trim($name))) {
            return;
        }

        $agenda = TerminalAgenda::create([
            'team_id' => $teamId,
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'icon' => $icon,
        ]);

        TerminalAgendaMember::create([
            'agenda_id' => $agenda->id,
            'user_id' => auth()->id(),
            'role' => 'owner',
        ]);

        $this->activeAgendaId = $agenda->id;
        $this->agendaView = 'board';
        unset($this->agendas, $this->agendaItems);
    }

    public function updateAgenda(int $agendaId, string $name, ?string $description = null, ?string $icon = null): void
    {
        $agenda = TerminalAgenda::find($agendaId);
        if (! $agenda || empty(trim($name))) {
            return;
        }

        $agenda->update([
            'name' => trim($name),
            'description' => $description ? trim($description) : null,
            'icon' => $icon ?? $agenda->icon,
        ]);

        unset($this->agendas);
    }

    public function deleteAgenda(int $agendaId): void
    {
        $agenda = TerminalAgenda::find($agendaId);
        if (! $agenda) {
            return;
        }

        $isOwner = TerminalAgendaMember::where('agenda_id', $agenda->id)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return;
        }

        $agenda->delete();

        if ($this->activeAgendaId === $agendaId) {
            $this->activeAgendaId = null;
        }

        unset($this->agendas);
    }

    public function getAgendaMembers(): array
    {
        if (! $this->activeAgendaId) {
            return [];
        }

        return TerminalAgendaMember::where('agenda_id', $this->activeAgendaId)
            ->with('user:id,name,avatar')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->user_id,
                'name' => $m->user?->name ?? 'Unbekannt',
                'avatar' => $m->user?->avatar,
                'initials' => $this->initials($m->user?->name ?? '?'),
                'role' => $m->role,
            ])
            ->toArray();
    }

    public function addAgendaMember(int $userId): void
    {
        if (! $this->activeAgendaId) {
            return;
        }

        TerminalAgendaMember::firstOrCreate(
            ['agenda_id' => $this->activeAgendaId, 'user_id' => $userId],
            ['role' => 'member']
        );
    }

    public function removeAgendaMember(int $userId): void
    {
        if (! $this->activeAgendaId) {
            return;
        }

        $isOwner = TerminalAgendaMember::where('agenda_id', $this->activeAgendaId)
            ->where('user_id', auth()->id())
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner || $userId === auth()->id()) {
            return;
        }

        TerminalAgendaMember::where('agenda_id', $this->activeAgendaId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function createAgendaItem(int $agendaId, string $title, ?string $notes = null, ?string $date = null, ?string $timeStart = null, ?string $timeEnd = null, ?string $color = null): void
    {
        $agenda = TerminalAgenda::find($agendaId);
        if (! $agenda || empty(trim($title))) {
            return;
        }

        $maxSort = TerminalAgendaItem::where('agenda_id', $agendaId)->max('sort_order') ?? 0;

        TerminalAgendaItem::create([
            'agenda_id' => $agendaId,
            'title' => trim($title),
            'notes' => $notes ? trim($notes) : null,
            'date' => $date,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'color' => $color,
            'sort_order' => $maxSort + 1,
        ]);

        $agenda->refreshItemCount();
        unset($this->agendaItems, $this->agendas, $this->myDayItems);
    }

    public function updateAgendaItem(int $itemId, ?string $title = null, ?string $notes = null, ?string $date = null, ?string $timeStart = null, ?string $timeEnd = null, ?string $color = null): void
    {
        $item = TerminalAgendaItem::find($itemId);
        if (! $item) {
            return;
        }

        $updates = [];
        if ($title !== null) {
            $updates['title'] = trim($title);
        }
        if ($notes !== null) {
            $updates['notes'] = $notes === '' ? null : trim($notes);
        }
        if ($date !== null) {
            $updates['date'] = $date === '' ? null : $date;
        }
        if ($timeStart !== null) {
            $updates['time_start'] = $timeStart === '' ? null : $timeStart;
        }
        if ($timeEnd !== null) {
            $updates['time_end'] = $timeEnd === '' ? null : $timeEnd;
        }
        if ($color !== null) {
            $updates['color'] = $color === '' ? null : $color;
        }

        if (! empty($updates)) {
            $item->update($updates);
        }

        unset($this->agendaItems, $this->myDayItems);
    }

    public function deleteAgendaItem(int $itemId): void
    {
        $item = TerminalAgendaItem::find($itemId);
        if (! $item) {
            return;
        }

        $agenda = $item->agenda;
        $item->delete();
        $agenda?->refreshItemCount();
        unset($this->agendaItems, $this->agendas, $this->myDayItems, $this->myDayBacklogItems);
    }

    public function detachAgendaItem(int $itemId): void
    {
        $item = TerminalAgendaItem::find($itemId);
        if (! $item) {
            return;
        }

        $agenda = $item->agenda;
        $item->delete();
        $agenda?->refreshItemCount();
        unset($this->agendaItems, $this->agendas, $this->myDayItems, $this->myDayBacklogItems);
    }

    public function toggleAgendaItemDone(int $itemId): void
    {
        $item = TerminalAgendaItem::find($itemId);
        if (! $item) {
            return;
        }

        $item->update(['is_done' => ! $item->is_done]);
        $item->agenda?->refreshItemCount();
        unset($this->agendaItems, $this->agendas, $this->myDayItems, $this->agendaSlots, $this->agendaBacklogItems, $this->agendaDoneItems);
    }

    public function updateAgendaItemOrder(array $items): void
    {
        foreach ($items as $entry) {
            TerminalAgendaItem::where('id', $entry['value'])
                ->update(['sort_order' => $entry['order']]);
        }

        unset($this->agendaItems, $this->myDayItems);
    }

    public function moveAgendaItemDate(int $itemId, ?string $date): void
    {
        $item = TerminalAgendaItem::find($itemId);
        if (! $item) {
            return;
        }

        $item->update(['date' => $date ?: null]);
        unset($this->agendaItems, $this->myDayItems, $this->myDayBacklogItems);
    }

    // ── Agenda Context Attach ──────────────────────────────────

    private const AGENDABLE_TYPE_LABELS = [
        \Platform\Planner\Models\PlannerTask::class => 'Aufgabe',
        \Platform\Planner\Models\PlannerProject::class => 'Projekt',
        \Platform\Canvas\Models\Canvas::class => 'Canvas',
        \Platform\Helpdesk\Models\HelpdeskBoard::class => 'Board',
        \Platform\Helpdesk\Models\HelpdeskTicket::class => 'Ticket',
        \Platform\Okr\Models\Objective::class => 'Objective',
        \Platform\Okr\Models\KeyResult::class => 'Key Result',
        \Platform\Brands\Models\BrandsBrand::class => 'Marke',
    ];

    private const CONTEXT_AGENDABLE_TYPES = [
        \Platform\Planner\Models\PlannerTask::class => true,
        \Platform\Planner\Models\PlannerProject::class => true,
        \Platform\Canvas\Models\Canvas::class => true,
        \Platform\Helpdesk\Models\HelpdeskBoard::class => true,
        \Platform\Helpdesk\Models\HelpdeskTicket::class => true,
        \Platform\Okr\Models\Objective::class => true,
        \Platform\Okr\Models\KeyResult::class => true,
        \Platform\Brands\Models\BrandsBrand::class => true,
    ];

    public function canAttachContextToAgenda(): bool
    {
        return $this->activeAgendaId
            && $this->contextType
            && $this->contextId
            && isset(self::CONTEXT_AGENDABLE_TYPES[$this->contextType]);
    }

    public function isContextAttachedToAgenda(): bool
    {
        if (! $this->activeAgendaId || ! $this->contextType || ! $this->contextId) {
            return false;
        }

        return TerminalAgendaItem::where('agenda_id', $this->activeAgendaId)
            ->where('agendable_type', $this->contextType)
            ->where('agendable_id', $this->contextId)
            ->exists();
    }

    public function attachContextToAgenda(): void
    {
        if (! $this->canAttachContextToAgenda()) {
            return;
        }

        $existing = TerminalAgendaItem::where('agenda_id', $this->activeAgendaId)
            ->where('agendable_type', $this->contextType)
            ->where('agendable_id', $this->contextId)
            ->first();

        if ($existing) {
            return;
        }

        $entity = $this->contextType::find($this->contextId);
        if (! $entity) {
            return;
        }

        $title = null;
        $color = null;

        if ($entity instanceof \Platform\Core\Contracts\AgendaRenderable) {
            $rendered = $entity->toAgendaItem();
            $title = $rendered['title'] ?? null;
            $color = $rendered['color'] ?? null;
        }

        if (! $title) {
            $title = $entity->title ?? $entity->name ?? class_basename($this->contextType) . ' #' . $this->contextId;
        }

        TerminalAgendaItem::create([
            'agenda_id' => $this->activeAgendaId,
            'agendable_type' => $this->contextType,
            'agendable_id' => $this->contextId,
            'title' => $title,
            'color' => $color,
            'created_by_user_id' => auth()->id(),
        ]);

        TerminalAgenda::find($this->activeAgendaId)?->refreshItemCount();
        unset($this->agendaItems, $this->agendas, $this->agendaBacklogItems, $this->agendaSlots);
    }

    // ── Kanban Slots ─────────────────────────────────────────

    public function createAgendaSlot(int $agendaId, string $name): void
    {
        $agenda = TerminalAgenda::find($agendaId);
        if (! $agenda || empty(trim($name))) {
            return;
        }

        $maxOrder = TerminalAgendaSlot::where('agenda_id', $agendaId)->max('order') ?? 0;

        TerminalAgendaSlot::create([
            'agenda_id' => $agendaId,
            'name' => trim($name),
            'order' => $maxOrder + 1,
        ]);

        unset($this->agendaSlots);
    }

    public function deleteAgendaSlot(int $slotId): void
    {
        $slot = TerminalAgendaSlot::find($slotId);
        if (! $slot) {
            return;
        }

        $slot->items()->update(['agenda_slot_id' => null]);
        $slot->delete();

        unset($this->agendaSlots, $this->agendaBacklogItems);
    }

    public function renameAgendaSlot(int $slotId, string $name): void
    {
        $slot = TerminalAgendaSlot::find($slotId);
        if (! $slot || empty(trim($name))) {
            return;
        }

        $slot->update(['name' => trim($name)]);
        unset($this->agendaSlots);
    }

    public function updateAgendaItemSlotOrder(array $groups): void
    {
        foreach ($groups as $group) {
            $rawSlotId = $group['value'];
            $items = $group['items'] ?? [];

            $isDone = $rawSlotId === 'done';
            $slotId = ($rawSlotId === 'null' || $rawSlotId === null || $rawSlotId === 'backlog' || $rawSlotId === 'done' || (int) $rawSlotId === 0)
                ? null
                : (int) $rawSlotId;

            foreach ($items as $item) {
                TerminalAgendaItem::where('id', $item['value'])
                    ->update([
                        'agenda_slot_id' => $slotId,
                        'is_done' => $isDone,
                        'sort_order' => $item['order'],
                    ]);
            }
        }

        if ($this->activeAgendaId) {
            TerminalAgenda::find($this->activeAgendaId)?->refreshItemCount();
        }

        unset($this->agendaSlots, $this->agendaBacklogItems, $this->agendaDoneItems, $this->agendaItems, $this->agendas);
    }

    public function updateAgendaSlotOrder(array $slots): void
    {
        foreach ($slots as $entry) {
            if (in_array($entry['value'], ['backlog', 'done'], true) || ! is_numeric($entry['value'])) {
                continue;
            }
            TerminalAgendaSlot::where('id', $entry['value'])
                ->update(['order' => $entry['order']]);
        }

        unset($this->agendaSlots);
    }

    // ── Helpers ──────────────────────────────────────────────

    protected function formatAgendaItem(TerminalAgendaItem $item, bool $showAgenda = false): array
    {
        $isLinked = ! empty($item->agendable_type);

        $data = [
            'id' => $item->id,
            'agenda_id' => $item->agenda_id,
            'agenda_slot_id' => $item->agenda_slot_id,
            'title' => $item->title,
            'notes' => $item->notes,
            'date' => $item->date?->toDateString(),
            'date_label' => $item->date?->translatedFormat('D, d. M') ?? null,
            'time_start' => $item->time_start ? substr($item->time_start, 0, 5) : null,
            'time_end' => $item->time_end ? substr($item->time_end, 0, 5) : null,
            'is_done' => $item->is_done,
            'sort_order' => $item->sort_order,
            'color' => $item->color,
            'is_linked' => $isLinked,
            'agendable_type_label' => $isLinked ? (self::AGENDABLE_TYPE_LABELS[$item->agendable_type] ?? 'Verknüpft') : null,
            'linked_icon' => null,
            'linked_status' => null,
            'linked_status_color' => null,
            'linked_description' => null,
            'linked_url' => null,
            'linked_meta' => [],
        ];

        if ($isLinked && class_exists($item->agendable_type)) {
            try {
                $entity = $item->agendable_type::find($item->agendable_id);
                if ($entity instanceof \Platform\Core\Contracts\AgendaRenderable) {
                    $rendered = $entity->toAgendaItem();
                    $data['linked_icon'] = $rendered['icon'] ?? null;
                    $data['linked_status'] = $rendered['status'] ?? null;
                    $data['linked_status_color'] = $rendered['status_color'] ?? null;
                    $data['linked_description'] = $rendered['description'] ?? null;
                    $data['linked_url'] = $rendered['url'] ?? null;
                    $data['linked_meta'] = $rendered['meta'] ?? [];
                }
            } catch (\Throwable) {
                // Entity may have been deleted or module unloaded
            }
        }

        if ($showAgenda && $item->relationLoaded('agenda') && $item->agenda) {
            $data['agenda_name'] = $item->agenda->name;
            $data['agenda_icon'] = $item->agenda->icon ?? '📋';
        }

        return $data;
    }

    protected function initials(?string $name): string
    {
        if (! $name) {
            return '?';
        }

        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }

        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }

    public function render()
    {
        return view('platform::livewire.terminal.agenda');
    }
}
