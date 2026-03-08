<?php

namespace Platform\Core\Services\Documents;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Platform\Core\Models\DocumentTemplate;

class DocumentTemplateRegistry
{
    /**
     * Resolve a template by key for a given team.
     *
     * Priority: DB team override → DB system default → Blade fallback
     */
    public function resolve(string $key, ?int $teamId): ?DocumentTemplate
    {
        // 1. Team override (DB)
        if ($teamId !== null) {
            $teamTemplate = DocumentTemplate::active()
                ->where('team_id', $teamId)
                ->where('key', $key)
                ->first();

            if ($teamTemplate) {
                return $teamTemplate;
            }
        }

        // 2. System default (DB, team_id = NULL)
        $systemTemplate = DocumentTemplate::active()
            ->whereNull('team_id')
            ->where('key', $key)
            ->first();

        if ($systemTemplate) {
            return $systemTemplate;
        }

        // 3. Blade fallback – check if a blade view exists for this key
        $bladeView = "platform::documents.{$key}";
        if (View::exists($bladeView)) {
            // Create a virtual (non-persisted) template representing the blade view
            $template = new DocumentTemplate();
            $template->key = $key;
            $template->name = $key;
            $template->blade_view = $bladeView;
            $template->is_active = true;

            return $template;
        }

        return null;
    }

    /**
     * List all available templates for a team (merged: team overrides win by key).
     */
    public function listForTeam(int $teamId): Collection
    {
        $templates = DocumentTemplate::active()
            ->forTeam($teamId)
            ->orderBy('key')
            ->get();

        // Group by key, team override wins over system default
        return $templates->groupBy('key')->map(function (Collection $group) {
            // Prefer the team-specific template (team_id != null)
            return $group->sortByDesc('team_id')->first();
        })->values();
    }

    /**
     * Render a template to HTML with the given data.
     *
     * DB content templates: body is rendered via Blade::render(), then auto-wrapped
     * in the base layout (_base.blade.php) with all CSS (typography, tables, print).
     * The LLM only needs to provide body HTML — no full document structure needed.
     *
     * Blade view templates: rendered directly via View::make() (legacy/fallback).
     */
    public function renderToHtml(DocumentTemplate $template, array $data): string
    {
        // Merge default_data with provided data (provided wins)
        $mergedData = array_merge($template->default_data ?? [], $data);

        // DB content (team override or system default with content)
        if ($template->content) {
            // Render the body template (Blade syntax: {{ $var }}, {!! $html !!}, @if etc.)
            $bodyHtml = Blade::render($template->content, $mergedData);

            // Extract custom styles from template meta
            $customStyles = $template->meta['styles'] ?? '';

            // Wrap in base layout (CSS, print styles, document structure)
            return $this->wrapInLayout($bodyHtml, $mergedData['title'] ?? 'Dokument', $customStyles);
        }

        // Blade view (system templates / legacy fallback)
        if ($template->blade_view) {
            return View::make($template->blade_view, $mergedData)->render();
        }

        throw new \RuntimeException("Template '{$template->key}' has neither content nor blade_view.");
    }

    /**
     * Wrap body HTML in the base document layout.
     * Provides: doctype, charset, print CSS, table styles, typography, utility classes.
     */
    protected function wrapInLayout(string $bodyHtml, string $title, string $customStyles = ''): string
    {
        return View::make('platform::documents._base', [
            'title' => $title,
            'bodyHtml' => $bodyHtml,
            'customStyles' => $customStyles,
        ])->render();
    }
}
