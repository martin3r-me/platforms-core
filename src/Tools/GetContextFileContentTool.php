<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;
use Illuminate\Support\Facades\Storage;

/**
 * Tool zum Abrufen des Inhalts einer Context-Datei
 *
 * Ermöglicht der LLM, den Inhalt einer spezifischen Datei abzurufen.
 * Für Text-Dateien wird der Inhalt direkt zurückgegeben,
 * für Bilder/Binärdateien wird die URL zurückgegeben.
 */
class GetContextFileContentTool implements ToolContract
{
    /**
     * Maximum file size to return content (50KB)
     */
    private const MAX_CONTENT_SIZE = 50 * 1024;

    public function getName(): string
    {
        return 'core.context.files.content.GET';
    }

    public function getDescription(): string
    {
        return 'Ruft den Inhalt einer Datei ab. Für Text-Dateien (Code, JSON, Markdown, etc.) wird der '
            . 'Inhalt direkt zurückgegeben (max. 50KB). Für Bilder wird die URL zurückgegeben. '
            . 'Nutze zuerst core.context.files.GET um verfügbare Dateien und deren IDs zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file_id' => [
                    'type' => 'integer',
                    'description' => 'Die ID der Datei (aus core.context.files.GET)',
                ],
            ],
            'required' => ['file_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('NO_TEAM', 'Kein Team-Kontext verfügbar');
            }

            $fileId = $arguments['file_id'] ?? null;
            if (!$fileId) {
                return ToolResult::error('VALIDATION_ERROR', 'file_id ist erforderlich');
            }

            // Find file with team scope
            $file = ContextFile::query()
                ->where('id', (int) $fileId)
                ->where('team_id', $team->id)
                ->first();

            if (!$file) {
                return ToolResult::error('NOT_FOUND', 'Datei nicht gefunden oder keine Berechtigung');
            }

            $result = [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->file_size,
                'size_human' => $this->humanFileSize($file->file_size),
                'context_type' => $file->context_type,
                'context_id' => $file->context_id,
            ];

            // Check if it's a text file
            if ($this->isTextFile($file->mime_type)) {
                // Try to read the content
                try {
                    $disk = Storage::disk($file->disk);
                    if (!$disk->exists($file->path)) {
                        return ToolResult::error('FILE_NOT_FOUND', 'Datei existiert nicht mehr im Storage');
                    }

                    $content = $disk->get($file->path);

                    // Check size limit
                    if (strlen($content) > self::MAX_CONTENT_SIZE) {
                        $result['content'] = substr($content, 0, self::MAX_CONTENT_SIZE);
                        $result['truncated'] = true;
                        $result['truncated_at'] = self::MAX_CONTENT_SIZE;
                        $result['total_size'] = strlen($content);
                        $result['hint'] = 'Datei wurde bei 50KB abgeschnitten. Gesamtgröße: ' . $this->humanFileSize(strlen($content));
                    } else {
                        $result['content'] = $content;
                        $result['truncated'] = false;
                    }
                } catch (\Exception $e) {
                    return ToolResult::error('READ_ERROR', 'Fehler beim Lesen der Datei: ' . $e->getMessage());
                }
            } elseif (str_starts_with($file->mime_type, 'image/')) {
                // For images, return URL
                $result['url'] = $file->url;
                $result['is_image'] = true;
                $result['hint'] = 'Für Bilder wird die URL zurückgegeben, nicht der Binärinhalt.';

                // Add dimensions if available
                if ($file->width && $file->height) {
                    $result['width'] = $file->width;
                    $result['height'] = $file->height;
                }

                // Add thumbnail URL if available
                $thumbnail = $file->thumbnail;
                if ($thumbnail) {
                    $result['thumbnail_url'] = Storage::disk($thumbnail->disk)->url($thumbnail->path);
                }
            } else {
                // Binary file - return URL for download
                $result['url'] = $file->url;
                $result['download_url'] = $file->download_url;
                $result['is_binary'] = true;
                $result['hint'] = 'Binärdatei - Inhalt kann nicht als Text dargestellt werden. Download-URL verfügbar.';
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der Datei: ' . $e->getMessage());
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

    private function isTextFile(string $mimeType): bool
    {
        $textTypes = [
            'text/',
            'application/json',
            'application/xml',
            'application/javascript',
            'application/x-php',
            'application/x-python',
            'application/x-ruby',
            'application/x-yaml',
            'application/yaml',
            'application/x-httpd-php',
            'application/x-sh',
            'application/x-shellscript',
        ];

        foreach ($textTypes as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
