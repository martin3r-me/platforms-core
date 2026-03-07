<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;
use Platform\Core\Models\DocumentFolder;

class ManageDocumentFolderTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.folders.MANAGE';
    }

    public function getDescription(): string
    {
        return 'Erstellt, bearbeitet oder löscht Dokument-Ordner. Ordner können verschachtelt werden (parent_id). '
            . 'Mit action: "share" wird ein öffentlicher Share-Link für den Ordner erstellt (zeigt alle Dokumente). '
            . 'Mit action: "unshare" wird der Share-Link deaktiviert. '
            . 'Beim Löschen werden enthaltene Dokumente auf den Schreibtisch verschoben (nicht gelöscht).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Aktion: "create", "update", "delete", "share" (Share-Link erstellen), "unshare" (Share-Link deaktivieren)',
                ],
                'folder_id' => [
                    'type' => 'integer',
                    'description' => 'Für update/delete/share/unshare: Ordner-ID',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Ordnername (für create/update)',
                ],
                'parent_id' => [
                    'type' => 'integer',
                    'description' => 'Eltern-Ordner-ID (für create/update). null/0 = Root-Ebene.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Ordner-Beschreibung',
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'Farbe (z.B. "#3b82f6", "blue")',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $action = $arguments['action'] ?? 'create';

            return match ($action) {
                'create' => $this->create($arguments, $team->id, $context->user->id),
                'update' => $this->update($arguments, $team->id),
                'delete' => $this->delete($arguments, $team->id),
                'share' => $this->share($arguments, $team->id),
                'unshare' => $this->unshare($arguments, $team->id),
                default => ToolResult::error("Unbekannte Aktion: {$action}", 'VALIDATION_ERROR'),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function create(array $arguments, int $teamId, int $userId): ToolResult
    {
        if (empty($arguments['name'])) {
            return ToolResult::error('name ist required.', 'VALIDATION_ERROR');
        }

        $parentId = ($arguments['parent_id'] ?? null) ?: null;

        // Validate parent belongs to team
        if ($parentId) {
            $parent = DocumentFolder::where('id', $parentId)->where('team_id', $teamId)->first();
            if (!$parent) {
                return ToolResult::error('Eltern-Ordner nicht gefunden.', 'NOT_FOUND');
            }
        }

        $folder = DocumentFolder::create([
            'team_id' => $teamId,
            'parent_id' => $parentId,
            'name' => $arguments['name'],
            'description' => $arguments['description'] ?? null,
            'color' => $arguments['color'] ?? null,
            'created_by_user_id' => $userId,
        ]);

        return ToolResult::success([
            'id' => $folder->id,
            'name' => $folder->name,
            'path' => $folder->path,
            'parent_id' => $folder->parent_id,
            'action' => 'created',
        ]);
    }

    private function update(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['folder_id'])) {
            return ToolResult::error('folder_id ist required.', 'VALIDATION_ERROR');
        }

        $folder = DocumentFolder::where('id', (int) $arguments['folder_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$folder) {
            return ToolResult::error('Ordner nicht gefunden.', 'NOT_FOUND');
        }

        $updates = [];
        $changed = [];

        foreach (['name', 'description', 'color'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $updates[$field] = $arguments[$field];
                $changed[] = $field;
            }
        }

        // Move to different parent
        if (array_key_exists('parent_id', $arguments)) {
            $newParentId = ($arguments['parent_id'] ?? null) ?: null;

            // Prevent circular reference
            if ($newParentId === $folder->id) {
                return ToolResult::error('Ein Ordner kann nicht sein eigener Eltern-Ordner sein.', 'VALIDATION_ERROR');
            }

            if ($newParentId) {
                $parent = DocumentFolder::where('id', $newParentId)->where('team_id', $teamId)->first();
                if (!$parent) {
                    return ToolResult::error('Eltern-Ordner nicht gefunden.', 'NOT_FOUND');
                }

                // Check descendant IDs to prevent cycles
                $descendantIds = $folder->getDescendantIds();
                if (in_array($newParentId, $descendantIds)) {
                    return ToolResult::error('Zirkuläre Verschachtelung nicht erlaubt.', 'VALIDATION_ERROR');
                }
            }

            $updates['parent_id'] = $newParentId;
            $changed[] = 'parent_id';
        }

        if (empty($updates)) {
            return ToolResult::error('Keine Änderungen.', 'NO_CHANGES');
        }

        $folder->update($updates);

        return ToolResult::success([
            'id' => $folder->id,
            'name' => $folder->name,
            'path' => $folder->fresh()->path,
            'changed' => $changed,
            'action' => 'updated',
        ]);
    }

    private function delete(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['folder_id'])) {
            return ToolResult::error('folder_id ist required.', 'VALIDATION_ERROR');
        }

        $folder = DocumentFolder::where('id', (int) $arguments['folder_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$folder) {
            return ToolResult::error('Ordner nicht gefunden.', 'NOT_FOUND');
        }

        $name = $folder->name;

        // Move documents to desktop (unfiled)
        $movedDocs = Document::where('document_folder_id', $folder->id)->update(['document_folder_id' => null]);

        // Move subfolders to parent (or root)
        DocumentFolder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);

        $folder->delete();

        return ToolResult::success([
            'action' => 'deleted',
            'name' => $name,
            'documents_moved_to_desktop' => $movedDocs,
        ]);
    }

    private function share(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['folder_id'])) {
            return ToolResult::error('folder_id ist required.', 'VALIDATION_ERROR');
        }

        $folder = DocumentFolder::where('id', (int) $arguments['folder_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$folder) {
            return ToolResult::error('Ordner nicht gefunden.', 'NOT_FOUND');
        }

        $folder->ensureShareToken();

        return ToolResult::success([
            'id' => $folder->id,
            'name' => $folder->name,
            'share_url' => $folder->share_url,
            'action' => 'shared',
        ]);
    }

    private function unshare(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['folder_id'])) {
            return ToolResult::error('folder_id ist required.', 'VALIDATION_ERROR');
        }

        $folder = DocumentFolder::where('id', (int) $arguments['folder_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$folder) {
            return ToolResult::error('Ordner nicht gefunden.', 'NOT_FOUND');
        }

        $folder->update(['share_token' => null]);

        return ToolResult::success([
            'id' => $folder->id,
            'name' => $folder->name,
            'action' => 'unshared',
        ]);
    }
}
