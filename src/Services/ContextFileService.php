<?php

namespace Platform\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Illuminate\Support\Facades\Auth;

/**
 * Service für kontextbezogene Datei-Uploads
 * 
 * - Flache Speicherung (keine Ordnerstruktur)
 * - Token-basierte Dateinamen
 * - Kontext-Bezug über Datenbank
 * - Bildvarianten optional (Standard: kleine Variante)
 * - Original behalten auf Zuruf
 */
class ContextFileService
{
    protected string $disk;
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Lädt eine Datei für einen Kontext hoch
     * 
     * @param UploadedFile $file
     * @param string $contextType
     * @param int $contextId
     * @param array $options ['keep_original' => bool, 'generate_variants' => bool]
     * @return array ['id', 'token', 'path', 'original_name', 'url', 'variants']
     */
    public function uploadForContext(
        UploadedFile $file,
        string $contextType,
        int $contextId,
        array $options = []
    ): array {
        $user = Auth::user();
        $team = $user->currentTeamRelation;

        if (!$team) {
            throw new \Exception('Kein Team-Kontext vorhanden');
        }

        // Token generieren (eindeutig)
        $token = $this->generateToken();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();
        $isImage = str_starts_with($mimeType, 'image/');

        // Dateiname: nur Token + Extension (flach, keine Ordnerstruktur)
        $fileName = "{$token}.{$extension}";

        // Datei speichern (flach im Root des Disks)
        $path = Storage::disk($this->disk)->putFileAs('', $file, $fileName);

        // Metadaten
        $meta = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $user->id,
        ];

        // Bild-Dimensionen extrahieren
        $width = null;
        $height = null;
        if ($isImage) {
            try {
                $image = $this->imageManager->read(Storage::disk($this->disk)->get($path));
                $width = $image->width();
                $height = $image->height();
                $meta['width'] = $width;
                $meta['height'] = $height;
            } catch (\Exception $e) {
                // Ignorieren wenn Bild nicht gelesen werden kann
            }
        }

        // ContextFile in Datenbank speichern
        $contextFile = \Platform\Core\Models\ContextFile::create([
            'token' => $token,
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'disk' => $this->disk,
            'path' => $path,
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'meta' => $meta,
            'keep_original' => $options['keep_original'] ?? false,
        ]);

        $variants = [];

        // Bildvarianten generieren (wenn Bild und gewünscht)
        if ($isImage && ($options['generate_variants'] ?? true)) {
            $variants = $this->generateImageVariants($contextFile, $options['keep_original'] ?? false);
        }

        return [
            'id' => $contextFile->id,
            'token' => $token,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'url' => Storage::disk($this->disk)->url($path),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'variants' => $variants,
        ];
    }

    /**
     * Generiert Bildvarianten
     * 
     * Standard: kleine Variante (thumbnail)
     * Optional: weitere Varianten + Original behalten
     */
    protected function generateImageVariants(\Platform\Core\Models\ContextFile $contextFile, bool $keepOriginal = false): array
    {
        $variants = [];
        $webpEncoder = new WebpEncoder();

        // Original lesen
        $originalContent = Storage::disk($this->disk)->get($contextFile->path);
        $originalImage = $this->imageManager->read($originalContent);
        $originalWidth = $originalImage->width();
        $originalHeight = $originalImage->height();

        // Standard: Thumbnail (kleine Variante)
        $thumbnail = $originalImage->scaleDown(300, 300);
        $thumbnailToken = $this->generateToken();
        $thumbnailPath = "{$thumbnailToken}.webp";
        Storage::disk($this->disk)->put($thumbnailPath, (string) $thumbnail->encode($webpEncoder, 85));

        // Variant in DB speichern
        $variant = \Platform\Core\Models\ContextFileVariant::create([
            'context_file_id' => $contextFile->id,
            'variant_type' => 'thumbnail',
            'token' => $thumbnailToken,
            'disk' => $this->disk,
            'path' => $thumbnailPath,
            'width' => $thumbnail->width(),
            'height' => $thumbnail->height(),
            'file_size' => Storage::disk($this->disk)->size($thumbnailPath),
        ]);

        $variants['thumbnail'] = [
            'token' => $thumbnailToken,
            'url' => Storage::disk($this->disk)->url($thumbnailPath),
            'width' => $thumbnail->width(),
            'height' => $thumbnail->height(),
        ];

        // Weitere Varianten nur wenn gewünscht
        if ($keepOriginal) {
            // Medium
            $medium = $originalImage->scaleDown(800, 800);
            $mediumToken = $this->generateToken();
            $mediumPath = "{$mediumToken}.webp";
            Storage::disk($this->disk)->put($mediumPath, (string) $medium->encode($webpEncoder, 90));

            \Platform\Core\Models\ContextFileVariant::create([
                'context_file_id' => $contextFile->id,
                'variant_type' => 'medium',
                'token' => $mediumToken,
                'disk' => $this->disk,
                'path' => $mediumPath,
                'width' => $medium->width(),
                'height' => $medium->height(),
                'file_size' => Storage::disk($this->disk)->size($mediumPath),
            ]);

            $variants['medium'] = [
                'token' => $mediumToken,
                'url' => Storage::disk($this->disk)->url($mediumPath),
                'width' => $medium->width(),
                'height' => $medium->height(),
            ];

            // Large
            $large = $originalImage->scaleDown(1200, 1200);
            $largeToken = $this->generateToken();
            $largePath = "{$largeToken}.webp";
            Storage::disk($this->disk)->put($largePath, (string) $large->encode($webpEncoder, 90));

            \Platform\Core\Models\ContextFileVariant::create([
                'context_file_id' => $contextFile->id,
                'variant_type' => 'large',
                'token' => $largeToken,
                'disk' => $this->disk,
                'path' => $largePath,
                'width' => $large->width(),
                'height' => $large->height(),
                'file_size' => Storage::disk($this->disk)->size($largePath),
            ]);

            $variants['large'] = [
                'token' => $largeToken,
                'url' => Storage::disk($this->disk)->url($largePath),
                'width' => $large->width(),
                'height' => $large->height(),
            ];
        }

        return $variants;
    }

    /**
     * Generiert einen eindeutigen Token
     */
    protected function generateToken(): string
    {
        return Str::random(32);
    }

    /**
     * Löscht eine Context-Datei
     */
    public function delete(int $contextFileId): void
    {
        $contextFile = \Platform\Core\Models\ContextFile::findOrFail($contextFileId);

        // Varianten löschen
        foreach ($contextFile->variants as $variant) {
            Storage::disk($variant->disk)->delete($variant->path);
            $variant->delete();
        }

        // Original löschen
        Storage::disk($contextFile->disk)->delete($contextFile->path);

        // DB-Eintrag löschen
        $contextFile->delete();
    }

    /**
     * Gibt Download-URL mit Original-Dateinamen zurück
     */
    public function getDownloadUrl(int $contextFileId): string
    {
        $contextFile = \Platform\Core\Models\ContextFile::findOrFail($contextFileId);
        
        // URL mit Original-Dateinamen als Query-Parameter
        $url = Storage::disk($contextFile->disk)->url($contextFile->path);
        $originalName = urlencode($contextFile->original_name);
        
        return "{$url}?download={$originalName}";
    }
}

