<?php

namespace Platform\Core\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Contracts\HasKeyResultAncestors;
use Platform\Core\Contracts\InheritsExtraFields;
use Platform\Core\Support\PageContextState;

/**
 * Seitenkontext für Terminal + Kontext-Panel — ein Weg statt 6–8 Einzel-Events.
 *
 * Die Seite sagt nur, welches Modell sie zeigt; Subject, Beschreibung und die
 * verfügbaren Apps (Aktivität, Dateien, Tags, Zeiten, OKR, Zusatzfelder,
 * Organisation) werden aus dem Modell abgeleitet. Liefert pageContext() null
 * (Liste/Dashboard), wird der Kontext ausdrücklich zurückgesetzt.
 *
 *   use HasPageContext;
 *
 *   protected function pageContext(): ?Model
 *   {
 *       return $this->project;
 *   }
 *
 *   // optional — nur was vom Ableiten abweicht:
 *   protected function pageContextOptions(): array
 *   {
 *       return [
 *           'url' => route('planner.projects.show', $this->project),
 *           'source' => 'planner.project.view',
 *           'apps' => ['files' => false],   // abschalten / erzwingen
 *       ];
 *   }
 *
 * Gesendet wird `page-context` (neu, für das Kontext-Panel) und — solange die
 * Empfänger noch nicht umgestellt sind — die bisherigen Events (`comms`,
 * `terminal:app:*`, `organization`, `keyresult`, `extrafields`) mit unverändertem
 * Payload-Format. Am Terminal ändert sich dadurch nichts.
 *
 * Hat die Komponente ein eigenes rendered(), dort dispatchPageContext() aufrufen.
 * Konvention: ui-tailwind/docs/module-layout.md
 */
trait HasPageContext
{
    /** Das Modell, um das es auf dieser Seite geht — null auf Listen/Dashboards. */
    abstract protected function pageContext(): ?Model;

    /**
     * Abweichungen vom Abgeleiteten. Schlüssel: subject, description, url, source,
     * recipients, capabilities, meta, apps (['app' => bool]), extrafields_context (Model|null).
     */
    protected function pageContextOptions(): array
    {
        return [];
    }

    public function rendered(): void
    {
        $this->dispatchPageContext();
    }

    protected function dispatchPageContext(): void
    {
        $model = $this->pageContext();

        if (! $model || ! $model->getKey()) {
            $source = $this->pageContextOptions()['source'] ?? null;
            app(PageContextState::class)->payload = null;
            $this->dispatch('page-context', ['model' => null, 'modelId' => null, 'source' => $source]);
            $this->dispatch('comms', ['model' => null, 'modelId' => null, 'source' => $source]);

            return;
        }

        $options = $this->pageContextOptions();
        $apps = array_merge(static::pageContextAppsFor($model), $options['apps'] ?? []);
        $context = [
            'model' => get_class($model),
            'modelId' => $model->getKey(),
            'subject' => $options['subject'] ?? static::pageContextSubjectFor($model),
            'description' => $options['description'] ?? ($model->description ?? ''),
            'url' => $options['url'] ?? null,
            'source' => $options['source'] ?? null,
        ];

        $efTarget = ! empty($apps['extrafields'])
            ? (array_key_exists('extrafields_context', $options) ? $options['extrafields_context'] : static::pageContextExtraFieldsTarget($model))
            : null;

        $pageContext = $context + [
            'apps' => array_keys(array_filter($apps)),
            'extrafields' => $efTarget ? ['type' => get_class($efTarget), 'id' => $efTarget->getKey()] : null,
        ];
        // Für das Kontext-Panel im Layout (gleicher Request) — Event für spätere Updates
        app(PageContextState::class)->payload = $pageContext;
        $this->dispatch('page-context', $pageContext);

        // ── Bisherige Events (Terminal, Key-Result-/Zusatzfeld-Modal) ──
        // comms zuerst: das Terminal setzt beim Kontextwechsel seine Tabs zurück.
        $this->dispatch('comms', $context + [
            'recipients' => $options['recipients'] ?? [],
            'capabilities' => $options['capabilities'] ?? ['manage_channels' => false, 'threads' => true],
            'meta' => $options['meta'] ?? [],
        ]);

        $ref = ['context_type' => get_class($model), 'context_id' => $model->getKey()];

        foreach (['activity', 'files', 'tags'] as $app) {
            if (! empty($apps[$app])) {
                $this->dispatch('terminal:app:' . $app);
            }
        }
        if (! empty($apps['time'])) {
            $this->dispatch('organization', $ref + ['allow_time_entry' => true]);
        }
        if (! empty($apps['okr'])) {
            $this->dispatch('keyresult', $ref);
        }
        if ($efTarget) {
            $this->dispatch('extrafields', ['context_type' => get_class($efTarget), 'context_id' => $efTarget->getKey()]);
        }
    }

    /**
     * Apps, die das Modell von sich aus kann. Trait-Namen als Strings, damit der
     * Core nicht von organization/activity-log abhängt.
     */
    protected static function pageContextAppsFor(Model $model): array
    {
        $traits = class_uses_recursive($model);

        return [
            'activity' => isset($traits['Platform\ActivityLog\Traits\LogsActivity']),
            'files' => true, // Kontext-Dateien sind polymorph, gehen an jedem Modell
            'tags' => isset($traits['Platform\Core\Traits\HasTags']),
            'time' => isset($traits['Platform\Organization\Traits\HasTimeEntries']),
            'okr' => $model instanceof HasKeyResultAncestors,
            'extrafields' => isset($traits['Platform\Core\Traits\HasExtraFields']),
            'entities' => isset($traits['Platform\Core\Models\Concerns\HasEntityLinks']),
        ];
    }

    protected static function pageContextSubjectFor(Model $model): ?string
    {
        if ($model instanceof HasDisplayName && ($name = $model->getDisplayName())) {
            return $name;
        }

        return $model->name ?? $model->title ?? null;
    }

    /** Zusatzfeld-Definitionen hängen bei erbenden Modellen (z. B. Task) am Eltern-Modell. */
    protected static function pageContextExtraFieldsTarget(Model $model): ?Model
    {
        if ($model instanceof InheritsExtraFields) {
            return $model->extraFieldParents()[0] ?? null;
        }

        return $model;
    }
}
