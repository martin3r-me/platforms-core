<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;
use Platform\Core\Models\Tag;

class TagDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.TAG';
    }

    public function getDescription(): string
    {
        return 'Tags an Dokumenten verwalten: zuweisen, entfernen, oder alle Tags eines Dokuments abrufen. '
            . 'Tags können per ID oder Name referenziert werden. '
            . 'Neue Tags werden automatisch erstellt wenn sie noch nicht existieren (create_if_missing: true). '
            . 'Mit action: "list" werden alle verfügbaren Tags des Teams angezeigt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'description' => 'Aktion: "add" (Tag zuweisen), "remove" (Tag entfernen), "get" (Tags des Dokuments), "list" (alle Team-Tags). Standard: "add".',
                ],
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'Dokument-ID (für add/remove/get).',
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Tag-Namen oder IDs (für add/remove). Beispiel: ["wichtig", "q1-2026", "entwurf"].',
                ],
                'create_if_missing' => [
                    'type' => 'boolean',
                    'description' => 'Bei add: Nicht existierende Tags automatisch erstellen. Standard: true.',
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'Farbe für neu erstellte Tags (z.B. "#3b82f6"). Wird nur bei create_if_missing genutzt.',
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

            $action = $arguments['action'] ?? 'add';

            return match ($action) {
                'add' => $this->addTags($arguments, $team->id, $context->user->id),
                'remove' => $this->removeTags($arguments, $team->id),
                'get' => $this->getTags($arguments, $team->id),
                'list' => $this->listTeamTags($team->id),
                default => ToolResult::error("Unbekannte Aktion: {$action}. Erlaubt: add, remove, get, list.", 'VALIDATION_ERROR'),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function addTags(array $arguments, int $teamId, int $userId): ToolResult
    {
        if (empty($arguments['document_id'])) {
            return ToolResult::error('document_id ist required.', 'VALIDATION_ERROR');
        }
        if (empty($arguments['tags'])) {
            return ToolResult::error('tags ist required (Array von Tag-Namen).', 'VALIDATION_ERROR');
        }

        $document = Document::where('id', (int) $arguments['document_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$document) {
            return ToolResult::error('Dokument nicht gefunden.', 'NOT_FOUND');
        }

        $createIfMissing = $arguments['create_if_missing'] ?? true;
        $color = $arguments['color'] ?? null;
        $added = [];
        $created = [];

        foreach ($arguments['tags'] as $tagInput) {
            $tag = $this->resolveOrCreateTag($tagInput, $teamId, $userId, $createIfMissing, $color);

            if (!$tag) {
                continue;
            }

            if (!$document->hasTag($tag, false)) {
                $document->tags()->attach($tag->id, [
                    'user_id' => null,
                    'team_id' => $teamId,
                ]);
                $added[] = $tag->label;
            }

            if ($tag->wasRecentlyCreated) {
                $created[] = $tag->label;
            }
        }

        return ToolResult::success([
            'document_id' => $document->id,
            'added' => $added,
            'created' => $created,
            'all_tags' => $document->teamTags()->get()->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'color' => $t->color,
            ])->toArray(),
        ]);
    }

    private function removeTags(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['document_id'])) {
            return ToolResult::error('document_id ist required.', 'VALIDATION_ERROR');
        }
        if (empty($arguments['tags'])) {
            return ToolResult::error('tags ist required (Array von Tag-Namen).', 'VALIDATION_ERROR');
        }

        $document = Document::where('id', (int) $arguments['document_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$document) {
            return ToolResult::error('Dokument nicht gefunden.', 'NOT_FOUND');
        }

        $removed = [];

        foreach ($arguments['tags'] as $tagInput) {
            $tag = $this->findTag($tagInput, $teamId);
            if ($tag && $document->hasTag($tag)) {
                $document->tags()->detach($tag->id);
                $removed[] = $tag->label;
            }
        }

        return ToolResult::success([
            'document_id' => $document->id,
            'removed' => $removed,
            'remaining_tags' => $document->teamTags()->get()->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'color' => $t->color,
            ])->toArray(),
        ]);
    }

    private function getTags(array $arguments, int $teamId): ToolResult
    {
        if (empty($arguments['document_id'])) {
            return ToolResult::error('document_id ist required.', 'VALIDATION_ERROR');
        }

        $document = Document::where('id', (int) $arguments['document_id'])
            ->where('team_id', $teamId)
            ->first();

        if (!$document) {
            return ToolResult::error('Dokument nicht gefunden.', 'NOT_FOUND');
        }

        return ToolResult::success([
            'document_id' => $document->id,
            'title' => $document->title,
            'tags' => $document->teamTags()->get()->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'color' => $t->color,
            ])->toArray(),
        ]);
    }

    private function listTeamTags(int $teamId): ToolResult
    {
        $tags = Tag::availableForTeam($teamId)
            ->orderBy('label')
            ->get();

        return ToolResult::success([
            'tags' => $tags->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'color' => $t->color,
                'is_global' => $t->isGlobal(),
            ])->toArray(),
            'total' => $tags->count(),
        ]);
    }

    private function resolveOrCreateTag(string $input, int $teamId, int $userId, bool $createIfMissing, ?string $color): ?Tag
    {
        // Try by ID
        if (is_numeric($input)) {
            $tag = Tag::where('id', (int) $input)
                ->where(fn($q) => $q->where('team_id', $teamId)->orWhereNull('team_id'))
                ->first();
            if ($tag) {
                return $tag;
            }
        }

        // Try by name
        $tag = Tag::where('name', $input)
            ->where(fn($q) => $q->where('team_id', $teamId)->orWhereNull('team_id'))
            ->first();

        if ($tag) {
            return $tag;
        }

        // Try by label (case-insensitive)
        $tag = Tag::where('label', $input)
            ->where(fn($q) => $q->where('team_id', $teamId)->orWhereNull('team_id'))
            ->first();

        if ($tag) {
            return $tag;
        }

        if (!$createIfMissing) {
            return null;
        }

        // Create new tag
        return Tag::create([
            'name' => \Illuminate\Support\Str::slug($input),
            'label' => $input,
            'color' => $color,
            'team_id' => $teamId,
            'created_by_user_id' => $userId,
        ]);
    }

    private function findTag(string $input, int $teamId): ?Tag
    {
        if (is_numeric($input)) {
            return Tag::where('id', (int) $input)
                ->where(fn($q) => $q->where('team_id', $teamId)->orWhereNull('team_id'))
                ->first();
        }

        return Tag::where(fn($q) => $q->where('name', $input)->orWhere('label', $input))
            ->where(fn($q) => $q->where('team_id', $teamId)->orWhereNull('team_id'))
            ->first();
    }
}
