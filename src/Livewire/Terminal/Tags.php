<?php

namespace Platform\Core\Livewire\Terminal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Core\Models\Tag;

class Tags extends Component
{
    use WithTerminalContext;

    public string $taggingTab = 'tags';
    public string $tagFilter = 'all';
    public array $teamTags = [];
    public array $personalTags = [];
    public array $availableTags = [];
    public array $allTags = [];
    public array $allColors = [];
    public string $tagInput = '';
    public array $tagSuggestions = [];
    public bool $showTagSuggestions = false;
    public ?string $newTagColor = null;
    public bool $newTagIsPersonal = false;
    public ?string $contextColor = null;
    public ?string $newContextColor = null;

    protected function onContextChanged(): void
    {
        $this->taggingTab = ($this->contextType && $this->contextId) ? 'tags' : 'overview';
        $this->tagFilter = 'all';
        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;
        $this->newContextColor = null;

        if ($this->contextType && $this->contextId) {
            $this->loadTags();
            $this->loadColor();
        }
        $this->loadAllTags();
        $this->loadAllColors();
    }

    public function openTagsApp(): void
    {
        $this->taggingTab = ($this->contextType && $this->contextId) ? 'tags' : 'overview';
        $this->tagFilter = 'all';
        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;
        $this->newContextColor = null;

        if ($this->contextType && $this->contextId) {
            $this->loadTags();
            $this->loadColor();
        }
        $this->loadAllTags();
        $this->loadAllColors();
    }

