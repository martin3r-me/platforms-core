<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\Tag;
use Illuminate\Support\Str;

class ModalTagging extends Component
{
    public bool $open = false;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public string $activeTab = 'all'; // 'all', 'team', 'personal'
    
    // Tag-Management
    public array $teamTags = [];
    public array $personalTags = [];
    public array $availableTags = [];
    
    // Neues Tag erstellen
    public string $newTagLabel = '';
    public ?string $newTagColor = null;
    public bool $newTagIsPersonal = false;
    
    // Filter
    public string $searchQuery = '';

    public function mount(): void
    {
        // Initialisierung
    }

    #[On('tagging')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType && $this->contextId) {
            $this->loadTags();
        }
    }

    #[On('tagging:open')]
    public function open(): void
    {
        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        // Reset
        $this->searchQuery = '';
        $this->newTagLabel = '';
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;

        // Tags laden
        if ($this->contextType && $this->contextId) {
            $this->loadTags();
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId', 'searchQuery', 'newTagLabel', 'newTagColor', 'newTagIsPersonal');
    }

    public function loadTags(): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        if (!class_exists($this->contextType)) {
            return;
        }

        $context = $this->contextType::find($this->contextId);
        if (!$context || !method_exists($context, 'tags')) {
            return;
        }

        $user = Auth::user();
        $team = $user->currentTeamRelation;

        // Team-Tags laden
        $this->teamTags = $context->teamTags()
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                ];
            })
            ->toArray();

        // Persönliche Tags laden
        $this->personalTags = $context->personalTags()
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                ];
            })
            ->toArray();

        // Verfügbare Tags laden (Team + global, noch nicht zugeordnet)
        $assignedTagIds = collect($this->teamTags)->pluck('id')
            ->merge(collect($this->personalTags)->pluck('id'))
            ->unique()
            ->toArray();

        $this->availableTags = Tag::query()
            ->availableForUser($user)
            ->whereNotIn('id', $assignedTagIds)
            ->when($this->searchQuery, function ($q) {
                $q->where(function ($query) {
                    $query->where('label', 'like', '%' . $this->searchQuery . '%')
                          ->orWhere('name', 'like', '%' . $this->searchQuery . '%');
                });
            })
            ->orderBy('label')
            ->get()
            ->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                ];
            })
            ->toArray();
    }

    public function toggleTag(int $tagId, bool $personal = false): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        if (!class_exists($this->contextType)) {
            return;
        }

        $context = $this->contextType::find($this->contextId);
        if (!$context || !method_exists($context, 'tags')) {
            return;
        }

        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }

        // Prüfe ob Tag bereits zugeordnet ist
        $hasTag = $context->hasTag($tag, $personal);

        if ($hasTag) {
            // Tag entfernen
            $context->untag($tag, $personal);
        } else {
            // Tag zuordnen
            $context->tag($tag, $personal);
        }

        // Tags neu laden
        $this->loadTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $hasTag ? 'Tag entfernt.' : 'Tag zugeordnet.',
        ]);
    }

    public function createTag(): void
    {
        $this->validate([
            'newTagLabel' => ['required', 'string', 'max:255'],
            'newTagColor' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = Auth::user();
        $baseTeam = $user->currentTeamRelation;

        if (!$baseTeam) {
            $this->addError('newTagLabel', 'Kein Team-Kontext vorhanden.');
            return;
        }

        // Verwende Root-Team (Parent-Team), nicht Child-Team
        $rootTeam = $baseTeam->getRootTeam();

        $tagData = [
            'label' => trim($this->newTagLabel),
            'name' => Str::slug($this->newTagLabel),
            'color' => $this->newTagColor,
            'created_by_user_id' => $user->id,
        ];

        // Wenn persönlich: team_id = null, sonst Root-Team-ID setzen
        if (!$this->newTagIsPersonal) {
            $tagData['team_id'] = $rootTeam->id;
        }

        $tag = Tag::create($tagData);

        // Tag direkt zuordnen
        if ($this->contextType && $this->contextId && class_exists($this->contextType)) {
            $context = $this->contextType::find($this->contextId);
            if ($context && method_exists($context, 'tag')) {
                $context->tag($tag, $this->newTagIsPersonal);
            }
        }

        // Reset Formular
        $this->newTagLabel = '';
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;

        // Tags neu laden
        $this->loadTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag erstellt und zugeordnet.',
        ]);
    }

    public function deleteTag(int $tagId): void
    {
        $tag = Tag::find($tagId);
        if (!$tag) {
            return;
        }

        $user = Auth::user();
        $team = $user->currentTeamRelation;

        // Prüfe Berechtigung: Nur wenn User Tag erstellt hat oder Team-Admin
        if ($tag->created_by_user_id !== $user->id) {
            // Optional: Team-Admin-Check hier
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung, dieses Tag zu löschen.',
            ]);
            return;
        }

        // Prüfe ob Tag noch verwendet wird
        $usageCount = \DB::table('taggables')
            ->where('tag_id', $tagId)
            ->count();

        if ($usageCount > 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Tag wird noch verwendet und kann nicht gelöscht werden.',
            ]);
            return;
        }

        $tag->delete();

        $this->loadTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag gelöscht.',
        ]);
    }

    public function updatedSearchQuery(): void
    {
        $this->loadTags();
    }

    public function getContextLabelProperty(): ?string
    {
        if (!$this->contextType || !$this->contextId) {
            return null;
        }

        if (!class_exists($this->contextType)) {
            return null;
        }

        $context = $this->contextType::find($this->contextId);
        if (!$context) {
            return null;
        }

        // Versuche verschiedene Methoden für Label
        if (method_exists($context, 'getDisplayName')) {
            return $context->getDisplayName();
        }

        if (isset($context->title)) {
            return $context->title;
        }

        if (isset($context->name)) {
            return $context->name;
        }

        return class_basename($this->contextType) . ' #' . $this->contextId;
    }

    public function getContextBreadcrumbProperty(): array
    {
        if (!$this->contextType || !$this->contextId) {
            return [];
        }

        return [
            [
                'type' => class_basename($this->contextType),
                'label' => $this->contextLabel,
            ],
        ];
    }

    public function render()
    {
        return view('platform::livewire.modal-tagging');
    }
}

