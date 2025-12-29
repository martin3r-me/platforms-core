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

        // Für Bilder: Original immer als WebP speichern
        if ($isImage) {
            // Bild lesen und als WebP speichern
            $image = $this->imageManager->read($file->getRealPath());
            $width = $image->width();
            $height = $image->height();
            
            $webpEncoder = new WebpEncoder(90); // 90% Qualität
            $webpContent = (string) $image->encode($webpEncoder);
            
            // Dateiname: Token + .webp
            $fileName = "{$token}.webp";
            $path = Storage::disk($this->disk)->put($fileName, $webpContent);
            
            $mimeType = 'image/webp';
            $fileSize = strlen($webpContent);
        } else {
            // Nicht-Bilder: Original-Format behalten
            $fileName = "{$token}.{$extension}";
            $path = Storage::disk($this->disk)->putFileAs('', $file, $fileName);
            $fileSize = $file->getSize();
            $width = null;
            $height = null;
        }

        // Metadaten
        $meta = [
            'original_name' => $file->getClientOriginalName(),
            'original_mime_type' => $file->getMimeType(),
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'uploaded_at' => now()->toIso8601String(),
            'uploaded_by' => $user->id,
        ];

        // Bild-Dimensionen in Meta speichern
        if ($isImage) {
            $meta['width'] = $width;
            $meta['height'] = $height;
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
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'variants' => $variants,
        ];
    }

    /**
     * Generiert Bildvarianten mit verschiedenen Seitenverhältnissen
     * 
     * Seitenverhältnisse: 4:3, 16:9, 1:1, 9:16, 3:1, original
     * Größen pro Seitenverhältnis: thumbnail, medium, large
     */
    protected function generateImageVariants(\Platform\Core\Models\ContextFile $contextFile, bool $keepOriginal = false): array
    {
        $variants = [];
        $webpEncoder = new WebpEncoder(90);

        // Original lesen
        $originalContent = Storage::disk($this->disk)->get($contextFile->path);
        $originalImage = $this->imageManager->read($originalContent);
        $originalWidth = $originalImage->width();
        $originalHeight = $originalImage->height();
        $isPortrait = $originalHeight > $originalWidth;

        // Varianten-Definitionen: Seitenverhältnis => [thumbnail, medium, large]
        $aspectRatios = [
            '4_3' => [
                'thumbnail' => [300, 225],
                'medium' => [800, 600],
                'large' => [1200, 900],
            ],
            '16_9' => [
                'thumbnail' => [300, 169],
                'medium' => [800, 450],
                'large' => [1200, 675],
            ],
            '1_1' => [
                'thumbnail' => [300, 300],
                'medium' => [800, 800],
                'large' => [1200, 1200],
            ],
            '9_16' => [
                'thumbnail' => [300, 533],
                'medium' => [800, 1422],
                'large' => [1200, 2133],
            ],
            '3_1' => [
                'thumbnail' => [300, 100],
                'medium' => [900, 300],
                'large' => [1500, 500],
            ],
            'original' => [
                'thumbnail' => [300, null],
                'medium' => [800, null],
                'large' => [1200, null],
            ],
        ];

        // Für jedes Seitenverhältnis alle Größen generieren
        foreach ($aspectRatios as $aspectRatio => $sizes) {
            foreach ($sizes as $sizeName => $dimensions) {
                [$width, $height] = $dimensions;

                // Bild neu lesen (jede Variante braucht frisches Original)
                $variantImage = $this->imageManager->read($originalContent);

                // Verarbeitung basierend auf Seitenverhältnis und Bild-Orientierung
                if ($aspectRatio === 'original') {
                    // Original-Verhältnis: nur skalieren
                    $variantImage->scaleDown($width, $height);
                } else {
                    // Feste Seitenverhältnisse
                    if ($isPortrait) {
                        // Hochformat: contain (mit Padding)
                        $variantImage->contain($width, $height, 'ffffff');
                    } else {
                        // Querformat: cover (zuschneiden)
                        $variantImage->cover($width, $height);
                    }
                }

                // Token und Pfad generieren
                $variantToken = $this->generateToken();
                $variantPath = "{$variantToken}.webp";

                // Variante speichern
                Storage::disk($this->disk)->put($variantPath, (string) $variantImage->encode($webpEncoder));

                // Tatsächliche Dimensionen ermitteln (wichtig für original-Varianten)
                $actualWidth = $variantImage->width();
                $actualHeight = $height ?? $variantImage->height();

                // Variant in DB speichern
                $variant = \Platform\Core\Models\ContextFileVariant::create([
                    'context_file_id' => $contextFile->id,
                    'variant_type' => "{$sizeName}_{$aspectRatio}",
                    'token' => $variantToken,
                    'disk' => $this->disk,
                    'path' => $variantPath,
                    'width' => $actualWidth,
                    'height' => $actualHeight,
                    'file_size' => Storage::disk($this->disk)->size($variantPath),
                ]);

                // In Variants-Array speichern
                $variants["{$sizeName}_{$aspectRatio}"] = [
                    'token' => $variantToken,
                    'url' => Storage::disk($this->disk)->url($variantPath),
                    'width' => $actualWidth,
                    'height' => $actualHeight,
                ];
            }
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

