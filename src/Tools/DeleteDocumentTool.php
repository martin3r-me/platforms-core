<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;
use Platform\Core\Services\ContextFileService;

class DeleteDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht ein Dokument und dessen generierte PDF-Datei. '
            . 'Entfernt das Dokument, alle Exports und die zugehörige ContextFile (PDF) vom Storage. '
            . 'Diese Aktion ist nicht umkehrbar.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Dokuments das gelöscht werden soll',
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

            $document = Document::with(['exports'])
                ->where('id', $documentId)
                ->where('team_id', $team->id)
                ->first();

            if (!$document) {
                return ToolResult::error('Dokument nicht gefunden oder kein Zugriff.', 'NOT_FOUND');
            }

            $title = $document->title;

            // Delete output ContextFile (PDF) if exists
            if ($document->output_context_file_id) {
                try {
                    $service = app(ContextFileService::class);
                    $service->delete($document->output_context_file_id, $team->id);
                } catch (\Throwable $e) {
                    // Continue even if file deletion fails
                }
            }

            // Delete export ContextFiles
            foreach ($document->exports as $export) {
                if ($export->output_context_file_id && $export->output_context_file_id !== $document->output_context_file_id) {
                    try {
                        $service = app(ContextFileService::class);
                        $service->delete($export->output_context_file_id, $team->id);
                    } catch (\Throwable $e) {
                        // Continue
                    }
                }
            }

            // Delete document (cascades to exports via FK)
            $document->delete();

            return ToolResult::success([
                'deleted' => true,
                'title' => $title,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
