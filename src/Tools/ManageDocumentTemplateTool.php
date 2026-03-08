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
            . '## So funktioniert es '
            . "\n"
            . 'Du lieferst nur den BODY-HTML in "content" (Blade-Syntax). '
            . 'Das System wrappt automatisch mit Base-Layout: CSS für Tabellen, Typografie, Print, KPI-Cards, Brief-Layout. '
            . "\n\n"
            . '## Verfügbare Felder '
            . "\n"
            . '- content: BODY-HTML (Blade). Wichtigstes Feld! Nutze {!! $html_content !!} für den Haupt-Body. '
            . "\n"
            . '- styles: Eigenes CSS (wird im <head> eingefügt). Basis-CSS ist immer da (table, h1-h3, .kpi-grid, .page-break, etc.). '
            . "\n"
            . '- schema: JSON-Schema der Template-Daten — hilft der LLM beim Ausfüllen. '
            . "\n"
            . '- default_data: Standard-Werte die automatisch gemergt werden. '
            . "\n"
            . '- header_html: HTML für den Dokumenten-Header (z.B. Logo, Firmenname). '
            . "\n"
            . '- footer_html: HTML für den Dokumenten-Footer (z.B. Seitenzahl, Disclaimer). '
            . "\n\n"
            . '## Beispiel '
            . "\n"
            . 'action: "create", key: "meeting-protocol", name: "Protokoll", '
            . 'content: "<h2>{{ $meeting_title }}</h2>{!! $html_content !!}", '
            . 'styles: ".meta { color: #666; }", '
            . 'schema: { properties: { html_content: { type: "string" }, meeting_title: { type: "string" } }, required: ["html_content"] } '
            . "\n\n"
            . 'Mit action: "delete" kann ein Team-Template gelöscht werden (System-Defaults nicht löschbar).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Aktion: "create", "update", "delete". Standard: "create".',
                ],
                'template_id' => [
                    'type' => 'integer',
                    'description' => 'Für update/delete: ID des Templates.',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Template-Key (z.B. "invoice", "protocol"). Bei create required. Unique pro Team.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Anzeigename (z.B. "Rechnung", "Protokoll").',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Beschreibung — was das Template tut, welche Felder es erwartet.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'BODY-HTML (Blade-Syntax). Nur der Body — Layout+CSS wird automatisch gewrappt. Nutze {!! $html_content !!} für raw HTML, {{ $var }} für escaped, @if/@foreach für Logik.',
                ],
                'styles' => [
                    'type' => 'string',
                    'description' => 'Eigenes CSS das im <head> eingefügt wird. Basis-CSS ist immer da: table, th/td, h1-h3, p, .kpi-grid/.kpi-card, .page-break, .text-right, .bold, .sender-block, .recipient-block, .closing-block, .signature-block.',
                ],
                'header_html' => [
                    'type' => 'string',
                    'description' => 'HTML für den Dokumenten-Header (z.B. Logo, Firmenname). Wird von Browsershot als Header auf jeder Seite gedruckt.',
                ],
                'footer_html' => [
                    'type' => 'string',
                    'description' => 'HTML für den Dokumenten-Footer (z.B. Seitenzahl, Disclaimer). Wird von Browsershot als Footer auf jeder Seite gedruckt.',
                ],
                'schema' => [
                    'type' => 'object',
                    'description' => 'JSON-Schema der Template-Daten: { properties: { field: { type, description } }, required: [...] }. Hilft der LLM die richtigen Daten zu liefern.',
                ],
                'default_data' => [
                    'type' => 'object',
                    'description' => 'Standard-Daten die automatisch gemergt werden wenn ein Dokument erstellt wird (z.B. { closing: "Mit freundlichen Grüßen" }).',
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Weitere Metadaten: paper config (format, margins). Styles/header/footer können auch direkt als Top-Level-Parameter übergeben werden.',
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

            // Merge top-level shortcuts into meta
            $arguments = $this->mergeShortcutsIntoMeta($arguments);

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

    /**
     * Merge top-level shortcut fields (styles, header_html, footer_html) into meta.
     */
    private function mergeShortcutsIntoMeta(array $arguments): array
    {
        $shortcuts = ['styles', 'header_html', 'footer_html'];
        $meta = $arguments['meta'] ?? [];

        foreach ($shortcuts as $key) {
            if (array_key_exists($key, $arguments) && $arguments[$key] !== null) {
                $meta[$key] = $arguments[$key];
                unset($arguments[$key]);
            }
        }

        if (!empty($meta)) {
            $arguments['meta'] = $meta;
        }

        return $arguments;
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
            'has_content' => !empty($template->content),
            'has_styles' => !empty($template->meta['styles'] ?? null),
            'has_schema' => !empty($template->schema),
            'has_header' => !empty($template->meta['header_html'] ?? null),
            'has_footer' => !empty($template->meta['footer_html'] ?? null),
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
            return ToolResult::error(
                'Template nicht gefunden oder kein Zugriff. '
                . 'System-Defaults (team_id=null) können nicht direkt bearbeitet werden — '
                . 'erstelle stattdessen ein Team-Template mit dem gleichen Key (action: "create"), '
                . 'das überschreibt automatisch den System-Default.',
                'NOT_FOUND'
            );
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
            return ToolResult::error(
                'Keine erkannten Änderungen. Erlaubte Felder: content, styles, header_html, footer_html, schema, default_data, name, description, meta.',
                'NO_CHANGES'
            );
        }

        $template->update($updates);
        $template->refresh();

        return ToolResult::success([
            'id' => $template->id,
            'key' => $template->key,
            'name' => $template->name,
            'changed' => $changed,
            'has_content' => !empty($template->content),
            'has_styles' => !empty($template->meta['styles'] ?? null),
            'has_schema' => !empty($template->schema),
            'has_header' => !empty($template->meta['header_html'] ?? null),
            'has_footer' => !empty($template->meta['footer_html'] ?? null),
            'action' => 'updated',
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
