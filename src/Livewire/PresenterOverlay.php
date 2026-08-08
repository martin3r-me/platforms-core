<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Platform\Core\Support\Presenter;
use Platform\Core\Support\PresenterTourRegistry;

/**
 * Globaler Regie-Player im App-Layout (Alias: core.presenter-overlay).
 *
 * Zwei Quellen, gepollt alle ~1,5s:
 *   1) Eine laufende TOUR (via PresenterTourRegistry → tour-Modul): geordnete Schritte,
 *      "Weiter" schaltet vor. Hat Vorrang.
 *   2) Ein AD-HOC-Kommentar (Presenter-Cache): einzelner Schritt, "Verstanden" bestaetigt.
 *
 * Traegt der Schritt eine navigate-URL und ist der Zuschauer nicht dort, beamt der Player
 * ihn hin (Livewire-SPA-Redirect). So bleibt der Kommentar ueber Seitenwechsel stehen.
 */
class PresenterOverlay extends Component
{
    public ?int $teamId = null;
    public ?int $userId = null;
    public ?array $current = null;

    public function mount(): void
    {
        $this->teamId = auth()->user()?->currentTeam?->id;
        $this->userId = auth()->id();
        $this->syncState();
    }

    public function tick(): void
    {
        $this->syncState();
    }

    protected function registry(): PresenterTourRegistry
    {
        return app(PresenterTourRegistry::class);
    }

    protected function syncState(): void
    {
        $this->current = null;
        if (!$this->teamId) {
            return;
        }

        // 1) Laufende Tour (Vorrang)
        if ($this->userId) {
            $step = $this->registry()->activeStep($this->userId, $this->teamId);
            if ($step) {
                if ($this->maybeNavigate($step['navigate'] ?? null)) {
                    return;
                }
                $this->current = [
                    'mode'     => 'tour',
                    'title'    => $step['title'] ?? null,
                    'message'  => (string) ($step['message'] ?? ''),
                    'speaker'  => $step['speaker'] ?? 'Claude',
                    'progress' => ((int) ($step['position'] ?? 1)) . ' / ' . ((int) ($step['total'] ?? 1)),
                    'is_last'  => (bool) ($step['is_last'] ?? false),
                ];
                return;
            }
        }

        // 2) Ad-hoc-Kommentar aus dem Cache
        $step = Presenter::latest($this->teamId);
        if ($step && empty($step['acknowledged'])) {
            if ($this->maybeNavigate($step['navigate'] ?? null)) {
                return;
            }
            $this->current = [
                'mode'    => 'adhoc',
                'id'      => (int) $step['id'],
                'title'   => $step['title'] ?? null,
                'message' => (string) ($step['message'] ?? ''),
                'speaker' => $step['speaker'] ?? 'Claude',
            ];
        }
    }

    /** Navigiert (nur wenn der Zuschauer noch nicht auf der Zielseite ist). Gibt true bei Redirect. */
    protected function maybeNavigate(?string $url): bool
    {
        if (!$url) {
            return false;
        }
        $targetPath  = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $currentPath = trim(request()->path(), '/');
        if ($targetPath !== '' && $targetPath !== $currentPath) {
            $this->redirect($url, navigate: true);
            return true;
        }
        return false;
    }

    /** "Weiter" / "Verstanden": Tour vorschalten bzw. Ad-hoc-Kommentar bestaetigen. */
    public function next(): void
    {
        if (!$this->current) {
            return;
        }

        if (($this->current['mode'] ?? '') === 'tour') {
            if ($this->userId && $this->teamId) {
                $this->registry()->advance($this->userId, $this->teamId);
            }
        } elseif (isset($this->current['id']) && $this->teamId) {
            Presenter::acknowledge($this->teamId, (int) $this->current['id']);
        }

        $this->current = null;
        $this->syncState(); // sofort naechsten Schritt laden / hin-navigieren
    }

    /** Laufende Tour abbrechen. */
    public function stopTour(): void
    {
        if ($this->userId && $this->teamId) {
            $this->registry()->stop($this->userId, $this->teamId);
        }
        $this->current = null;
    }

    public function render()
    {
        return view('platform::livewire.presenter-overlay');
    }
}
