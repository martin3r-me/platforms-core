<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Core\Models\Tag;
use Illuminate\Support\Str;

class ModalTagging extends Component
{
    public bool $open = false;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public string $activeTab = 'tags'; // 'tags', 'color', 'overview'
    public string $tagFilter = 'all'; // 'all', 'team', 'personal' (für Tags-Tab)
    
    // Tag-Management
    public array $teamTags = [];
    public array $personalTags = [];
    public array $availableTags = [];
    public array $allTags = []; // Für Übersicht-Tab
    public array $allColors = []; // Für Übersicht-Tab
    
    // Neues Tag erstellen
    public string $newTagLabel = '';
    public ?string $newTagColor = null;
    public bool $newTagIsPersonal = false;
    
    // Farbe ohne Tags
    public ?string $contextColor = null;
    public ?string $newContextColor = null;
    
    // Filter
    public string $searchQuery = '';
    
    // Tag Autocomplete
    public string $tagInput = '';
    public array $tagSuggestions = [];
    public bool $showTagSuggestions = false;

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
            $this->loadColor();
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
        $this->newContextColor = null;
        $this->tagInput = '';
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;
        $this->activeTab = $this->contextType && $this->contextId ? 'tags' : 'overview';
        $this->tagFilter = 'all';

        // Tags und Farbe laden
        if ($this->contextType && $this->contextId) {
            $this->loadTags();
            $this->loadColor();
        }

