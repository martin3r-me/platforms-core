<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Support\Presenter;

/**
 * Globales Overlay im App-Layout (Alias: core.presenter-overlay). Pollt den Presenter-
 * Kanal des aktuellen Teams und zeigt neue Kommentare als Sprechblase.
 *
 * Beim Mount wird die aktuelle Sequenz-ID als "gesehen" gesetzt, damit bereits vorhandene
 * Nachrichten bei einem Seitenwechsel NICHT erneut aufpoppen — es erscheinen nur Kommentare,
 * die nach dem Laden der Seite gepusht werden.
 */
class PresenterOverlay extends Component
{
    public ?int $teamId = null;
    public int $lastSeenId = 0;
    public ?array $current = null;

    public function mount(): void
    {
        $this->teamId = auth()->user()?->currentTeam?->id;
        $latest = $this->teamId ? Presenter::latest($this->teamId) : null;
        $this->lastSeenId = (int) ($latest['id'] ?? 0);
    }

    public function tick(): void
    {
        if (!$this->teamId) {
            return;
        }

        $latest = Presenter::latest($this->teamId);
        if ($latest && (int) ($latest['id'] ?? 0) > $this->lastSeenId) {
            $this->current    = $latest;
            $this->lastSeenId = (int) $latest['id'];
        }
    }

    public function dismiss(): void
    {
        $this->current = null;
    }

    public function render()
    {
        return view('platform::livewire.presenter-overlay');
    }
}