    public function loadTags(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        try {
            if (! Schema::hasTable('tags') || ! Schema::hasTable('taggables')) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context || ! method_exists($context, 'tags')) {
                return;
            }

            $user = auth()->user();
            if (! $user) {
                return;
            }

            $this->teamTags = $context->teamTags()
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                ])
                ->toArray();

            $this->personalTags = $context->personalTags()
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                ])
                ->toArray();

            $assignedTagIds = collect($this->teamTags)->pluck('id')
                ->merge(collect($this->personalTags)->pluck('id'))
                ->unique()
                ->toArray();

            $this->availableTags = Tag::query()
                ->availableForUser($user)
                ->whereNotIn('id', $assignedTagIds)
                ->orderBy('label')
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                ])
                ->toArray();
        } catch (\Exception $e) {
            $this->teamTags = [];
            $this->personalTags = [];
            $this->availableTags = [];
        }
    }

    public function loadColor(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->contextColor = null;
            return;
        }

        if (! class_exists($this->contextType)) {
            $this->contextColor = null;
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                $this->contextColor = null;
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->contextColor = null;
                return;
            }

            $this->contextColor = $context->color;
        } catch (\Exception $e) {
            $this->contextColor = null;
        }
    }

    public function toggleTag(int $tagId, bool $personal = false): void
    {
        if (! $this->contextType || ! $this->contextId || ! class_exists($this->contextType)) {
            return;
        }

        $context = $this->contextType::find($this->contextId);
        if (! $context || ! method_exists($context, 'tags')) {
            return;
        }

        $tag = Tag::find($tagId);
        if (! $tag) {
            return;
        }

        $hasTag = $context->hasTag($tag, $personal);

        if ($hasTag) {
            $context->untag($tag, $personal);
        } else {
            $context->tag($tag, $personal);
        }

        $this->loadTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $hasTag ? 'Tag entfernt.' : 'Tag zugeordnet.',
        ]);
    }

    public function setColor(): void
    {
        if (! $this->contextType || ! $this->contextId || ! $this->newContextColor) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $this->newContextColor)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ungültige Farbangabe.']);
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieses Model unterstützt keine Farben.']);
                return;
            }

            $context->setColor($this->newContextColor, false);
            $this->contextColor = $this->newContextColor;
            $this->newContextColor = null;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Farbe gesetzt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Setzen der Farbe.']);
        }
    }

    public function setColorPreset(string $color): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ungültige Farbangabe.']);
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieses Model unterstützt keine Farben.']);
                return;
            }

            $context->setColor($color, false);
            $this->contextColor = $color;
            $this->newContextColor = null;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Farbe gesetzt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Setzen der Farbe.']);
        }
    }

    public function removeColor(): void
    {
        if (! $this->contextType || ! $this->contextId || ! class_exists($this->contextType)) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (! $context) {
                return;
            }

            if (! in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', ['type' => 'error', 'message' => 'Dieses Model unterstützt keine Farben.']);
                return;
            }

            $context->removeColor(false);
            $this->contextColor = null;

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Farbe entfernt.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Entfernen der Farbe.']);
        }
    }

    public function updatedTagInput(): void
    {
        $this->searchTagSuggestions();
    }

    public function searchTagSuggestions(): void
    {
        if (empty($this->tagInput)) {
            $this->tagSuggestions = [];
            $this->showTagSuggestions = false;
            return;
        }

        try {
            if (! Schema::hasTable('tags')) {
                $this->tagSuggestions = [];
                return;
            }

            $user = auth()->user();
            if (! $user) {
                $this->tagSuggestions = [];
                return;
            }

            $assignedTagIds = collect($this->teamTags)->pluck('id')
                ->merge(collect($this->personalTags)->pluck('id'))
                ->unique()
                ->toArray();

            $tags = Tag::query()
                ->availableForUser($user)
                ->whereNotIn('id', $assignedTagIds)
                ->where(function ($q) {
                    $q->where('label', 'like', '%' . $this->tagInput . '%')
                      ->orWhere('name', 'like', '%' . $this->tagInput . '%');
                })
                ->orderBy('label')
                ->limit(10)
                ->get()
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'label' => $tag->label,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                ])
                ->toArray();

            $this->tagSuggestions = $tags;
            $this->showTagSuggestions = count($tags) > 0 || strlen($this->tagInput) >= 2;
        } catch (\Exception $e) {
            $this->tagSuggestions = [];
        }
    }

    public function addTagFromSuggestion(int $tagId, bool $personal = false): void
    {
        $this->toggleTag($tagId, $personal);
        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;
    }

    public function createAndAddTag(): void
    {
        if (empty(trim($this->tagInput))) {
            return;
        }

        $user = auth()->user();
        $baseTeam = $user->currentTeamRelation;

        if (! $baseTeam) {
            return;
        }

        $rootTeam = $baseTeam->getRootTeam();

        $existingTag = Tag::query()
            ->where('label', trim($this->tagInput))
            ->where(function ($q) use ($rootTeam) {
                if ($this->newTagIsPersonal) {
                    $q->whereNull('team_id');
                } else {
                    $q->where('team_id', $rootTeam->id)->orWhereNull('team_id');
                }
            })
            ->first();

        if ($existingTag) {
            $this->toggleTag($existingTag->id, $this->newTagIsPersonal);
            $this->tagInput = '';
            $this->tagSuggestions = [];
            $this->showTagSuggestions = false;
            return;
        }

        $tagData = [
            'label' => trim($this->tagInput),
            'name' => Str::slug(trim($this->tagInput)),
            'color' => $this->newTagColor,
            'created_by_user_id' => $user->id,
        ];

        if (! $this->newTagIsPersonal) {
            $tagData['team_id'] = $rootTeam->id;
        }

        $tag = Tag::create($tagData);

        if ($this->contextType && $this->contextId && class_exists($this->contextType)) {
            $context = $this->contextType::find($this->contextId);
            if ($context && method_exists($context, 'tag')) {
                $context->tag($tag, $this->newTagIsPersonal);
            }
        }

        $this->loadTags();
        $this->loadAllTags();

        $this->tagInput = '';
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Tag erstellt und zugeordnet.']);
    }

    public function deleteTag(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if (! $tag) {
            return;
        }

        $user = auth()->user();

        if ($tag->created_by_user_id !== $user->id) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Sie haben keine Berechtigung, dieses Tag zu löschen.']);
            return;
        }

        $usageCount = DB::table('taggables')->where('tag_id', $tagId)->count();

        if ($usageCount > 0) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Tag wird noch verwendet und kann nicht gelöscht werden.']);
            return;
        }

        $tag->delete();

        $this->loadTags();
        $this->loadAllTags();

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Tag gelöscht.']);
    }

    public function loadAllTags(): void
    {
        try {
            if (! Schema::hasTable('tags') || ! Schema::hasTable('taggables')) {
                $this->allTags = [];
                return;
            }

            $user = auth()->user();
            if (! $user) {
                $this->allTags = [];
                return;
            }

            $tags = Tag::query()
                ->availableForUser($user)
                ->with('createdBy')
                ->orderBy('label')
                ->get();

            $this->allTags = $tags->map(function ($tag) {
                $teamCount = DB::table('taggables')->where('tag_id', $tag->id)->whereNull('user_id')->count();
                $personalCount = DB::table('taggables')->where('tag_id', $tag->id)->whereNotNull('user_id')->count();

                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                    'is_global' => $tag->isGlobal(),
                    'total_count' => $teamCount + $personalCount,
                    'team_count' => $teamCount,
                    'personal_count' => $personalCount,
                    'created_at' => $tag->created_at?->format('d.m.Y'),
                    'created_by' => $tag->createdBy?->name ?? 'Unbekannt',
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->allTags = [];
        }
    }

    public function loadAllColors(): void
    {
        try {
            if (! Schema::hasTable('colorables')) {
                $this->allColors = [];
                return;
            }

            $user = auth()->user();
            if (! $user) {
                $this->allColors = [];
                return;
            }

            $colors = DB::table('colorables')
                ->select('color', DB::raw('COUNT(*) as total_count'))
                ->groupBy('color')
                ->orderBy('total_count', 'desc')
                ->get();

            $this->allColors = $colors->map(function ($color) {
                $teamCount = DB::table('colorables')->where('color', $color->color)->whereNull('user_id')->count();
                $personalCount = DB::table('colorables')->where('color', $color->color)->whereNotNull('user_id')->count();

                return [
                    'color' => $color->color,
                    'total_count' => $color->total_count,
                    'team_count' => $teamCount,
                    'personal_count' => $personalCount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->allColors = [];
        }
    }

    public function render()
    {
        return view('platform::livewire.terminal.tags');
    }
}
