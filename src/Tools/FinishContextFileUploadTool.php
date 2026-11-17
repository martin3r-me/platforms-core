<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFileUpload;
use Platform\Core\Services\DirectUploadService;

/**
 * Direkt-Upload Schritt 3: prüft Größe/Checksumme, legt den ContextFile-Eintrag an, liefert file_id.
 */
class FinishContextFileUploadTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.upload.finish';
    }

    public function getDescription(): string
    {
        return 'Schließt einen Direkt-Upload ab (nach HTTP PUT an upload_url aus core.context.files.upload.request): '
            . 'prüft Größe/sha256, legt das Context-File an und liefert file_id. Mit Kontext aus upload.request wird direkt verknüpft.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'upload_id' => ['type' => 'string', 'description' => 'upload_id aus upload.request'],
            ],
            'required' => ['upload_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $upload = ContextFileUpload::where('uuid', (string) ($arguments['upload_id'] ?? ''))
                ->where('user_id', $context->user->id)
                ->where('team_id', $context->team->id ?? 0)
                ->first();
            if (!$upload) {
                return ToolResult::error('Upload nicht gefunden oder abgelaufen.', 'UPLOAD_NOT_FOUND');
            }
            if ($upload->status === 'pending') {
                return ToolResult::error('Noch keine Datei empfangen — erst per HTTP PUT an upload_url senden.', 'NOT_UPLOADED');
            }
            if ($upload->status === 'completed') {
                return ToolResult::error('Upload wurde bereits abgeschlossen.', 'ALREADY_COMPLETED');
            }

            try {
                $file = app(DirectUploadService::class)->complete($upload);
            } catch (\RuntimeException $e) {
                if (in_array($e->getMessage(), ['SIZE_MISMATCH', 'CHECKSUM_MISMATCH'], true)) {
                    return ToolResult::error($e->getMessage() === 'SIZE_MISMATCH' ? 'Größe stimmt nicht.' : 'Checksumme (sha256) stimmt nicht überein.', $e->getMessage());
                }
                throw $e;
            }

            return ToolResult::success([
                'file_id' => $file->id,
                'token' => $file->token,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
                'context_type' => $file->context_type,
                'context_id' => $file->context_id,
                'url' => $file->url,
                'attached' => (bool) $file->context_type,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler bei upload.finish: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
