<?php

namespace Platform\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Platform\Core\Jobs\GenerateImageVariantsJob;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\ContextFileReference;
use Platform\Core\Models\ContextFileUpload;

/**
 * Direkt-Upload per signiertem Link (Autorisierung nur über MCP, Transfer per HTTP PUT nach S3).
 */
class DirectUploadService
{
    public const MAX_SIZE = 100 * 1024 * 1024;
    public const LINK_TTL_MINUTES = 15;
    /** Unverknüpfte, fertige Dateien werden nach dieser Frist entfernt. */
    public const UNATTACHED_TTL_HOURS = 24;

    private const FOLDER = 'pending-uploads';

    /** Prüft Kontext + Team; liefert Fehlertext oder null. */
    public function validateContext(string $type, int $id, int $teamId): ?string
    {
        if (!class_exists($type) || !is_subclass_of($type, Model::class)) {
            return "context_type '{$type}' ist kein gültiges Model.";
        }
        $model = $type::find($id);
        if (!$model) {
            return "Kein {$type} mit ID {$id} gefunden.";
        }
        if (isset($model->team_id) && (int) $model->team_id !== $teamId) {
            return 'Das Context-Objekt gehört nicht zum aktuellen Team.';
        }

        return null;
    }

    public function request(array $data): array
    {
        $this->cleanupExpired();

        $disk = config('filesystems.default', 'public');
        $uuid = (string) Str::uuid();
        $upload = ContextFileUpload::create($data + [
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => self::FOLDER . '/' . $uuid,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::LINK_TTL_MINUTES),
        ]);

        return [$upload, URL::temporarySignedRoute(
            'core.context-files.direct-upload',
            $upload->expires_at,
            ['uuid' => $uuid]
        )];
    }

    /** Streamt den Request-Body ohne lokale Zwischenablage in den Storage. */
    public function receive(ContextFileUpload $upload, $stream, ?int $contentLength): void
    {
        if ($contentLength !== null && $contentLength !== $upload->total_size) {
            throw new \RuntimeException('Content-Length (' . $contentLength . ') weicht von total_size (' . $upload->total_size . ') ab.');
        }
        $disk = Storage::disk($upload->disk);
        $disk->writeStream($upload->path, $stream);
        $size = $disk->size($upload->path);
        if ($size !== $upload->total_size) {
            $disk->delete($upload->path);
            throw new \RuntimeException('Größe stimmt nicht: erwartet ' . $upload->total_size . ', empfangen ' . $size . '.');
        }
        $upload->update(['status' => 'uploaded']);
    }

    /** Verifiziert (sha256 gestreamt) und legt den ContextFile-Eintrag an. */
    public function complete(ContextFileUpload $upload): ContextFile
    {
        $disk = Storage::disk($upload->disk);
        if ($disk->size($upload->path) !== $upload->total_size) {
            throw new \RuntimeException('SIZE_MISMATCH');
        }
        if ($upload->sha256) {
            $ctx = hash_init('sha256');
            $stream = $disk->readStream($upload->path);
            hash_update_stream($ctx, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (!hash_equals($upload->sha256, hash_final($ctx))) {
                throw new \RuntimeException('CHECKSUM_MISMATCH');
            }
        }

        $token = Str::random(32);
        $ext = strtolower(pathinfo($upload->file_name, PATHINFO_EXTENSION));
        $mime = $upload->mime_type ?: ($disk->mimeType($upload->path) ?: 'application/octet-stream');
        $finalPath = $token . ($ext !== '' ? '.' . $ext : '');
        $disk->move($upload->path, $finalPath);

        $isRaster = str_starts_with($mime, 'image/') && !in_array(strtolower($mime), ['image/svg+xml', 'image/svg'], true);
        $file = ContextFile::create([
            'token' => $token,
            'team_id' => $upload->team_id,
            'user_id' => $upload->user_id,
            'context_type' => $upload->context_type,
            'context_id' => $upload->context_id,
            'disk' => $upload->disk,
            'path' => $finalPath,
            'file_name' => $finalPath,
            'original_name' => $upload->file_name,
            'mime_type' => $mime,
            'file_size' => $upload->total_size,
            'meta' => [
                'original_name' => $upload->file_name,
                'mime_type' => $mime,
                'file_size' => $upload->total_size,
                'uploaded_at' => now()->toIso8601String(),
                'uploaded_by' => $upload->user_id,
                'source' => 'direct-upload',
            ],
            'keep_original' => false,
        ]);
        if ($isRaster) {
            $file->update(['variants_status' => 'pending']);
            GenerateImageVariantsJob::dispatch($file->id);
        }
        $upload->update(['status' => 'completed']);

        return $file;
    }

    /** Verknüpft eine Datei mit einem Kontext (primär, falls frei; sonst als Reference). */
    public function attach(ContextFile $file, string $type, int $id): string
    {
        if (!$file->context_type) {
            $file->update(['context_type' => $type, 'context_id' => $id]);

            return 'primary';
        }
        if ($file->context_type === $type && (int) $file->context_id === $id) {
            return 'already';
        }
        ContextFileReference::firstOrCreate([
            'context_file_id' => $file->id,
            'reference_type' => $type,
            'reference_id' => $id,
        ]);

        return 'reference';
    }

    /** Räumt abgelaufene Links und unverknüpfte Dateien auf (S3 + DB). */
    public function cleanupExpired(): int
    {
        $removed = 0;
        $stale = ContextFileUpload::whereIn('status', ['pending', 'uploaded'])->where('expires_at', '<', now())->get();
        foreach ($stale as $u) {
            Storage::disk($u->disk)->delete($u->path);
            $u->delete();
            $removed++;
        }
        ContextFileUpload::where('status', 'completed')->where('updated_at', '<', now()->subDays(7))->delete();

        $orphans = ContextFile::whereNull('context_type')
            ->where('created_at', '<', now()->subHours(self::UNATTACHED_TTL_HOURS))
            ->whereDoesntHave('references')
            ->get();
        foreach ($orphans as $f) {
            app(ContextFileService::class)->delete($f->id);
            $removed++;
        }

        return $removed;
    }
}
