<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\Tag;

class InlineTags extends Component
{
    public ?string $contextType = null;
    public ?int $contextId = null;

    // Zugeordnete Tags
    public array $assignedTags = [];

    // Farbe (unabhängig von Tags)
    public ?string $contextColor = null;

    // Tag-Input & Autocomplete
    public string $tagInput = '';
    public array $tagSuggestions = [];
    public bool $showSuggestions = false;

    // Inline-Edit: Tag bearbeiten
    public ?int $editingTagId = null;
    public string $editTagLabel = '';
    public ?string $editTagColor = null;

    // Farb-Picker
    public bool $showColorPicker = false;
    public ?string $newColor = null;

    public function mount(?string $contextType = null, ?int $contextId = null): void
    {
        $this->contextType = $contextType;
        $this->contextId = $contextId;

        if ($this->contextType && $this->contextId) {
            $this->loadAssignedTags();
            $this->loadColor();
        }
    }

    #[On('inline-tags:refresh')]
    public function refreshTags(array $payload = []): void
    {
        if (isset($payload['context_type'])) {
            $this->contextType = $payload['context_type'];
        }
        if (isset($payload['context_id'])) {
            $this->contextId = (int) $payload['context_id'];
        }

        $this->loadAssignedTags();
        $this->loadColor();
    }

    public function loadAssignedTags(): void
    {
        $this->assignedTags = [];

        if (!$this->contextType || !$this->contextId || !class_exists($this->contextType)) {
            return;
        }

        try {
            if (!Schema::hasTable('tags') || !Schema::hasTable('taggables')) {
                return;
            }

            $context = $this->contextType::find($this->contextId);
            if (!$context || !method_exists($context, 'tags')) {
                return;
            }

            // Team + persönliche Tags laden
            $teamTags = $context->teamTags()->get()->map(fn($tag) => [
                'id' => $tag->id,
                'label' => $tag->label,
                'name' => $tag->name,
                'color' => $tag->color,
                'is_personal' => false,
            ]);

            $personalTags = $context->personalTags()->get()->map(fn($tag) => [
                'id' => $tag->id,
                'label' => $tag->label,
                'name' => $tag->name,
                'color' => $tag->color,
                'is_personal' => true,
            ]);

            $this->assignedTags = $teamTags->merge($personalTags)->toArray();
        } catch (\Exception $e) {
            $this->assignedTags = [];
        }
    }

    public function loadColor(): void
    {
        $this->contextColor = null;

        if (!$this->contextType || !$this->contextId || !class_exists($this->contextType)) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                return;
            }

            if (!in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                return;
            }

