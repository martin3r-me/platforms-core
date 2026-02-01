<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;

/**
 * Tool zum Auflisten von Context-Files
 *
 * Ermöglicht der LLM, verfügbare Dateien an einem bestimmten Context zu entdecken.
 * Statt Files vorab zu senden, kann die LLM selbst entscheiden, welche sie braucht.
 */
class GetContextFilesTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.GET';
    }

    public function getDescription(): string
    {
        return 'Listet verfügbare Dateien an einem Context-Objekt auf (z.B. Task-Attachments, Ticket-Anhänge). '
            . 'Nutze dieses Tool, um zu sehen welche Dateien an einem Objekt hängen, bevor du deren Inhalt abrufst. '
            . 'Wenn du weißt, dass es um Dateien/Attachments geht, rufe zuerst dieses Tool auf um die verfügbaren Dateien zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Der vollqualifizierte Model-Klassenname des Context-Objekts, z.B. "Platform\Planner\Models\PlannerTask" oder "Platform\Planner\Models\PlannerTicket". Kann auch der einfache Name sein.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Die ID des Context-Objekts',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Optional: Suche in Dateinamen (partial match)',
                ],
                'mime_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach MIME-Typ-Präfix, z.B. "image/" oder "text/"',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximale Anzahl Ergebnisse (Standard: 50, Max: 100)',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Offset für Pagination (Standard: 0)',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('NO_TEAM', 'Kein Team-Kontext verfügbar');
            }

            $contextType = $arguments['context_type'] ?? null;
            $contextId = $arguments['context_id'] ?? null;
            $search = $arguments['search'] ?? null;
            $mimeType = $arguments['mime_type'] ?? null;
            $limit = min((int) ($arguments['limit'] ?? 50), 100);
            $offset = (int) ($arguments['offset'] ?? 0);

            $query = ContextFile::query()
                ->where('team_id', $team->id)
                ->orderByDesc('created_at');

            // Filter by context type if provided
            if ($contextType) {
                // Support both full class name and simple name
                if (!str_contains($contextType, '\\')) {
                    // Simple name - try to match partial
                    $query->where('context_type', 'LIKE', '%' . $contextType);
                } else {
                    $query->where('context_type', $contextType);
                }
            }

            // Filter by context ID if provided
            if ($contextId) {
                $query->where('context_id', (int) $contextId);
            }

            // Search in filename
            if ($search) {
                $query->where('original_name', 'LIKE', '%' . $search . '%');
            }

            // Filter by MIME type prefix
            if ($mimeType) {
                $query->where('mime_type', 'LIKE', $mimeType . '%');
            }

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $files = $query
                ->skip($offset)
                ->take($limit)
                ->get();

            $result = [
                'files' => $files->map(fn($file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size' => $file->file_size,
                    'size_human' => $this->humanFileSize($file->file_size),
                    'is_image' => str_starts_with($file->mime_type, 'image/'),
                    'is_text' => $this->isTextFile($file->mime_type),
                    'context_type' => $file->context_type,
                    'context_id' => $file->context_id,
                    'created_at' => $file->created_at?->toIso8601String(),
                ])->toArray(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ];

            // Add hint for using content tool
            if ($total > 0) {
                $result['hint'] = 'Nutze core.context.files.content.GET mit file_id um den Inhalt einer Datei abzurufen.';
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der Dateien: ' . $e->getMessage());
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
        ];

        foreach ($textTypes as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
