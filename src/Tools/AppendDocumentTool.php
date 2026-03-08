<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;

class AppendDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.APPEND';
    }

    public function getDescription(): string
    {
        return 'Hängt HTML-Content an das Ende von html_content eines Dokuments an — ohne den bestehenden Inhalt zu überschreiben. '
            . 'Ideal für: neue Abschnitte, Anhänge, Tabellen ergänzen. '
            . 'Spart Tokens, da nur der neue Content übertragen wird. '
            . 'Nach dem Anhängen: core.documents.EXPORT zum Neu-Rendern.';
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
                'html' => [
                    'type' => 'string',
                    'description' => 'HTML-Content der ans Ende von html_content angehängt wird. Beispiel: "<h2>Neuer Abschnitt</h2><p>Inhalt...</p>"',
                ],
                'separator' => [
                    'type' => 'string',
                    'description' => 'Optional: HTML-Trenner vor dem neuen Content. Standard: "" (kein Trenner). Beispiel: "<hr>" oder "<div class=\"page-break\"></div>"',
                ],
            ],
            'required' => ['document_id', 'html'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $document = Document::where('id', (int) $arguments['document_id'])
                ->where('team_id', $team->id)
                ->first();

            if (!$document) {
                return ToolResult::error('Dokument nicht gefunden oder kein Zugriff.', 'NOT_FOUND');
            }

            $data = $document->data ?? [];
            $existing = $data['html_content'] ?? '';
            $separator = $arguments['separator'] ?? '';
            $newHtml = $arguments['html'];

            $data['html_content'] = $existing . $separator . $newHtml;
            $document->update(['data' => $data]);

            return ToolResult::success([
                'id' => $document->id,
                'title' => $document->title,
                'content_length' => mb_strlen($data['html_content']),
                'appended_length' => mb_strlen($newHtml),
                'hint' => 'Content angehängt. Nutze core.documents.EXPORT um das PDF neu zu rendern.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Anhängen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