        // Alle Tags und Farben für Übersicht laden
        $this->loadAllTags();
        $this->loadAllColors();

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId', 'searchQuery', 'newTagLabel', 'newTagColor', 'newTagIsPersonal', 'contextColor', 'newContextColor', 'activeTab', 'tagFilter', 'tagInput', 'tagSuggestions', 'showTagSuggestions');
    }

    public function loadTags(): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        if (!class_exists($this->contextType)) {
            return;
        }

        // Prüfe ob Datenbank-Tabellen existieren (für Bootstrap/Migration)
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('tags') || 
                !\Illuminate\Support\Facades\Schema::hasTable('taggables')) {
                return;
            }
        } catch (\Exception $e) {
            // Wenn Schema nicht verfügbar ist, überspringen
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context || !method_exists($context, 'tags')) {
                return;
            }

            $user = Auth::user();
            if (!$user) {
                return;
            }

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
        } catch (\Exception $e) {
            // Wenn Datenbank-Fehler (z.B. beim Bootstrap), überspringen
            $this->teamTags = [];
            $this->personalTags = [];
            $this->availableTags = [];
        }
    }

    public function loadAllTags(): void
    {
        try {
            // Prüfe ob Datenbank-Tabellen existieren
            if (!\Illuminate\Support\Facades\Schema::hasTable('tags') || 
                !\Illuminate\Support\Facades\Schema::hasTable('taggables')) {
                $this->allTags = [];
                return;
            }

            $user = Auth::user();
            if (!$user) {
                $this->allTags = [];
                return;
            }

            // Alle verfügbaren Tags laden (Team + Global)
            $tags = Tag::query()
                ->availableForUser($user)
                ->with('createdBy')
                ->orderBy('label')
                ->get();

            $this->allTags = $tags->map(function ($tag) {
                // Zähle Team-Tags und persönliche Tags separat
                $teamCount = DB::table('taggables')
                    ->where('tag_id', $tag->id)
                    ->whereNull('user_id')
                    ->count();

                $personalCount = DB::table('taggables')
                    ->where('tag_id', $tag->id)
                    ->whereNotNull('user_id')
                    ->count();

                $totalCount = $teamCount + $personalCount;

                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'label' => $tag->label,
                    'color' => $tag->color,
                    'is_team_tag' => $tag->isTeamTag(),
                    'is_global' => $tag->isGlobal(),
                    'total_count' => $totalCount,
                    'team_count' => $teamCount,
                    'personal_count' => $personalCount,
                    'created_at' => $tag->created_at?->format('d.m.Y'),
                    'created_by' => $tag->createdBy?->name ?? 'Unbekannt',
                ];
            })->toArray();
        } catch (\Exception $e) {
            // Wenn Datenbank-Fehler, überspringen
            $this->allTags = [];
        }
    }

    public function loadAllColors(): void
    {
        try {
            // Prüfe ob Datenbank-Tabellen existieren
            if (!\Illuminate\Support\Facades\Schema::hasTable('colorables')) {
                $this->allColors = [];
                return;
            }

            $user = Auth::user();
            if (!$user) {
                $this->allColors = [];
                return;
            }

            // Alle verwendeten Farben aus colorables laden
            $colors = DB::table('colorables')
                ->select('color', DB::raw('COUNT(*) as total_count'))
                ->groupBy('color')
                ->orderBy('total_count', 'desc')
                ->get();

            $this->allColors = $colors->map(function ($color) {
                // Zähle Team-Farben und persönliche Farben separat
                $teamCount = DB::table('colorables')
                    ->where('color', $color->color)
                    ->whereNull('user_id')
                    ->count();

                $personalCount = DB::table('colorables')
                    ->where('color', $color->color)
                    ->whereNotNull('user_id')
                    ->count();

                return [
                    'color' => $color->color,
                    'total_count' => $color->total_count,
                    'team_count' => $teamCount,
                    'personal_count' => $personalCount,
                ];
            })->toArray();
        } catch (\Exception $e) {
            // Wenn Datenbank-Fehler, überspringen
            $this->allColors = [];
        }
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

    public function loadColor(): void
    {
        if (!$this->contextType || !$this->contextId) {
            $this->contextColor = null;
            return;
        }

        if (!class_exists($this->contextType)) {
            $this->contextColor = null;
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                $this->contextColor = null;
                return;
            }

            // Prüfe ob Model HasColors Trait verwendet
            if (!in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->contextColor = null;
                return;
            }

            // Verwende das color-Attribut aus dem Trait
            $this->contextColor = $context->color;
        } catch (\Exception $e) {
            $this->contextColor = null;
        }
    }

    public function setColor(): void
    {
        if (!$this->contextType || !$this->contextId || !$this->newContextColor) {
            return;
        }

        if (!class_exists($this->contextType)) {
            return;
        }

        // Validiere Farbe
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $this->newContextColor)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Ungültige Farbangabe.',
            ]);
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                return;
            }

            // Prüfe ob Model HasColors Trait verwendet
            if (!in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Dieses Model unterstützt keine Farben.',
                ]);
                return;
            }

            // Verwende die setColor-Methode aus dem Trait
            $context->setColor($this->newContextColor, false); // false = Team-Farbe
            $this->contextColor = $this->newContextColor;
            $this->newContextColor = null;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Farbe gesetzt.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Setzen der Farbe.',
            ]);
        }
    }

    public function removeColor(): void
    {
        if (!$this->contextType || !$this->contextId) {
            return;
        }

        if (!class_exists($this->contextType)) {
            return;
        }

        try {
            $context = $this->contextType::find($this->contextId);
            if (!$context) {
                return;
            }

            // Prüfe ob Model HasColors Trait verwendet
            if (!in_array(\Platform\Core\Traits\HasColors::class, class_uses_recursive($context))) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Dieses Model unterstützt keine Farben.',
                ]);
                return;
            }

            // Verwende die removeColor-Methode aus dem Trait
            $context->removeColor(false); // false = Team-Farbe
            $this->contextColor = null;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Farbe entfernt.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Entfernen der Farbe.',
            ]);
        }
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
        $this->loadAllTags();

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
        $this->loadAllTags();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag gelöscht.',
        ]);
    }

    public function updatedSearchQuery(): void
    {
        if ($this->activeTab === 'tags') {
            $this->loadTags();
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
            if (!\Illuminate\Support\Facades\Schema::hasTable('tags')) {
                $this->tagSuggestions = [];
                return;
            }

            $user = Auth::user();
            if (!$user) {
                $this->tagSuggestions = [];
                return;
            }

            // Lade bereits zugeordnete Tag-IDs
            $assignedTagIds = collect($this->teamTags)->pluck('id')
                ->merge(collect($this->personalTags)->pluck('id'))
                ->unique()
                ->toArray();

            // Suche nach Tags (nicht zugeordnet)
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
                ->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'label' => $tag->label,
                        'name' => $tag->name,
                        'color' => $tag->color,
                        'is_team_tag' => $tag->isTeamTag(),
                    ];
                })
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

        // Prüfe ob Tag bereits existiert
        $user = Auth::user();
        $baseTeam = $user->currentTeamRelation;

        if (!$baseTeam) {
            $this->addError('tagInput', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $rootTeam = $baseTeam->getRootTeam();

        // Prüfe ob Tag mit diesem Label bereits existiert
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
            // Tag existiert bereits, einfach zuordnen
            $this->toggleTag($existingTag->id, $this->newTagIsPersonal);
            $this->tagInput = '';
            $this->tagSuggestions = [];
            $this->showTagSuggestions = false;
            return;
        }

        // Neues Tag erstellen
        $tagData = [
            'label' => trim($this->tagInput),
            'name' => \Illuminate\Support\Str::slug(trim($this->tagInput)),
            'color' => $this->newTagColor,
            'created_by_user_id' => $user->id,
        ];

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

        // Tags neu laden
        $this->loadTags();
        $this->loadAllTags();

        // Reset
        $this->tagInput = '';
        $this->newTagColor = null;
        $this->newTagIsPersonal = false;
        $this->tagSuggestions = [];
        $this->showTagSuggestions = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Tag erstellt und zugeordnet.',
        ]);
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

