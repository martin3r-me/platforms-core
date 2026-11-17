<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ChunkedUploadService;

/**
 * Schließt einen Chunked Upload ab (Schritt 3 von 3): setzt zusammen, prüft Größe/Checksumme
 * und legt die Datei über core.context.files.CREATE als Context-File an.
 */
class CompleteContextFileUploadTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.upload.complete';
    }

    public function getDescription(): string
    {
        return 'Schließt einen Chunked Upload ab: setzt alle Teile zusammen, prüft Gesamtgröße (und sha256, falls angegeben) '
            . 'und legt die Datei als Context-File an. Bei Fehlern in der Prüfung bleibt der Upload bestehen, fehlende Teile können nachgesendet werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'upload_id' => ['type' => 'string', 'description' => 'upload_id aus upload.start'],
            ],
            'required' => ['upload_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $service = app(ChunkedUploadService::class);
            $uploadId = (string) ($arguments['upload_id'] ?? '');
            $meta = $service->meta($uploadId);

            if (!$meta || (int) $meta['user_id'] !== (int) $context->user->id || (int) $meta['team_id'] !== (int) ($context->team->id ?? 0)) {
                return ToolResult::error('Upload nicht gefunden oder abgelaufen.', 'UPLOAD_NOT_FOUND');
            }

            $indexes = $service->chunkIndexes($uploadId);
            if ($indexes === []) {
                return ToolResult::error('Keine Teile empfangen.', 'NO_CHUNKS');
            }
            $missing = array_values(array_diff(range(0, max($indexes)), $indexes));
            if ($missing !== []) {
                return ToolResult::error('Teile fehlen: index ' . implode(', ', array_slice($missing, 0, 20)), 'MISSING_CHUNKS');
            }

            $content = $service->assemble($uploadId);
            if (strlen($content) !== (int) $meta['total_size']) {
                return ToolResult::error(
                    'Größe stimmt nicht: erwartet ' . $meta['total_size'] . ' Bytes, empfangen ' . strlen($content) . '. Fehlende/überzählige Teile prüfen.',
                    'SIZE_MISMATCH'
                );
            }
            if (!empty($meta['sha256']) && !hash_equals($meta['sha256'], hash('sha256', $content))) {
                return ToolResult::error('Checksumme (sha256) stimmt nicht überein.', 'CHECKSUM_MISMATCH');
            }

            $result = (new CreateContextFileTool())->execute(array_filter([
                'context_type' => $meta['context_type'],
                'context_id' => $meta['context_id'],
                'file_name' => $meta['file_name'],
                'mime_type' => $meta['mime_type'],
                'base64_content' => base64_encode($content),
            ], fn ($v) => $v !== null), $context);

            if ($result->success) {
                $service->discard($uploadId);
            }

            return $result;
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Abschließen des Uploads: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
