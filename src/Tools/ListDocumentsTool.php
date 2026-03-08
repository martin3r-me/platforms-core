<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;

class ListDocumentsTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.LIST';
    }

    public function getDescription(): string
    {
        return 'Listet alle Dokumente des Teams auf. '
            . 'Zeigt ID, Titel, Template, Status und share_url. '
            . 'Filter nach template_key, status oder Suchbegriff im Titel möglich. '
            . 'Nutze core.documents.GET mit document_id für Details und Download-URL eines einzelnen Dokuments.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Filter nach Ordner-ID. 0 = nur Schreibtisch-Dokumente (ohne Ordner).',
                ],
                'template_key' => [
                    'type' => 'string',
                    'description' => 'Filter nach Template-Key (z.B. "report", "letter", "table-report")',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter nach Status: draft, rendered, failed',
                ],
                'tag' => [
                    'type' => 'string',
                    'description' => 'Filter nach Tag-Name (z.B. "wichtig", "q1-2026"). Zeigt nur Dokumente mit diesem Tag.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Suche im Titel (partial match)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Ergebnisse (Standard: 25, Max: 100)',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Offset für Pagination',
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
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $limit = min((int) ($arguments['limit'] ?? 25), 100);
            $offset = (int) ($arguments['offset'] ?? 0);

            $query = Document::where('team_id', $team->id)
                ->orderByDesc('created_at');

            if (array_key_exists('folder_id', $arguments)) {
                $folderId = (int) $arguments['folder_id'];
                if ($folderId === 0) {
                    $query->whereNull('document_folder_id');
                } else {
                    $query->where('document_folder_id', $folderId);
                }
            }

            if (!empty($arguments['template_key'])) {
                $query->where('template_key', $arguments['template_key']);
            }

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            if (!empty($arguments['tag'])) {
                $tagName = $arguments['tag'];
                $query->whereHas('teamTags', function ($q) use ($tagName) {
                    $q->where('name', $tagName)->orWhere('label', $tagName);
                });
            }

            if (!empty($arguments['search'])) {
                $query->where('title', 'LIKE', '%' . $arguments['search'] . '%');
            }

            $total = $query->count();
            $documents = $query->with('teamTags')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'documents' => $documents->map(fn($doc) => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'template_key' => $doc->template_key,
                    'folder_id' => $doc->document_folder_id,
                    'status' => $doc->status,
                    'has_output' => $doc->output_context_file_id !== null,
                    'tags' => $doc->teamTags->map(fn($t) => $t->label)->toArray(),
                    'share_url' => $doc->share_url,
                    'created_at' => $doc->created_at?->toIso8601String(),
                ])->toArray(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
