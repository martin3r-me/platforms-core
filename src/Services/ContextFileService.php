<?php

namespace Platform\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
        // Verwende 'public' für öffentlich zugängliche Dateien
        // Falls S3 oder anderer Cloud-Storage gewünscht, kann das über ENV gesetzt werden
        $this->disk = config('filesystems.default', 'public');
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Generates a URL for a file (presigned for S3, signed route for local).
     */
    public static function generateUrl(string $disk, string $path, string $token, string $routeName, int $ttlMinutes = 60): string
    {
        $storage = Storage::disk($disk);

        if ($storage->providesTemporaryUrls()) {
            return $storage->temporaryUrl($path, now()->addMinutes($ttlMinutes));
        }

        return URL::temporarySignedRoute($routeName, now()->addMinutes($ttlMinutes), ['token' => $token]);
    }

    /**
     * Generates a download URL for a file (presigned for S3, signed route for local).
     */
    public static function generateDownloadUrl(string $disk, string $path, string $token, string $originalName, int $ttlMinutes = 5): string
    {
        $storage = Storage::disk($disk);

        if ($storage->providesTemporaryUrls()) {
            return $storage->temporaryUrl($path, now()->addMinutes($ttlMinutes), [
                'ResponseContentDisposition' => 'attachment; filename="' . $originalName . '"',
            ]);
        }

        return URL::temporarySignedRoute(
            'core.context-files.show',
            now()->addMinutes($ttlMinutes),
            ['token' => $token, 'download' => $originalName]
        );
    }

    /**
     * Lädt eine Datei für einen Kontext hoch
     * 
     * @param UploadedFile $file
     * @param string $contextType
     * @param int $contextId
     * @param array $options ['keep_original' => bool, 'generate_variants' => bool, 'user_id' => int, 'team_id' => int]
     * @return array ['id', 'token', 'path', 'original_name', 'url', 'variants']
     */
    public function uploadForContext(
        UploadedFile $file,
        string $contextType,
        int $contextId,
        array $options = []
    ): array {
        // User-ID und Team-ID aus Options oder Auth holen (für Commands)
        // user_id darf null sein (z.B. Inbound-Mail ohne authentifizierten User)
        $userId = array_key_exists('user_id', $options) ? $options['user_id'] : null;
        $teamId = $options['team_id'] ?? null;
        $userIdResolved = $userId !== null;

        // Wenn team_id oder user_id NICHT in Options vorhanden, versuche von Auth zu holen
        if (!$userIdResolved || is_null($teamId)) {
            // Fallback: Versuche von Auth zu holen (für Web-Requests)
            if (Auth::check()) {
                $user = Auth::user();
                if ($user) {
                    $team = $user->currentTeamRelation ?? null;
                    if ($team) {
                        $userId = $userId ?: $user->id;
                        $teamId = $teamId ?: $team->id;
                    }
                }
            }

            // team_id ist immer erforderlich; user_id darf null sein (z.B. bei Inbound-Mail)
            if (is_null($teamId)) {
                throw new \Exception('Kein Team-Kontext vorhanden. Bitte team_id in options übergeben. team_id: null');
            }
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
            // put() gibt den vollständigen Pfad zurück, aber wir wollen nur den Dateinamen
            Storage::disk($this->disk)->put($fileName, $webpContent);
            $path = $fileName; // Flache Struktur: nur Dateiname
            
            $mimeType = 'image/webp';
            $fileSize = strlen($webpContent);
        } else {
            // Nicht-Bilder: Original-Format behalten
            $fileName = "{$token}.{$extension}";
            // putFileAs mit leerem Pfad speichert im Root
            $path = Storage::disk($this->disk)->putFileAs('', $file, $fileName);
            // putFileAs kann einen Pfad mit Slash zurückgeben, normalisieren
            $path = ltrim($path, '/');
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
            'uploaded_by' => $userId,
        ];

        // Bild-Dimensionen in Meta speichern
        if ($isImage) {
            $meta['width'] = $width;
            $meta['height'] = $height;
        }

        // ContextFile in Datenbank speichern
        $contextFile = \Platform\Core\Models\ContextFile::create([
            'token' => $token,
            'team_id' => $teamId,
            'user_id' => $userId,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'disk' => $this->disk,
            'path' => $path,
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'file_size' => $fileSize, // Verwende berechnete Größe, nicht $file->getSize()
            'width' => $width,
            'height' => $height,
            'meta' => $meta,
            'keep_original' => $options['keep_original'] ?? false,
        ]);

        $variantsStatus = 'none';

        // Bildvarianten asynchron generieren (wenn Bild und gewünscht)
        if ($isImage && ($options['generate_variants'] ?? true)) {
            $contextFile->update(['variants_status' => 'pending']);
            $variantsStatus = 'pending';
            \Platform\Core\Jobs\GenerateImageVariantsJob::dispatch($contextFile->id);
        }

        return [
            'id' => $contextFile->id,
            'token' => $token,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'url' => self::generateUrl($this->disk, $path, $token, 'core.context-files.show'),
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'variants' => [],
            'variants_status' => $variantsStatus,
        ];
    }

    /**
     * Generiert Bildvarianten mit verschiedenen Seitenverhältnissen
     * 
     * Seitenverhältnisse: 4:3, 16:9, 1:1, 9:16, 3:1, original
     * Größen pro Seitenverhältnis: thumbnail, medium, large
     */
    public function generateImageVariants(\Platform\Core\Models\ContextFile $contextFile, bool $keepOriginal = false): array
    {
        $variants = [];
        $webpEncoder = new WebpEncoder(90);

        // Original lesen
        $originalContent = Storage::disk($this->disk)->get($contextFile->path);
        $originalImage = $this->imageManager->read($originalContent);
        $originalWidth = $originalImage->width();
        $originalHeight = $originalImage->height();
        $isPortrait = $originalHeight > $originalWidth;

        // Varianten-Definitionen: Genau wie im Vorbild (Uploads/Index.php)
        $aspectRatios = [
            '4_3' => [
                'thumbnail' => [300, 225],
                'medium' => [800, 600],
                'large' => [1200, 900],
                'high_resolution' => [2400, 1800],
            ],
            '16_9' => [
                'thumbnail' => [300, 169],
                'medium' => [800, 450],
                'large' => [1200, 675],
                'high_resolution' => [2400, 1350],
            ],
            '1_1' => [
                'thumbnail' => [300, 300],
                'medium' => [800, 800],
                'large' => [1200, 1200],
                'high_resolution' => [2400, 2400],
            ],
            '9_16' => [
                'thumbnail' => [300, 533],
                'medium' => [800, 1422],
                'large' => [1200, 2133],
                'high_resolution' => [2400, 4267],
            ],
            '3_1' => [
                'thumbnail' => [300, 100],
                'medium' => [900, 300],
                'large' => [1500, 500],
                'high_resolution' => [3000, 1000],
            ],
            'original' => [
                'thumbnail' => [300, null],
                'medium' => [800, null],
                'large' => [1200, null],
                'high_resolution' => [2400, null],
            ],
        ];

        // Für jedes Seitenverhältnis alle Größen generieren
        foreach ($aspectRatios as $aspectRatio => $sizes) {
            foreach ($sizes as $sizeName => $dimensions) {
                [$width, $height] = $dimensions;

                // Upscale Guard: Skip variants that would be larger than the original
                $isOriginalVariant = ($aspectRatio === 'original');
                $targetWidth = $width;
                $targetHeight = $height;

                if ($isOriginalVariant && $targetHeight === null) {
                    $targetHeight = (int) round($originalHeight * ($targetWidth / $originalWidth));
                }

                if ($targetWidth > $originalWidth || ($targetHeight !== null && $targetHeight > $originalHeight)) {
                    continue;
                }

                try {
                    // Bild neu lesen (jede Variante braucht frisches Original)
                    $variantImage = $this->imageManager->read($originalContent);
                    
                    // Original-Dimensionen für Debug
                    $beforeWidth = $variantImage->width();
                    $beforeHeight = $variantImage->height();

                    // Verarbeitung basierend auf Seitenverhältnis und Bild-Orientierung
                    // IMMER flächiges Bild ohne Padding - passenden Ausschnitt wählen
                    if ($isOriginalVariant) {
                        // Original-Verhältnis: Proportional skalieren (behält Seitenverhältnis)
                        $variantImage->scaleDown($width, $height);
                    } else {
                        // Feste Seitenverhältnisse: IMMER cover() verwenden (zuschneiden, kein Padding)
                        // Das erzeugt ein flächiges Bild mit passendem Ausschnitt
                        $variantImage->cover($width, $height);
                    }

                    // Token und Pfad generieren
                    $variantToken = $this->generateToken();
                    $variantPath = "{$variantToken}.webp";

                    // Variante speichern
                    Storage::disk($this->disk)->put($variantPath, (string) $variantImage->encode($webpEncoder));

                    // Tatsächliche Dimensionen ermitteln (NACH Verarbeitung!)
                    // GENAU wie im Vorbild: Bei original-Varianten Höhe nach Skalierung ermitteln
                    $actualWidth = $variantImage->width();
                    
                    // Manuelles Ermitteln der tatsächlichen Bildhöhe für Originalvarianten
                    if ($isOriginalVariant) {
                        $actualHeight = $variantImage->height();  // Höhe der skalierten Variante festlegen
                    } else {
                        // Für feste Seitenverhältnisse: verwende definierte Höhe
                        $actualHeight = $height;
                    }
                    
                    // Debug-Log
                    \Log::debug("[ContextFileService] Variante generiert", [
                        'variant_type' => "{$sizeName}_{$aspectRatio}",
                        'target' => "{$width}×" . ($height ?? 'auto'),
                        'before' => "{$beforeWidth}×{$beforeHeight}",
                        'after' => "{$actualWidth}×{$actualHeight}",
                    ]);

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
                        'url' => self::generateUrl($this->disk, $variantPath, $variantToken, 'core.context-files.variant'),
                        'width' => $actualWidth,
                        'height' => $actualHeight,
                    ];
                } catch (\Exception $e) {
                    // Log Fehler, aber weiter mit nächster Variante
                    \Log::error("[ContextFileService] Fehler bei Variante {$sizeName}_{$aspectRatio}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
        
        \Log::info("[ContextFileService] Varianten-Generierung abgeschlossen", [
            'context_file_id' => $contextFile->id,
            'variants_generated' => count($variants),
            'expected' => count($aspectRatios) * count(reset($aspectRatios)),
        ]);

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
    public function delete(int $contextFileId, ?int $teamId = null): void
    {
        $contextFile = \Platform\Core\Models\ContextFile::findOrFail($contextFileId);

        if ($teamId !== null) {
            abort_if($contextFile->team_id !== $teamId, 403, 'Keine Berechtigung für diese Datei');
        }

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

        return self::generateDownloadUrl(
            $contextFile->disk,
            $contextFile->path,
            $contextFile->token,
            $contextFile->original_name,
            5
        );
    }
}

