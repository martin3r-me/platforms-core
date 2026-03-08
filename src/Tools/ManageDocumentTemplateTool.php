<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\DocumentTemplate;

class ManageDocumentTemplateTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.templates.MANAGE';
    }

    public function getDescription(): string
    {
        return 'Erstellt oder aktualisiert ein Dokument-Template für das aktuelle Team. '
            . 'Team-Templates überschreiben System-Defaults mit dem gleichen Key. '
            . "\n\n"
            . 'CONTENT: Du lieferst nur den BODY-HTML (Blade-Syntax). '
            . 'Das System wrappt automatisch mit Base-Layout (CSS für Tabellen, Typografie, Print, KPI-Cards). '
            . 'Variablen: {{ $var }} (escaped), {!! $var !!} (raw HTML), @if/@foreach etc. '
            . 'Standard-Variable ist immer $html_content — der Haupt-Body den die LLM beim Erstellen generiert. '
            . "\n\n"
            . 'STYLES: Eigene CSS-Klassen in meta.styles ablegen — werden automatisch im <head> eingefügt. '
            . 'Basis-CSS ist immer da: table, th/td, h1-h3, p, .kpi-grid/.kpi-card, .page-break, .text-right, .bold, etc. '
            . "\n\n"
            . 'BEISPIEL content: "@if(!empty($kunde))<p>Kunde: {{ $kunde }}</p>@endif<div class=\"content-body\">{!! $html_content !!}</div>" '
            . "\n\n"
            . 'Mit action: "delete" kann ein Team-Template gelöscht werden (System-Defaults sind nicht löschbar).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Aktion: "create" (neues Template), "update" (bestehendes ändern), "delete" (Team-Template löschen). Standard: "create".',
                ],
                'template_id' => [
                    'type' => 'integer',
                    'description' => 'Für update/delete: ID des Templates',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Template-Key (z.B. "invoice", "offer"). Bei create required. Muss unique pro Team sein.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Anzeigename (z.B. "Rechnung", "Angebot")',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Beschreibung des Templates und seiner Felder',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Nur BODY-HTML (Blade-Syntax). Das Base-Layout (CSS, Doctype, Print-Styles) wird automatisch drum herum gewrappt. Nutze {!! $html_content !!} für den Haupt-Body, {{ $var }} für escaped Variablen, @if/@foreach für Logik.',
                ],
                'schema' => [
                    'type' => 'object',
                    'description' => 'JSON-Schema für die Template-Daten (properties, required). Hilft der LLM beim Ausfüllen.',
                ],
                'default_data' => [
                    'type' => 'object',
                    'description' => 'Standard-Daten die automatisch gemergt werden (z.B. closing: "Mit freundlichen Grüßen")',
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Metadaten: paper config (format, margins), header_html, footer_html',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $action = $arguments['action'] ?? 'create';

            return match ($action) {
                'create' => $this->createTemplate($arguments, $team->id, $context->user->id),
                'update' => $this->updateTemplate($arguments, $team->id),
                'delete' => $this->deleteTemplate($arguments, $team->id),
                default => ToolResult::error("Unbekannte Aktion: {$action}. Erlaubt: create, update, delete.", 'VALIDATION_ERROR'),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function createTemplate(array $arguments, int $teamId, int $userId): ToolResult
    {
        if (empty($arguments['key'])) {
            return ToolResult::error('key ist required für create.', 'VALIDATION_ERROR');
        }
        if (empty($arguments['name'])) {
            return ToolResult::error('name ist required für create.', 'VALIDATION_ERROR');
        }

        // Check uniqueness
        $exists = DocumentTemplate::where('team_id', $teamId)
            ->where('key', $arguments['key'])
            ->exists();

        if ($exists) {
            return ToolResult::error("Template mit key '{$arguments['key']}' existiert bereits für dieses Team. Nutze action: 'update'.", 'DUPLICATE');
        }

        $template = DocumentTemplate::create([
            'team_id' => $teamId,
            'key' => $arguments['key'],
            'name' => $arguments['name'],
            'description' => $arguments['description'] ?? null,
            'content' => $arguments['content'] ?? null,
            'schema' => $arguments['schema'] ?? null,
            'default_data' => $arguments['default_data'] ?? null,
            'meta' => $arguments['meta'] ?? null,
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);

        return ToolResult::success([
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'action' => 'created',
            'hint' => "Template erstellt. Nutze core.documents.CREATE mit template_key: '{$template->key}' um Dokumente damit zu erstellen.",
        ]);
    }

    private function updateTemplate(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['template_id'])) {
            return ToolResult::error('template_id ist required für update.', 'VALIDATION_ERROR');
        }

        $template = DocumentTemplate::where('id', (int) $arguments['template_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$template) {
            return ToolResult::error('Template nicht gefunden oder kein Zugriff. System-Defaults können nicht bearbeitet werden — erstelle stattdessen ein Team-Override mit dem gleichen Key.', 'NOT_FOUND');
        }

        $updates = [];
        $changed = [];

        foreach (['name', 'description', 'content', 'schema', 'default_data', 'meta'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $updates[$field] = $arguments[$field];
                $changed[] = $field;
            }
        }

        if (empty($updates)) {
            return ToolResult::error('Keine Änderungen übergeben.', 'NO_CHANGES');
        }

        $template->update($updates);

        return ToolResult::success([
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'action' => 'updated',
            'changed' => $changed,
        ]);
    }

    private function deleteTemplate(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['template_id'])) {
            return ToolResult::error('template_id ist required für delete.', 'VALIDATION_ERROR');
        }

        $template = DocumentTemplate::where('id', (int) $arguments['template_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$template) {
            return ToolResult::error('Template nicht gefunden oder kein Zugriff. System-Defaults (team_id=null) können nicht gelöscht werden.', 'NOT_FOUND');
        }

        $key = $template->key;
        $template->delete();

        return ToolResult::success([
            'action' => 'deleted',
            'key' => $key,
        ]);
    }
}
