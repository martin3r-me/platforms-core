<?php

namespace Platform\Core\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\UserUiPreference;
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

    #[On('page-context')]
    public function setPageContext(array $payload = []): void
    {
        if (empty($payload['model']) || empty($payload['modelId'])) {
            if (! $this->contextId) {
                $this->skipRender();

                return;
            }
            $this->reset(['contextType', 'contextId', 'subject', 'url', 'source', 'apps', 'extrafields', 'activeApp']);

            return;
        }

        // Seiten senden page-context bei jedem eigenen Update — unveränderter Kontext → kein Re-Render
        if ($payload['model'] === $this->contextType && (int) $payload['modelId'] === $this->contextId
            && array_values($payload['apps'] ?? []) === $this->apps && ($payload['subject'] ?? null) === $this->subject) {
            $this->skipRender();

            return;
        }

        $this->contextType = $payload['model'];
        $this->contextId = (int) $payload['modelId'];
        $this->subject = $payload['subject'] ?? null;
        $this->url = $payload['url'] ?? null;
        $this->source = $payload['source'] ?? null;
        $this->apps = array_values($payload['apps'] ?? []);
        $this->extrafields = $payload['extrafields'] ?? null;

        $available = array_keys($this->panelApps());
        if (! in_array($this->activeApp, $available, true)) {
            $preferred = $this->preferredApp();
            $this->activeApp = in_array($preferred, $available, true) ? $preferred : ($available[0] ?? null);
        }
    }

    public function selectApp(string $key): void
    {
        if (array_key_exists($key, $this->panelApps())) {
            $this->activeApp = $key;
        }
    }

    /**
     * Apps fürs Panel: alles aus der Registry außer den Terminal-eigenen
     * (chat/agenda), sortiert nach order — Verknüpfungen zuerst.
     *
     * @return array<string, TerminalApp>
     */
    protected function panelApps(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        $context = new TerminalContext(
            teamId: auth()->user()?->currentTeam?->id,
            contextType: $this->contextType,
            contextId: $this->contextId,
            subject: $this->subject,
            source: $this->source,
            apps: $this->apps,
        );

        $apps = array_filter(
            TerminalAppRegistry::availableFor($context),
            fn (TerminalApp $app) => $app->group() !== 'native',
        );
        uasort($apps, fn (TerminalApp $a, TerminalApp $b) => $a->order() <=> $b->order());

        return $apps;
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
        $userId = auth()->id();
        if (! $userId) {
            return null;
        }
        $state = UserUiPreference::where('user_id', $userId)->value('state');
        $state = is_string($state) ? json_decode($state, true) : (array) $state;

        return data_get($state, 'context_panel.app');
    }

    public function render()
    {
        $apps = $this->panelApps();
        $active = $this->activeApp && isset($apps[$this->activeApp]) ? $apps[$this->activeApp] : null;

        return view('platform::livewire.context-panel', [
            'panelApps' => $apps,
            'active' => $active,
            'activeProps' => $active ? $this->appProps($active->key()) : [],
        ]);
    }
}
