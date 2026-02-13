<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;

/**
 * Tool zum Löschen von Context-Dateien
 *
 * Ermöglicht der LLM, Dateien aus einem Context zu entfernen.
 */
class DeleteContextFileTool implements ToolContract
{
    public function __construct(
        protected ContextFileService $contextFileService
    ) {}

    public function getName(): string
    {
        return 'core.context.files.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht eine Datei aus einem Context. Die Datei und alle zugehörigen Varianten werden '
            . 'permanent entfernt. Nutze core.context.files.GET um die file_id zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file_id' => [
                    'type' => 'integer',
                    'description' => 'Die ID der zu löschenden Datei (aus core.context.files.GET)',
                ],
            ],
            'required' => ['file_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $fileId = $arguments['file_id'] ?? null;
            if (!$fileId) {
                return ToolResult::error('file_id ist erforderlich', 'VALIDATION_ERROR');
            }

            // Find file with team scope
            $file = ContextFile::query()
                ->where('id', (int) $fileId)
                ->where('team_id', $team->id)
                ->first();

            if (!$file) {
                return ToolResult::error('Datei nicht gefunden oder keine Berechtigung', 'NOT_FOUND');
            }

            // Store info for response before deletion
            $fileInfo = [
                'id' => $file->id,
                'name' => $file->original_name,
                'context_type' => $file->context_type,
                'context_id' => $file->context_id,
            ];

            // Delete using service (handles variants and storage)
            $this->contextFileService->delete($file->id);

            return ToolResult::success([
                'deleted' => true,
                'file' => $fileInfo,
                'message' => "Datei '{$fileInfo['name']}' wurde erfolgreich gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Löschen der Datei: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
