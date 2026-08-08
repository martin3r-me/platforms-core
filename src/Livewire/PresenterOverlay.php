<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Support\Presenter;

/**
 * Globaler Regie-Player im App-Layout (Alias: core.presenter-overlay). Pollt den Presenter-
 * Kanal des aktuellen Teams und zeigt den aktuellen Schritt als Sprechblase — solange er
 * nicht bestaetigt ist (server-seitiges acknowledged-Flag), auch ueber Seitenwechsel hinweg.
 *
 * Traegt der Schritt eine navigate-URL und ist der Zuschauer nicht dort, beamt der Player
 * ihn hin (Livewire-SPA-Redirect). "Verstanden" bestaetigt den Schritt; danach ist der
 * Browser wieder frei, bis der naechste Schritt gepusht wird.
 */
class PresenterOverlay extends Component
{
    public ?int $teamId = null;
    public ?array $current = null;

    public function mount(): void
    {
        $this->teamId = auth()->user()?->currentTeam?->id;
        $this->syncState();
    }

    public function tick(): void
    {
        $this->syncState();
    }

    /**
     * Aktuellen, unbestaetigten Schritt laden. Navigiert bei Bedarf (nur wenn der Zuschauer
     * noch nicht auf der Zielseite ist) und zeigt sonst die Sprechblase.
     */
    protected function syncState(): void
    {
        if (!$this->teamId) {
            $this->current = null;
            return;
        }

        $step = Presenter::latest($this->teamId);

        if (!$step || !empty($step['acknowledged'])) {
            $this->current = null;
            return;
        }

        // Navigation: nur ausloesen, wenn der Zuschauer noch nicht auf der Zielseite ist.
        if (!empty($step['navigate'])) {
            $targetPath  = trim((string) (parse_url($step['navigate'], PHP_URL_PATH) ?? ''), '/');
            $currentPath = trim(request()->path(), '/');
            if ($targetPath !== '' && $targetPath !== $currentPath) {
                $this->redirect($step['navigate'], navigate: true);
                return;
            }
        }

        $this->current = $step;
    }

    /** Zuschauer hat "Verstanden" geklickt — Schritt bestaetigen. */
    public function dismiss(): void
    {
        if ($this->teamId && $this->current) {
            Presenter::acknowledge($this->teamId, (int) $this->current['id']);
        }
        $this->current = null;
    }

    public function render()
    {
        return view('platform::livewire.presenter-overlay');
    }
}
