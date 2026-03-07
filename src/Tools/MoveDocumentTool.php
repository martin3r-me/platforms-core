<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;
use Platform\Core\Models\DocumentFolder;

class MoveDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.MOVE';
    }

    public function getDescription(): string
    {
        return 'Verschiebt ein oder mehrere Dokumente in einen Ordner oder zurück auf den Schreibtisch. '
            . 'folder_id: null/0 = Schreibtisch (unsortiert). '
            . 'Unterstützt Einzel- und Bulk-Operationen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'IDs der Dokumente die verschoben werden sollen',
                ],
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Ziel-Ordner-ID. null oder 0 = Schreibtisch (kein Ordner).',
                ],
            ],
            'required' => ['document_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $documentIds = $arguments['document_ids'] ?? [];
            if (empty($documentIds)) {
                return ToolResult::error('document_ids darf nicht leer sein.', 'VALIDATION_ERROR');
            }

            $folderId = ($arguments['folder_id'] ?? null) ?: null;

            // Validate target folder
            if ($folderId) {
                $folder = DocumentFolder::where('id', $folderId)
                    ->where('team_id', $team->id)
                    ->first();

                if (!$folder) {
                    return ToolResult::error('Ziel-Ordner nicht gefunden.', 'NOT_FOUND');
                }
            }

            // Move documents
            $moved = Document::where('team_id', $team->id)
                ->whereIn('id', $documentIds)
                ->update(['document_folder_id' => $folderId]);

            $target = $folderId
                ? DocumentFolder::find($folderId)->name
                : 'Schreibtisch';

            return ToolResult::success([
                'moved' => $moved,
                'target' => $target,
                'folder_id' => $folderId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
