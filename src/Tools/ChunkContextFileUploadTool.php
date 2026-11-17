<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ChunkedUploadService;

/**
 * Hängt einen Teil an einen Chunked Upload an (Schritt 2 von 3).
 */
class ChunkContextFileUploadTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.upload.chunk';
    }

    public function getDescription(): string
    {
        return 'Sendet einen base64-kodierten Teil (index ab 0) zu einem mit core.context.files.upload.start gestarteten Upload. '
            . 'Teile dürfen in beliebiger Reihenfolge und wiederholt gesendet werden (gleicher index überschreibt).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'upload_id' => ['type' => 'string', 'description' => 'upload_id aus upload.start'],
                'index' => ['type' => 'integer', 'description' => 'Laufende Nummer des Teils, beginnend bei 0'],
                'base64_content' => ['type' => 'string', 'description' => 'Base64-kodierter Teil (max. 64KB Rohdaten, empfohlen 20–30KB)'],
            ],
            'required' => ['upload_id', 'index', 'base64_content'],
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

            $index = $arguments['index'] ?? null;
            if (!is_int($index) && !(is_string($index) && ctype_digit($index))) {
                return ToolResult::error('index muss eine Ganzzahl >= 0 sein', 'VALIDATION_ERROR');
            }
            $index = (int) $index;
            if ($index < 0 || $index >= ChunkedUploadService::MAX_CHUNKS) {
                return ToolResult::error('index außerhalb des erlaubten Bereichs (0–' . (ChunkedUploadService::MAX_CHUNKS - 1) . ')', 'VALIDATION_ERROR');
            }

            $encoded = (string) ($arguments['base64_content'] ?? '');
            $bytes = $encoded === '' ? false : base64_decode($encoded, true);
            if ($bytes === false) {
                return ToolResult::error('Ungültiger oder leerer Base64-Inhalt', 'VALIDATION_ERROR');
            }
            if (strlen($bytes) > ChunkedUploadService::MAX_CHUNK_SIZE) {
                return ToolResult::error('Teil ist zu groß (max. 64KB Rohdaten)', 'CHUNK_TOO_LARGE');
            }

            $service->putChunk($uploadId, $index, $bytes);

            return ToolResult::success([
                'upload_id' => $uploadId,
                'index' => $index,
                'chunk_bytes' => strlen($bytes),
                'chunks_received' => count($service->chunkIndexes($uploadId)),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Speichern des Teils: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
