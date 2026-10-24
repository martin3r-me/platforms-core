<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\UserUiPreference;
use Platform\Core\Support\PageContextState;
use Platform\Core\Terminal\Contracts\HasBadge;
use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalAppRegistry;
use Platform\Core\Terminal\TerminalContext;

/**
 * Kontext-Panel — rechte Sidebar für alles, was am aktuellen Datensatz hängt
 * (Verknüpfungen, Zeit, Aktivität, Dateien, Tags, OKR, Felder).
 *
 * Zweite Hülle über dem TerminalAppRegistry: die Apps selbst bleiben die
 * bestehenden Terminal-App-Komponenten. Sichtbar nur, solange die Seite per
 * HasPageContext (`page-context`) ein konkretes Modell meldet.
 * Konvention: ui-tailwind/docs/module-layout.md
 */
class ContextPanel extends Component
{
    public ?string $contextType = null;
    public ?int $contextId = null;
    public ?string $subject = null;
    public ?string $url = null;
    public ?string $source = null;
    /** Von der Seite angebotene Apps (page-context). */
    public array $apps = [];
    /** Ziel der Zusatzfeld-Definitionen ['type' => …, 'id' => …] (bei Tasks z. B. das Projekt). */
    public ?array $extrafields = null;
    public ?string $activeApp = null;
    /** Reiter-Kennzahlen [app => '3'|'12h'|null]; nur Apps mit HasBadge. null = leer. */
    public array $badges = [];

    /** Nicht persistiert — nur innerhalb eines Requests. */
    protected ?array $uiPreferenceCache = null;

    /**
     * Die Seite ist im selben Request schon gerendert (Layout kommt danach) und hat
     * ihren Kontext abgelegt — Panel steht damit sofort im ersten HTML.
     */
    public function mount(): void
    {
        if ($payload = app(PageContextState::class)->payload) {
            $this->applyContext($payload);
        }
    }

    #[On('page-context')]
    public function setPageContext(array $payload = []): void
    {
        if (empty($payload['model']) || empty($payload['modelId'])) {
            if (! $this->contextId) {
                $this->skipRender();

                return;
            }
            $this->reset(['contextType', 'contextId', 'subject', 'url', 'source', 'apps', 'extrafields', 'activeApp', 'badges']);

            return;
        }

        // Seiten senden page-context bei jedem eigenen Update (und beim Laden, obwohl
        // mount() den Kontext schon hat) — unveränderter Kontext → kein Re-Render
        if ($payload['model'] === $this->contextType && (int) $payload['modelId'] === $this->contextId
            && array_values($payload['apps'] ?? []) === $this->apps && ($payload['subject'] ?? null) === $this->subject) {
            $this->skipRender();

            return;
        }

        $this->applyContext($payload);
    }

    protected function applyContext(array $payload): void
    {
        if (empty($payload['model']) || empty($payload['modelId'])) {
            return;
        }

        $this->contextType = $payload['model'];
        $this->contextId = (int) $payload['modelId'];
        $this->subject = $payload['subject'] ?? null;
        $this->url = $payload['url'] ?? null;
        $this->source = $payload['source'] ?? null;
        $this->apps = array_values($payload['apps'] ?? []);
        $this->extrafields = $payload['extrafields'] ?? null;

        $apps = $this->panelApps();
        $available = array_keys($apps);
        if (! in_array($this->activeApp, $available, true)) {
            $preferred = $this->preferredApp();
            $this->activeApp = in_array($preferred, $available, true) ? $preferred : ($available[0] ?? null);
        }
        $this->refreshBadges($apps);
    }

    public function selectApp(string $key): void
    {
        $apps = $this->panelApps();
        if (array_key_exists($key, $apps)) {
            $this->activeApp = $key;
            // Beim Reiterwechsel auffrischen — was man im letzten Reiter angelegt hat, zählt sofort
            $this->refreshBadges($apps);
        }
    }

    /** Apps melden Änderungen (z. B. Agenda), damit die Zahl am Reiter sofort stimmt. */
    #[On('context-panel-badges')]
    public function onBadgesChanged(): void
    {
        $this->refreshBadges($this->panelApps());
    }

    /** @param array<string, TerminalApp> $apps */
    protected function refreshBadges(array $apps): void
    {
        $context = $this->terminalContext();
        $badges = [];
        foreach ($apps as $key => $app) {
            if (! $app instanceof HasBadge) {
                continue;
            }
            try {
                $badges[$key] = $app->badge($context);
            } catch (\Throwable $e) {
                report($e); // Zähler darf das Panel nie kippen — dann eben ohne Badge
            }
        }
        $this->badges = $badges;
    }

    /**
     * Apps fürs Panel: alles aus der Registry außer den Terminal-eigenen
     * (native), sortiert nach order — Verknüpfungen zuerst.
     *
     * @return array<string, TerminalApp>
     */
    protected function panelApps(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        $apps = array_filter(
            TerminalAppRegistry::availableFor($this->terminalContext()),
            fn (TerminalApp $app) => $app->group() !== 'native',
        );
        uasort($apps, fn (TerminalApp $a, TerminalApp $b) => $a->order() <=> $b->order());

        return $apps;
    }

    protected function terminalContext(): TerminalContext
    {
        return new TerminalContext(
            teamId: auth()->user()?->currentTeam?->id,
            contextType: $this->contextType,
            contextId: $this->contextId,
            subject: $this->subject,
            source: $this->source,
            apps: $this->apps,
        );
    }

    /** Props für die App-Komponente — Zusatzfelder hängen am Definitions-Ziel. */
    protected function appProps(string $key): array
    {
        if ($key === 'extrafields') {
            return [
                'efContextType' => $this->extrafields['type'] ?? null,
                'efContextId' => isset($this->extrafields['id']) ? (int) $this->extrafields['id'] : null,
                'contextBreadcrumb' => [],
            ];
        }

        return [
            'contextType' => $this->contextType,
            'contextId' => $this->contextId,
            'contextSubject' => $this->subject,
            'contextSource' => $this->source,
            'contextUrl' => $this->url,
            'contextMeta' => [],
        ];
    }

    /** Zuletzt gewählter Reiter (UI-Präferenz, vom Panel per Store gesetzt). */
    protected function preferredApp(): ?string
    {
        return $this->uiPreference()['app'] ?? null;
    }

    /** Panel-Präferenzen des Users (app, open, width) — einmal pro Request gelesen. */
    protected function uiPreference(): array
    {
        if ($this->uiPreferenceCache === null) {
            $userId = auth()->id();
            $state = $userId ? UserUiPreference::where('user_id', $userId)->value('state') : null;
            $state = is_string($state) ? json_decode($state, true) : (array) $state;
            $this->uiPreferenceCache = (array) data_get($state, 'context_panel', []);
        }

        return $this->uiPreferenceCache;
    }

    public function render()
    {
        $apps = $this->panelApps();
        $active = $this->activeApp && isset($apps[$this->activeApp]) ? $apps[$this->activeApp] : null;

        return view('platform::livewire.context-panel', [
            'panelApps' => $apps,
            'active' => $active,
            'activeProps' => $active ? $this->appProps($active->key()) : [],
            // Startzustand fürs erste HTML (vor Alpine) — sonst springt die Breite beim Laden
            'initialOpen' => (bool) ($this->uiPreference()['open'] ?? true),
            'initialWidth' => max(320, min(720, (int) ($this->uiPreference()['width'] ?? 400))),
        ]);
    }
}
