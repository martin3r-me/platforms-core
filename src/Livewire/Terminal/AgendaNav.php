<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaMember;

/**
 * Agenda-Navigation für die linke Terminal-Leiste. Besitzt die Agenda-Liste,
 * „Mein Tag", das Anlegen und den Auswahl-State (activeAgendaId/agendaView) als
 * Single Source of Truth. Bei jeder Auswahl dispatcht sie `terminal-agenda-state`
 * an das Content-Kind (core.terminal.agenda), das Board/„Mein Tag" rendert.
 *
 * Warum getrennt: Die Leiste gehört strukturell zur Shell, die Agenda-Logik soll
 * aber nicht in der God-Komponente liegen — deshalb ein eigenes schlankes Kind.
 */
class AgendaNav extends Component
{
    use WithTerminalContext;

    public ?int $activeAgendaId = null;
    public string $agendaView = 'board'; // 'board' | 'day'

    public function mount(): void
    {
        // Erst-Zustand: „Mein Tag". Das Content-Kind defaultet identisch in seinem
        // eigenen mount(), daher ist der Broadcast hier nur für spätere Öffnungen relevant.
        $this->openMyDay();
    }

    #[On('terminal-agenda-open-my-day')]
    public function openMyDay(): void
    {
        $this->activeAgendaId = null;
        $this->agendaView = 'day';
        $this->broadcastState();
    }

    public function selectAgenda(int $agendaId): void
    {
        $this->activeAgendaId = $agendaId;
        $this->agendaView = 'board';
        $this->broadcastState();
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
        unset($this->agendas);
        $this->broadcastState();
    }

    /**
     * Das Content-Kind meldet Löschungen zurück, damit die Liste + Auswahl aktuell
     * bleiben. War die gelöschte Agenda aktiv, fällt die Nav auf „Mein Tag" zurück.
     */
    #[On('terminal-agenda-deleted')]
    public function onAgendaDeleted(int $agendaId): void
    {
        unset($this->agendas);
        if ($this->activeAgendaId === $agendaId) {
            $this->openMyDay();
        }
    }

    protected function broadcastState(): void
    {
        $this->dispatch('terminal-agenda-state',
            agendaId: $this->activeAgendaId,
            view: $this->agendaView,
            dayDate: now()->toDateString(),
        );
    }

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

    public function render()
    {
        return view('platform::livewire.terminal.agenda-nav');
    }
}
