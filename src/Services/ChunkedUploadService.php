<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Zwischenspeicher für Chunked Uploads (core.context.files.upload.*).
 *
 * Teile liegen als Rohbytes unter chunked-uploads/{upload_id}/ auf der lokalen Disk,
 * Metadaten in meta.json. Unvollständige Uploads verfallen nach TTL_SECONDS.
 */
class ChunkedUploadService
{
    public const TTL_SECONDS = 3600;
    public const MAX_TOTAL_SIZE = 10 * 1024 * 1024;
    public const MAX_CHUNK_SIZE = 64 * 1024;
    public const MAX_CHUNKS = 1000;

    private const BASE = 'chunked-uploads';

    private function disk()
    {
        return Storage::disk('local');
    }

    public function start(array $meta): string
    {
        $this->cleanupExpired();

        $uploadId = (string) Str::uuid();
        $meta['created_at'] = time();
        $this->disk()->put($this->dir($uploadId) . '/meta.json', json_encode($meta));

        return $uploadId;
    }

    public function meta(string $uploadId): ?array
    {
        if (!Str::isUuid($uploadId)) {
            return null;
        }
        $path = $this->dir($uploadId) . '/meta.json';
        if (!$this->disk()->exists($path)) {
            return null;
        }
        $meta = json_decode($this->disk()->get($path), true);
        if (!is_array($meta) || ($meta['created_at'] ?? 0) < time() - self::TTL_SECONDS) {
            $this->discard($uploadId);
            return null;
        }

        return $meta;
    }

    public function putChunk(string $uploadId, int $index, string $bytes): void
    {
        $this->disk()->put($this->dir($uploadId) . '/' . $index . '.part', $bytes);
    }

    /** @return int[] sortierte Indizes der vorhandenen Teile */
    public function chunkIndexes(string $uploadId): array
    {
        $indexes = [];
        foreach ($this->disk()->files($this->dir($uploadId)) as $file) {
            if (preg_match('/(\d+)\.part$/', $file, $m)) {
                $indexes[] = (int) $m[1];
            }
        }
        sort($indexes);

        return $indexes;
    }

    public function assemble(string $uploadId): string
    {
        $content = '';
        foreach ($this->chunkIndexes($uploadId) as $index) {
            $content .= $this->disk()->get($this->dir($uploadId) . '/' . $index . '.part');
        }

        return $content;
    }

    public function discard(string $uploadId): void
    {
        if (Str::isUuid($uploadId)) {
            $this->disk()->deleteDirectory($this->dir($uploadId));
        }
    }

    /** Räumt abgelaufene (abgebrochene) Uploads auf. */
    public function cleanupExpired(): int
    {
        $removed = 0;
        foreach ($this->disk()->directories(self::BASE) as $dir) {
            $metaPath = $dir . '/meta.json';
            $created = 0;
            if ($this->disk()->exists($metaPath)) {
                $meta = json_decode($this->disk()->get($metaPath), true);
                $created = is_array($meta) ? (int) ($meta['created_at'] ?? 0) : 0;
            }
            if ($created < time() - self::TTL_SECONDS) {
                $this->disk()->deleteDirectory($dir);
                $removed++;
            }
        }

        return $removed;
    }

    private function dir(string $uploadId): string
    {
        return self::BASE . '/' . $uploadId;
    }
}
