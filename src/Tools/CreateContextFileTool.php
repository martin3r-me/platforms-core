<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

/**
 * Tool zum Erstellen von Context-Dateien
 *
 * Ermöglicht der LLM, Dateien in einem Context zu erstellen/speichern.
 * Unterstützt Text-Inhalte direkt oder Fetch von URL.
 */
class CreateContextFileTool implements ToolContract
{
    /**
     * Maximum content size (500KB for text)
     */
    private const MAX_CONTENT_SIZE = 500 * 1024;

    /**
     * Maximum URL fetch size (10MB)
     */
    private const MAX_URL_FETCH_SIZE = 10 * 1024 * 1024;

    public function getName(): string
    {
        return 'core.context.files.CREATE';
    }

    public function getDescription(): string
    {
        return 'Erstellt eine neue Datei in einem Context. Kann Text-Inhalt direkt speichern (Code, JSON, Markdown, etc.), '
            . 'binäre Dateien via Base64 hochladen, oder eine Datei von einer URL abrufen und speichern. Nutze dieses Tool um generierte Inhalte oder '
            . 'externe Dateien an ein Context-Objekt (Task, Ticket, etc.) anzuhängen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Der vollqualifizierte Model-Klassenname des Context-Objekts, z.B. "Platform\Planner\Models\PlannerTask"',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Die ID des Context-Objekts',
                ],
                'file_name' => [
                    'type' => 'string',
                    'description' => 'Der gewünschte Dateiname inkl. Endung, z.B. "report.json", "script.py", "notes.md"',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Der Text-Inhalt der Datei. Nutze dies für Code, JSON, Markdown, CSV, etc. Max. 500KB.',
                ],
                'base64_content' => [
                    'type' => 'string',
                    'description' => 'Base64-kodierter Inhalt der Datei. Nutze dies für binäre Dateien wie Bilder. '
                        . 'Kann mit oder ohne Data-URL-Präfix sein (data:image/png;base64,...). Max. 10MB nach Dekodierung.',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'Optional: URL zum Abrufen der Datei. Wird verwendet wenn kein content oder base64_content angegeben ist. Max. 10MB.',
                ],
                'mime_type' => [
                    'type' => 'string',
                    'description' => 'Optional: MIME-Typ der Datei. Wird automatisch erkannt wenn nicht angegeben.',
                ],
            ],
            'required' => ['context_type', 'context_id', 'file_name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            $user = $context->user;

            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            // Validate required arguments
            $contextType = $arguments['context_type'] ?? null;
            $contextId = $arguments['context_id'] ?? null;
            $fileName = $arguments['file_name'] ?? null;
            $content = $arguments['content'] ?? null;
            $base64Content = $arguments['base64_content'] ?? null;
            $url = $arguments['url'] ?? null;
            $mimeType = $arguments['mime_type'] ?? null;

            if (!$contextType) {
                return ToolResult::error('context_type ist erforderlich', 'VALIDATION_ERROR');
            }
            if (!$contextId) {
                return ToolResult::error('context_id ist erforderlich', 'VALIDATION_ERROR');
            }
            if (!$fileName) {
                return ToolResult::error('file_name ist erforderlich', 'VALIDATION_ERROR');
            }

            // Need either base64_content, content, or url
            if (!$base64Content && !$content && !$url) {
                return ToolResult::error('Entweder base64_content, content oder url muss angegeben werden', 'VALIDATION_ERROR');
            }

            // Track content source for metadata
            $contentSource = 'content';

            // Priority 1: base64_content (binary data)
            if ($base64Content) {
                // Handle data URL format: data:image/png;base64,iVBORw0...
                if (preg_match('/^data:([^;]+);base64,(.+)$/si', $base64Content, $matches)) {
                    $mimeType = $mimeType ?? $matches[1];
                    $decodedContent = base64_decode($matches[2], true);
                } else {
                    $decodedContent = base64_decode($base64Content, true);
                }

                if ($decodedContent === false) {
                    return ToolResult::error('Ungültiger Base64-Inhalt', 'VALIDATION_ERROR');
                }

                if (strlen($decodedContent) > self::MAX_URL_FETCH_SIZE) {
                    return ToolResult::error('Dekodierte Base64-Daten sind zu groß (max. 10MB)', 'FILE_TOO_LARGE');
                }

                $content = $decodedContent;
                $contentSource = 'base64';
            }

            // Priority 3: Get content from URL if no direct content or base64
            if (!$content && $url) {
                try {
                    $response = Http::timeout(30)->get($url);

                    if (!$response->successful()) {
                        return ToolResult::error('URL konnte nicht abgerufen werden: HTTP ' . $response->status(), 'URL_FETCH_ERROR');
                    }

                    $content = $response->body();

                    if (strlen($content) > self::MAX_URL_FETCH_SIZE) {
                        return ToolResult::error('Datei von URL ist zu groß (max. 10MB)', 'FILE_TOO_LARGE');
                    }

                    // Try to get mime type from response
                    if (!$mimeType) {
                        $mimeType = $response->header('Content-Type');
                        if ($mimeType) {
                            // Remove charset etc
                            $mimeType = explode(';', $mimeType)[0];
                        }
                    }

                    $contentSource = 'url';
                } catch (\Exception $e) {
                    return ToolResult::error('Fehler beim Abrufen der URL: ' . $e->getMessage(), 'URL_FETCH_ERROR');
                }
            }

            // Check content size for direct text content (not base64 or url)
            if ($contentSource === 'content' && strlen($content) > self::MAX_CONTENT_SIZE) {
                return ToolResult::error('Content ist zu groß (max. 500KB für direkten Text)', 'CONTENT_TOO_LARGE');
            }

            // Determine mime type from extension if not provided
            if (!$mimeType) {
                $mimeType = $this->getMimeTypeFromExtension($fileName);
            }

            // Check if this is an image - use ContextFileService for WebP conversion + variants
            if ($this->isImage($mimeType)) {
                return $this->handleImageUpload(
                    $content,
                    $fileName,
                    $mimeType,
                    $contextType,
                    (int) $contextId,
                    $user,
                    $team,
                    $contentSource,
                    $url
                );
            }

            // Non-image files: Store directly
            $disk = config('filesystems.default', 'public');
            $token = Str::random(32);
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $storedFileName = "{$token}.{$extension}";

            // Store the file
            Storage::disk($disk)->put($storedFileName, $content);

            // Create ContextFile record
            $contextFile = ContextFile::create([
                'token' => $token,
                'team_id' => $team->id,
                'user_id' => $user->id,
                'context_type' => $contextType,
                'context_id' => (int) $contextId,
                'disk' => $disk,
                'path' => $storedFileName,
                'file_name' => $storedFileName,
                'original_name' => $fileName,
                'mime_type' => $mimeType,
                'file_size' => strlen($content),
                'width' => null,
                'height' => null,
                'meta' => [
                    'created_by_tool' => true,
                    'source' => $contentSource,
                    'source_url' => $url,
                ],
                'keep_original' => true,
            ]);

            return ToolResult::success([
                'id' => $contextFile->id,
                'token' => $token,
                'name' => $fileName,
                'mime_type' => $mimeType,
                'size' => strlen($content),
                'size_human' => $this->humanFileSize(strlen($content)),
                'url' => Storage::disk($disk)->url($storedFileName),
                'context_type' => $contextType,
                'context_id' => $contextId,
                'source' => $contentSource,
                'created_at' => $contextFile->created_at->toIso8601String(),
                'hint' => 'Datei erfolgreich erstellt. Nutze core.context.files.GET um alle Dateien des Contexts zu sehen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Erstellen der Datei: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' Bytes';
    }

    private function getMimeTypeFromExtension(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $mimeTypes = [
            // Text
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'markdown' => 'text/markdown',

            // Code
            'php' => 'application/x-php',
            'js' => 'application/javascript',
            'ts' => 'application/typescript',
            'jsx' => 'application/javascript',
            'tsx' => 'application/typescript',
            'py' => 'application/x-python',
            'rb' => 'application/x-ruby',
            'java' => 'text/x-java-source',
            'c' => 'text/x-c',
            'cpp' => 'text/x-c++',
            'cs' => 'text/x-csharp',
            'go' => 'text/x-go',
            'rs' => 'text/x-rust',
            'swift' => 'text/x-swift',
            'kt' => 'text/x-kotlin',
            'scala' => 'text/x-scala',
            'sh' => 'application/x-sh',
            'bash' => 'application/x-sh',
            'zsh' => 'application/x-sh',
            'sql' => 'application/sql',

            // Data
            'json' => 'application/json',
            'xml' => 'application/xml',
            'yaml' => 'application/x-yaml',
            'yml' => 'application/x-yaml',
            'csv' => 'text/csv',
            'tsv' => 'text/tab-separated-values',

            // Web
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'scss' => 'text/x-scss',
            'sass' => 'text/x-sass',
            'less' => 'text/x-less',

            // Config
            'ini' => 'text/plain',
            'conf' => 'text/plain',
            'cfg' => 'text/plain',
            'env' => 'text/plain',
            'htaccess' => 'text/plain',
            'gitignore' => 'text/plain',
            'dockerfile' => 'text/plain',

            // Documents
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',

            // Archives
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'rar' => 'application/vnd.rar',
            '7z' => 'application/x-7z-compressed',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Check if MIME type is an image
     */
    private function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Handle image upload via ContextFileService (WebP + Varianten)
     */
    private function handleImageUpload(
        string $content,
        string $fileName,
        string $mimeType,
        string $contextType,
        int $contextId,
        $user,
        $team,
        string $contentSource,
        ?string $sourceUrl
    ): ToolResult {
        // Create temporary file for ContextFileService
        $tempPath = tempnam(sys_get_temp_dir(), 'ctx_img_');
        file_put_contents($tempPath, $content);

        try {
            $uploadedFile = new UploadedFile(
                $tempPath,
                $fileName,
                $mimeType,
                null,
                true // test mode = skip validation
            );

            // Use ContextFileService for WebP conversion + variant generation
            $service = app(ContextFileService::class);
            $result = $service->uploadForContext(
                $uploadedFile,
                $contextType,
                $contextId,
                [
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'generate_variants' => true,
                ]
            );

            @unlink($tempPath);

            return ToolResult::success([
                'id' => $result['id'],
                'token' => $result['token'],
                'name' => $fileName,
                'mime_type' => $result['mime_type'],
                'size' => $result['file_size'],
                'size_human' => $this->humanFileSize($result['file_size']),
                'url' => $result['url'],
                'width' => $result['width'],
                'height' => $result['height'],
                'context_type' => $contextType,
                'context_id' => $contextId,
                'variants_count' => count($result['variants'] ?? []),
                'source' => $contentSource,
                'source_url' => $sourceUrl,
                'hint' => 'Bild erfolgreich erstellt mit WebP-Konvertierung und ' . count($result['variants'] ?? []) . ' Varianten. Nutze core.context.files.GET um alle Dateien zu sehen.',
            ]);
        } catch (\Exception $e) {
            @unlink($tempPath);
            return ToolResult::error('Fehler bei der Bildverarbeitung: ' . $e->getMessage(), 'IMAGE_PROCESSING_ERROR');
        }
    }
}
