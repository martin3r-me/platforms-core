<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;

class UpdateDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.UPDATE';
    }

    public function getDescription(): string
    {
        return 'Aktualisiert ein bestehendes Dokument. '
            . 'data wird GEMERGT (nicht ersetzt): Übergib nur die geänderten Keys, z.B. data:{subtitle:"Neu"} — html_content bleibt unangetastet. '
            . 'Für gezielte Textänderungen innerhalb von html_content: Nutze core.documents.PATCH (search/replace) statt den gesamten Content hier zu übergeben. '
            . 'Für Anhänge an html_content: Nutze core.documents.APPEND. '
            . 'Nach data-Änderung: core.documents.EXPORT zum Neu-Rendern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Dokuments',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Neuer Titel',
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Template-Daten (wird mit bestehenden Daten GEMERGT). Nur geänderte Keys übergeben — Rest bleibt erhalten. Beispiel: {subtitle:"Neu"} ändert nur subtitle, html_content bleibt.',
                ],
                'template_key' => [
                    'type' => 'string',
                    'description' => 'Anderes Template zuweisen (z.B. von "report" auf "table-report" wechseln)',
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Metadaten aktualisieren',
                ],
            ],
            'required' => ['document_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $documentId = (int) $arguments['document_id'];

            $document = Document::where('id', $documentId)
                ->where('team_id', $team->id)
                ->first();

            if (!$document) {
                return ToolResult::error('Dokument nicht gefunden oder kein Zugriff.', 'NOT_FOUND');
            }

            $updates = [];
            $changed = [];

            if (array_key_exists('title', $arguments)) {
                $updates['title'] = $arguments['title'];
                $changed[] = 'title';
            }

            if (array_key_exists('data', $arguments)) {
                $existing = $document->data ?? [];
                $updates['data'] = array_merge($existing, $arguments['data']);
                $changed[] = 'data';
            }

            if (array_key_exists('template_key', $arguments)) {
                $updates['template_key'] = $arguments['template_key'];
                $changed[] = 'template_key';
            }

            if (array_key_exists('meta', $arguments)) {
                $updates['meta'] = $arguments['meta'];
                $changed[] = 'meta';
            }

            if (empty($updates)) {
                return ToolResult::error('Keine Änderungen übergeben.', 'NO_CHANGES');
            }

            $document->update($updates);

            $result = [
                'id' => $document->id,
                'title' => $document->title,
                'template_key' => $document->template_key,
                'status' => $document->status,
                'changed' => $changed,
                'share_url' => $document->share_url,
            ];

            // Hint to re-render if data changed
            if (in_array('data', $changed) || in_array('template_key', $changed)) {
                $result['hint'] = 'Daten/Template geändert. Nutze core.documents.EXPORT um das PDF neu zu rendern.';
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Aktualisieren: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
