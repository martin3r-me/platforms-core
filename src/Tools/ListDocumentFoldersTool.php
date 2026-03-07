<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;
use Platform\Core\Models\DocumentFolder;

class ListDocumentFoldersTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.folders.LIST';
    }

    public function getDescription(): string
    {
        return 'Listet Dokument-Ordner des Teams auf. '
            . 'Ordner können verschachtelt sein (parent_id). Ordner ohne parent_id sind Root-Ordner. '
            . 'Dokumente ohne Ordner liegen auf dem "Schreibtisch" (unsortiert). '
            . 'Mit parent_id: Zeigt Unterordner eines bestimmten Ordners. '
            . 'Mit include_tree: true wird die komplette Baumstruktur zurückgegeben. '
            . 'Mit folder_id: Zeigt Inhalt eines Ordners (Unterordner + Dokumente).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Ordner-Inhalt anzeigen: Unterordner + Dokumente dieses Ordners. Ohne folder_id: Root-Ebene.',
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Unterordner dieses Eltern-Ordners anzeigen. null/0 = Root-Ordner.',
                ],
                'include_tree' => [
                    'type' => 'boolean',
                    'description' => 'Komplette Baumstruktur zurückgeben (alle Ebenen rekursiv). Standard: false.',
                ],
                'include_document_count' => [
                    'type' => 'boolean',
                    'description' => 'Anzahl Dokumente pro Ordner mitzählen. Standard: true.',
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

            $includeCount = $arguments['include_document_count'] ?? true;

            // Full tree mode
            if (!empty($arguments['include_tree'])) {
                return $this->getTree($team->id, $includeCount);
            }

            // Browse folder contents
            if (isset($arguments['folder_id'])) {
                return $this->getFolderContents((int) $arguments['folder_id'], $team->id, $includeCount);
            }

            // List folders at a specific level
            $parentId = $arguments['parent_id'] ?? null;
            if ($parentId === 0) $parentId = null;

            return $this->listLevel($parentId, $team->id, $includeCount);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function listLevel(?int $parentId, int $teamId, bool $includeCount): ToolResult
    {
        $query = DocumentFolder::where('team_id', $teamId);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        $folders = $query->orderBy('name')->get();

        // Desktop documents count (unfiled)
        $desktopCount = Document::where('team_id', $teamId)
            ->whereNull('document_folder_id')
            ->count();

        return ToolResult::success([
            'desktop_documents' => $desktopCount,
            'folders' => $folders->map(fn($f) => $this->formatFolder($f, $includeCount))->toArray(),
            'level' => $parentId ? 'subfolder' : 'root',
        ]);
    }

    private function getFolderContents(int $folderId, int $teamId, bool $includeCount): ToolResult
    {
        $folder = DocumentFolder::where('id', $folderId)
            ->where('team_id', $teamId)
            ->first();

        if (!$folder) {
            return ToolResult::error('Ordner nicht gefunden.', 'NOT_FOUND');
        }

        $subfolders = DocumentFolder::where('team_id', $teamId)
            ->where('parent_id', $folderId)
            ->orderBy('name')
            ->get();

        $documents = Document::where('team_id', $teamId)
            ->where('document_folder_id', $folderId)
            ->orderByDesc('created_at')
            ->get();

        return ToolResult::success([
            'folder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'share_url' => $folder->share_url,
            ],
            'subfolders' => $subfolders->map(fn($f) => $this->formatFolder($f, $includeCount))->toArray(),
            'documents' => $documents->map(fn($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'template_key' => $doc->template_key,
                'status' => $doc->status,
                'share_url' => $doc->share_url,
                'created_at' => $doc->created_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }

    private function getTree(int $teamId, bool $includeCount): ToolResult
    {
        $roots = DocumentFolder::where('team_id', $teamId)
            ->whereNull('parent_id')
            ->with('childrenRecursive')
            ->orderBy('name')
            ->get();

        $desktopCount = Document::where('team_id', $teamId)
            ->whereNull('document_folder_id')
            ->count();

        return ToolResult::success([
            'desktop_documents' => $desktopCount,
            'tree' => $roots->map(fn($f) => $this->formatFolderTree($f, $includeCount))->toArray(),
        ]);
    }

    private function formatFolder(DocumentFolder $folder, bool $includeCount): array
    {
        $result = [
            'id' => $folder->id,
            'name' => $folder->name,
            'path' => $folder->path,
            'color' => $folder->color,
            'parent_id' => $folder->parent_id,
            'share_url' => $folder->share_url,
        ];

        if ($includeCount) {
            $result['document_count'] = $folder->documents()->count();
            $result['subfolder_count'] = $folder->children()->count();
        }

        return $result;
    }

    private function formatFolderTree(DocumentFolder $folder, bool $includeCount): array
    {
        $result = $this->formatFolder($folder, $includeCount);
        $result['children'] = $folder->childrenRecursive
            ->map(fn($child) => $this->formatFolderTree($child, $includeCount))
            ->toArray();

        return $result;
    }
}