            $this->contextColor = $context->color;
        } catch (\Exception $e) {
            // Ignoriere Fehler
        }
    }

    public function updatedTagInput(): void
    {
        $this->searchSuggestions();
    }

    public function searchSuggestions(): void
    {
        if (strlen($this->tagInput) < 1) {
            $this->tagSuggestions = [];
            $this->showSuggestions = false;
            return;
        }

        try {
            if (!Schema::hasTable('tags')) {
                $this->tagSuggestions = [];
                return;
            }

            $user = Auth::user();
            if (!$user) {
                $this->tagSuggestions = [];
                return;
            }

            $assignedIds = collect($this->assignedTags)->pluck('id')->toArray();

            $tags = Tag::query()
                ->availableForUser($user)
                ->whereNotIn('id', $assignedIds)
                ->where(function ($q) {
                    $q->where('label', 'like', '%' . $this->tagInput . '%')
                      ->orWhere('name', 'like', '%' . $this->tagInput . '%');
                })
                ->orderBy('label')
                ->limit(8)
                ->get()
                ->map(fn($tag) => [
                    'id' => $tag->id,
                    'label' => $tag->label,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                ])
                ->toArray();

            $this->tagSuggestions = $tags;
            $this->showSuggestions = true;
        } catch (\Exception $e) {
            $this->tagSuggestions = [];
        }
    }

    public function addTag(int $tagId): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        $context = $this->contextType::find($this->contextId);
        if (!$context || !method_exists($context, 'tag')) {
            return;
        }

        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }

        $context->tag($tag, false);

        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showSuggestions = false;
        $this->loadAssignedTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag zugeordnet.',
        ]);
    }

    public function createAndAddTag(): void
    {
        if (empty(trim($this->tagInput))) {
            return;
        }

        $user = Auth::user();
        $baseTeam = $user?->currentTeamRelation;
        if (!$baseTeam) {
            return;
        }

        $rootTeam = $baseTeam->getRootTeam();

        // Prüfe ob Tag bereits existiert
        $existingTag = Tag::query()
            ->where('label', trim($this->tagInput))
            ->where(function ($q) use ($rootTeam) {
                $q->where('team_id', $rootTeam->id)->orWhereNull('team_id');
            })
            ->first();

        if ($existingTag) {
            $this->addTag($existingTag->id);
            return;
        }

        // Neues Tag erstellen
        $tag = Tag::create([
            'label' => trim($this->tagInput),
            'name' => Str::slug(trim($this->tagInput)),
            'team_id' => $rootTeam->id,
            'created_by_user_id' => $user->id,
        ]);

        // Direkt zuordnen
        if ($this->contextType && $this->contextId && class_exists($this->contextType)) {
            $context = $this->contextType::find($this->contextId);
            if ($context && method_exists($context, 'tag')) {
                $context->tag($tag, false);
            }
        }

        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showSuggestions = false;
        $this->loadAssignedTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag erstellt und zugeordnet.',
        ]);
    }

    public function removeTag(int $tagId, bool $personal = false): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        $context = $this->contextType::find($this->contextId);
        if (!$context || !method_exists($context, 'untag')) {
            return;
        }

        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }

        $context->untag($tag, $personal);
        $this->loadAssignedTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag entfernt.',
        ]);
    }

    public function startEditTag(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }

        // Nur eigene Tags oder Team-Admin darf bearbeiten
        $user = Auth::user();
        if ($tag->created_by_user_id !== $user->id) {
            return;
        }

        $this->editingTagId = $tagId;
        $this->editTagLabel = $tag->label;
        $this->editTagColor = $tag->color;
    }

    public function saveEditTag(): void
    {
        if (!$this->editingTagId) {
            return;
        }

        $tag = Tag::find($this->editingTagId);
        if (!$tag) {
            $this->cancelEditTag();
            return;
        }

        $user = Auth::user();
        if ($tag->created_by_user_id !== $user->id) {
            $this->cancelEditTag();
            return;
        }

        $tag->label = trim($this->editTagLabel);
        $tag->name = Str::slug(trim($this->editTagLabel));
        $tag->color = $this->editTagColor;
        $tag->save();

        $this->cancelEditTag();
        $this->loadAssignedTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag aktualisiert.',
        ]);
    }

    public function cancelEditTag(): void
    {
        $this->editingTagId = null;
        $this->editTagLabel = '';
        $this->editTagColor = null;
    }

    public function deleteTag(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }

        $user = Auth::user();
        if ($tag->created_by_user_id !== $user->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Keine Berechtigung zum Löschen.',
            ]);
            return;
        }

        // Zuerst vom Kontext entfernen, dann löschen
        if ($this->contextType && $this->contextId) {
            $context = $this->contextType::find($this->contextId);
            if ($context && method_exists($context, 'untag')) {
                $context->untag($tag);
            }
        }

        // Tag komplett löschen (nur wenn nicht anderswo verwendet)
        $usageCount = \DB::table('taggables')
            ->where('tag_id', $tagId)
            ->count();

        if ($usageCount === 0) {
            $tag->delete();
        }

        $this->loadAssignedTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag gelöscht.',
        ]);
    }

    // === Farb-Management (unabhängig von Tags) ===

    public function toggleColorPicker(): void
    {
        $this->showColorPicker = !$this->showColorPicker;
        $this->newColor = $this->contextColor;
    }

    public function setColor(): void
    {
        if (!$this->contextType || !$this->contextId || !$this->newColor) {
            return;
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $this->newColor)) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                return;
            }

            if (!in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                return;
            }

            $context->setColor($this->newColor, false);
            $this->contextColor = $this->newColor;
            $this->showColorPicker = false;
            $this->newColor = null;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Farbe gesetzt.',
            ]);
        } catch (\Exception $e) {
            // Ignoriere Fehler
        }
    }

    public function removeColor(): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                return;
            }

            if (!in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                return;
            }

            $context->removeColor(false);
            $this->contextColor = null;
            $this->showColorPicker = false;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Farbe entfernt.',
            ]);
        } catch (\Exception $e) {
            // Ignoriere Fehler
        }
    }

    public function render()
    {
        return view('platform::livewire.inline-tags');
    }
}
