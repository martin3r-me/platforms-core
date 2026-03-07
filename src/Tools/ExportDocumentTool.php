<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Jobs\RenderDocumentJob;
use Platform\Core\Models\Document;

class ExportDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.EXPORT';
    }

    public function getDescription(): string
    {
        return 'Rendert ein bestehendes Dokument als PDF (oder re-rendert es nach Datenänderung). '
            . 'Rendering ist asynchron — nutze danach core.documents.GET um Status und Download-URL zu prüfen. '
            . 'Optional: renderer_options um Papierformat, Ränder oder Querformat zu setzen.';
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
                'renderer_options' => [
                    'type' => 'object',
                    'description' => 'Optional: PDF-Optionen (format, landscape, margin_top/bottom/left/right)',
                    'properties' => [
                        'format' => ['type' => 'string', 'description' => 'A4 (default), A3, Letter, Legal'],
                        'landscape' => ['type' => 'boolean', 'description' => 'Querformat'],
                        'margin_top' => ['type' => 'integer'],
                        'margin_bottom' => ['type' => 'integer'],
                        'margin_left' => ['type' => 'integer'],
                        'margin_right' => ['type' => 'integer'],
                    ],
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
            $rendererOptions = $arguments['renderer_options'] ?? [];

            $document = Document::where('id', $documentId)
                ->where('team_id', $team->id)
                ->first();

            if (!$document) {
                return ToolResult::error('Dokument nicht gefunden oder kein Zugriff.', 'NOT_FOUND');
            }

            // Dispatch async rendering job
            RenderDocumentJob::dispatch($document->id, $context->user->id, $rendererOptions);

            return ToolResult::success([
                'id' => $document->id,
                'title' => $document->title,
                'status' => 'rendering',
                'hint' => 'PDF wird asynchron gerendert. Nutze core.documents.GET mit document_id um Status und Download-URL abzurufen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Export: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
