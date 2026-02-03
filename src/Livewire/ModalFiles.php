<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\ContextFileReference;
use Platform\Core\Services\ContextFileService;

class ModalFiles extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public $files = [];
    public bool $keepOriginal = false; // Original behalten bei Bildern
    public bool $generateVariants = true; // Varianten generieren (Standard: true)

    public $uploadedFiles = [];

    // Picker Mode Properties
    public string $mode = 'upload';        // 'upload' | 'picker'
    public string $activeTab = 'upload';   // 'upload' | 'browse'
    public bool $multiple = true;
    public array $selectedFiles = [];
    public ?string $callback = null;

    // Reference Mode Properties (Modal erstellt Referenz selbst)
    public ?string $referenceType = null;    // z.B. LocationGalleryBoard::class
    public ?int $referenceId = null;         // z.B. board_id
    public ?int $selectedFileForVariant = null;
    public ?int $selectedVariantId = null;

    // Assign Mode Properties (existierende Referenz aktualisieren)
    public ?int $assignReferenceId = null;   // ID der zu befüllenden Referenz

    public function mount(): void
    {
        // Initialisierung
    }

    #[On('files')]
    public function setContext(array $payload = []): void
    {
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
    }

    #[On('files:open')]
    public function open(): void
    {
        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        $this->mode = 'upload';
        $this->activeTab = 'upload';
        $this->loadFiles();
        $this->open = true;
    }

    #[On('files:picker')]
    public function openPicker(array $payload = []): void
    {
        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        $this->mode = 'picker';
        $this->referenceType = $payload['reference_type'] ?? null;
        $this->referenceId = isset($payload['reference_id']) ? (int) $payload['reference_id'] : null;
        $this->multiple = $payload['multiple'] ?? true;
        $this->callback = $payload['callback'] ?? null;
        $this->selectedFiles = [];
        $this->selectedFileForVariant = null;
        $this->selectedVariantId = null;
        $this->activeTab = 'browse';  // Im Picker standardmaessig "Durchsuchen"
        $this->loadFiles();
        $this->open = true;
    }

    #[On('files:assign')]
    public function openAssignMode(array $payload = []): void
    {
        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        $this->mode = 'assign';
        $this->assignReferenceId = $payload['reference_id'] ?? null;
        $this->selectedFileForVariant = null;
        $this->selectedVariantId = null;
        $this->activeTab = 'browse';
        $this->loadFiles();
        $this->open = true;
    }

    public function close(): void
    {
        // Event für Refresh dispatchen wenn wir im assign/picker mode waren
        if ($this->mode === 'assign' && $this->assignReferenceId) {
            $this->dispatch('files:reference-updated', [
                'reference_id' => $this->assignReferenceId,
            ]);
        } elseif ($this->referenceType && $this->referenceId) {
            $this->dispatch('files:reference-created', [
                'reference_type' => $this->referenceType,
                'reference_id' => $this->referenceId,
            ]);
        }

        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId', 'files', 'uploadedFiles', 'keepOriginal', 'generateVariants', 'mode', 'activeTab', 'multiple', 'selectedFiles', 'callback', 'referenceType', 'referenceId', 'selectedFileForVariant', 'selectedVariantId', 'assignReferenceId');
    }

    public function toggleFileSelection(int $fileId): void
    {
        if (in_array($fileId, $this->selectedFiles)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$fileId]));
        } else {
            if ($this->multiple) {
                $this->selectedFiles[] = $fileId;
            } else {
                $this->selectedFiles = [$fileId];
            }
        }
    }

    public function confirmSelection(): void
    {
        // Handle assign mode (existierende Referenz aktualisieren)
        if ($this->mode === 'assign') {
            $this->assignToReference();
            return;
        }

        // Handle picker mode with referenceType (neue Referenz erstellen)
        if ($this->referenceType) {
            $this->createReference();
            return;
        }

        // Handle picker mode without referenceType (einfache Datei-Auswahl)
        $files = ContextFile::whereIn('id', $this->selectedFiles)
            ->with('variants')
            ->get();

        $this->dispatch('files:selected', [
            'files' => $files->map(fn($f) => [
                'id' => $f->id,
                'token' => $f->token,
                'original_name' => $f->original_name,
                'url' => $f->url,
                'thumbnail' => $f->variants()->where('variant_type', 'thumbnail_4_3')->first()?->url
                    ?? $f->variants()->where('variant_type', 'like', 'thumbnail_%')->first()?->url
                    ?? $f->url,
            ])->toArray(),
            'callback' => $this->callback,
        ]);

        $this->close();
    }

    /**
     * Auswahl abbrechen und zurück zur Datei-Übersicht
     */
    public function cancelSelection(): void
    {
        $this->selectedFileForVariant = null;
        $this->selectedVariantId = null;
    }

    /**
     * Bild für Varianten-Auswahl selektieren
     */
    public function selectFileForVariant(int $fileId): void
    {
        $this->selectedFileForVariant = $fileId;
        $this->selectedVariantId = null;
    }

    /**
     * Variante wählen (null = Original)
     */
    public function selectVariant(?int $variantId): void
    {
        $this->selectedVariantId = $variantId;
    }

    /**
     * Varianten-Auswahl abbrechen
     */
    public function cancelVariantSelection(): void
    {
        $this->selectedFileForVariant = null;
        $this->selectedVariantId = null;
    }

    /**
     * Referenz DIREKT im Modal erstellen
     */
    public function createReference(): void
    {
        if (!$this->referenceType || !$this->referenceId || !$this->selectedFileForVariant) {
            return;
        }

        $file = ContextFile::find($this->selectedFileForVariant);
        if (!$file) {
            return;
        }

        // Prüfen ob bereits existiert (File + Variante Kombination)
        $exists = ContextFileReference::where('context_file_id', $file->id)
            ->where('context_file_variant_id', $this->selectedVariantId)
            ->where('reference_type', $this->referenceType)
            ->where('reference_id', $this->referenceId)
            ->exists();

        if (!$exists) {
            ContextFileReference::create([
                'context_file_id' => $file->id,
                'context_file_variant_id' => $this->selectedVariantId,
                'reference_type' => $this->referenceType,
                'reference_id' => $this->referenceId,
                'meta' => ['title' => $file->original_name],
            ]);
        }

        // Event: Komponente soll neu laden
        $this->dispatch('files:reference-created', [
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
        ]);

        // Reset für nächste Auswahl (oder schließen wenn single)
        $this->selectedFileForVariant = null;
        $this->selectedVariantId = null;

        if (!$this->multiple) {
            $this->close();
        }
    }

    /**
     * Existierende Referenz aktualisieren (assign mode)
     */
    public function assignToReference(): void
    {
        if (!$this->assignReferenceId || !$this->selectedFileForVariant) {
            return;
        }

        $reference = ContextFileReference::find($this->assignReferenceId);
        if (!$reference) {
            return;
        }

        $file = ContextFile::find($this->selectedFileForVariant);
        if (!$file) {
            return;
        }

        $reference->update([
            'context_file_id' => $file->id,
            'context_file_variant_id' => $this->selectedVariantId,
            'meta' => array_merge($reference->meta ?? [], ['title' => $file->original_name]),
        ]);

        $this->dispatch('files:reference-updated', [
            'reference_id' => $this->assignReferenceId,
        ]);

        $this->close();
    }

    /**
     * Referenz löschen (aus dem Modal heraus)
     */
    public function deleteReference(int $referenceId): void
    {
        ContextFileReference::where('id', $referenceId)
            ->where('reference_type', $this->referenceType)
            ->where('reference_id', $this->referenceId)
            ->delete();

        $this->dispatch('files:reference-deleted', [
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
        ]);
    }

    public function uploadFiles(): void
    {
        if (!$this->contextType || !$this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kein Kontext ausgewählt.',
            ]);
            return;
        }

        if (empty($this->files)) {
            return;
        }

        $service = app(ContextFileService::class);
        $uploaded = [];

        foreach ($this->files as $file) {
            try {
                $result = $service->uploadForContext(
                    $file,
                    $this->contextType,
                    $this->contextId,
                    [
                        'keep_original' => $this->keepOriginal,
                        'generate_variants' => $this->generateVariants,
                    ]
                );
                $uploaded[] = $result;
            } catch (\Exception $e) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Fehler beim Hochladen: ' . $e->getMessage(),
                ]);
            }
        }

        $this->files = [];
        $this->loadFiles();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => count($uploaded) . ' Datei(en) erfolgreich hochgeladen.',
        ]);
    }

    public function deleteFile(int $fileId): void
    {
        $service = app(ContextFileService::class);
        
        try {
            $service->delete($fileId);
            $this->loadFiles();
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Datei gelöscht.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Fehler beim Löschen: ' . $e->getMessage(),
            ]);
        }
    }

    protected function loadFiles(): void
    {
        if (!$this->contextType || !$this->contextId) {
            $this->uploadedFiles = [];
            return;
        }

        $this->uploadedFiles = \Platform\Core\Models\ContextFile::query()
            ->where('context_type', $this->contextType)
            ->where('context_id', $this->contextId)
            ->with(['variants', 'user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'token' => $file->token,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'width' => $file->width,
                    'height' => $file->height,
                    'url' => $file->url,
                    'download_url' => $file->download_url,
                    'is_image' => $file->isImage(),
                    'thumbnail' => $file->variants()->where('variant_type', 'thumbnail_4_3')->first()?->url 
                        ?? $file->variants()->where('variant_type', 'like', 'thumbnail_%')->first()?->url 
                        ?? null,
                    'variants' => $file->variants->mapWithKeys(function($v) {
                        return [$v->variant_type => [
                            'id' => $v->id,
                            'type' => $v->variant_type,
                            'url' => $v->url,
                            'width' => $v->width,
                            'height' => $v->height,
                        ]];
                    })->toArray(),
                    'created_at' => $file->created_at->diffForHumans(),
                    'uploaded_by' => $file->user->name ?? 'Unbekannt',
                ];
            })
            ->toArray();
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
        return view('platform::livewire.modal-files');
    }
}

