<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ChunkedUploadService;

/**
 * Startet einen Chunked Upload (Schritt 1 von 3: start → chunk → complete).
 */
class StartContextFileUploadTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.upload.start';
    }

    public function getDescription(): string
    {
        return 'Startet einen Chunked Upload für große Dateien an ein Context-Objekt und liefert eine upload_id. '
            . 'Danach Teile mit core.context.files.upload.chunk senden und mit core.context.files.upload.complete abschließen. '
            . 'Unvollständige Uploads verfallen nach 1 Stunde.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'context_type' => ['type' => 'string', 'description' => 'Vollqualifizierter Model-Klassenname, z.B. "Platform\Planner\Models\PlannerTask"'],
                'context_id' => ['type' => 'integer', 'description' => 'ID des Context-Objekts'],
                'file_name' => ['type' => 'string', 'description' => 'Dateiname inkl. Endung'],
                'mime_type' => ['type' => 'string', 'description' => 'Optional: MIME-Typ (sonst aus Endung)'],
                'total_size' => ['type' => 'integer', 'description' => 'Gesamtgröße der Datei in Bytes (max. 10MB)'],
                'sha256' => ['type' => 'string', 'description' => 'Optional: SHA-256 (hex) der Gesamtdatei, wird beim Abschluss geprüft'],
            ],
            'required' => ['context_type', 'context_id', 'file_name', 'total_size'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $contextType = $arguments['context_type'] ?? null;
            $contextId = (int) ($arguments['context_id'] ?? 0);
            $fileName = $arguments['file_name'] ?? null;
            $totalSize = (int) ($arguments['total_size'] ?? 0);
            $sha256 = isset($arguments['sha256']) ? strtolower((string) $arguments['sha256']) : null;

            if (!$contextType || !$contextId || !$fileName || $totalSize <= 0) {
                return ToolResult::error('context_type, context_id, file_name und total_size (>0) sind erforderlich', 'VALIDATION_ERROR');
            }
            if ($totalSize > ChunkedUploadService::MAX_TOTAL_SIZE) {
                return ToolResult::error('Datei ist zu groß (max. 10MB)', 'FILE_TOO_LARGE');
            }
            if ($sha256 !== null && !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
                return ToolResult::error('sha256 muss ein 64-stelliger Hex-String sein', 'VALIDATION_ERROR');
            }
            if (!class_exists($contextType) || !is_subclass_of($contextType, Model::class)) {
                return ToolResult::error("context_type '{$contextType}' ist kein gültiges Model.", 'INVALID_CONTEXT_TYPE');
            }

            $contextModel = $contextType::find($contextId);
            if (!$contextModel) {
                return ToolResult::error("Kein {$contextType} mit ID {$contextId} gefunden.", 'CONTEXT_NOT_FOUND');
            }
            if (isset($contextModel->team_id) && (int) $contextModel->team_id !== (int) $context->team->id) {
                return ToolResult::error('Das Context-Objekt gehört nicht zum aktuellen Team.', 'ACCESS_DENIED');
            }

            $uploadId = app(ChunkedUploadService::class)->start([
                'user_id' => $context->user->id,
                'team_id' => $context->team->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
                'file_name' => $fileName,
                'mime_type' => $arguments['mime_type'] ?? null,
                'total_size' => $totalSize,
                'sha256' => $sha256,
            ]);

            return ToolResult::success([
                'upload_id' => $uploadId,
                'max_chunk_bytes' => ChunkedUploadService::MAX_CHUNK_SIZE,
                'expires_in_seconds' => ChunkedUploadService::TTL_SECONDS,
                'hint' => 'Sende die Datei in base64-Teilen (empfohlen 20–30 KB Rohdaten je Teil) mit core.context.files.upload.chunk (index ab 0), dann core.context.files.upload.complete.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Starten des Uploads: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
