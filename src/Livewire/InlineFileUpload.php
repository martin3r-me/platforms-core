<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;

/**
 * Inline File-Upload Livewire-Komponente mit Kontext-Awareness.
 *
 * Ermöglicht direkten Datei-Upload an Ort und Stelle, ohne das
 * globale fileContext Modal zu verwenden. Jede Instanz verwaltet
 * ihren eigenen State isoliert (kein State-Bleeding).
 *
 * Verwendung:
 *   <livewire:core.inline-file-upload
 *       :context-type="$contextType"
 *       :context-id="$contextId"
 *       :multiple="false"
 *       :key="'inline-upload-' . $uniqueId"
 *   />
 *
 * Für Extra-Feld-Integration:
 *   <livewire:core.inline-file-upload
 *       :context-type="$contextType"
 *       :context-id="$contextId"
 *       :multiple="$isMultiple"
 *       :field-id="$field['id']"
 *       :key="'ef-upload-' . $field['id']"
 *   />
 */
class InlineFileUpload extends Component
{
    use WithFileUploads;

    // Kontext
    public ?string $contextType = null;
    public ?int $contextId = null;

    // Konfiguration
    public bool $multiple = false;
    public ?int $fieldId = null; // Extra-Feld-ID (wenn als Extra-Feld verwendet)
    public ?string $label = null;
    public bool $showLabel = true;
    public string $accept = ''; // z.B. 'image/*,.pdf'
    public int $maxFileSizeMb = 20;
    public bool $generateVariants = true;

    // Upload State
    public $pendingFiles = [];
    public bool $isUploading = false;
    public int $uploadProgress = 0;

    // Drag & Drop State (client-side via Alpine)
    // Kein Livewire-Property nötig – wird per Alpine x-data verwaltet

    // Bereits hochgeladene / zugeordnete Dateien
    public array $uploadedFileIds = [];
    public array $uploadedFilesData = [];

    // UI State
    public bool $showDropZone = false;

    // Missing files tracking
    public array $missingFileIds = [];

    public function mount(
        ?string $contextType = null,
        ?int $contextId = null,
        bool $multiple = false,
        ?int $fieldId = null,
        ?string $label = null,
        bool $showLabel = true,
        string $accept = '',
        int $maxFileSizeMb = 20,
        bool $generateVariants = true,
        array $existingFileIds = [],
    ): void {
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->multiple = $multiple;
        $this->fieldId = $fieldId;
        $this->label = $label;
        $this->showLabel = $showLabel;
        $this->accept = $accept;
        $this->maxFileSizeMb = $maxFileSizeMb;
        $this->generateVariants = $generateVariants;

        if (!empty($existingFileIds)) {
            $this->uploadedFileIds = $existingFileIds;
            $this->loadUploadedFiles();
        }
    }

    /**
     * Setzt bestehende Datei-IDs von außen (z.B. aus Extra-Feld-Werten).
     */
    #[On('inline-file-upload:set-files.{fieldId}')]
    public function setFiles(array $payload): void
    {
        $fileIds = $payload['file_ids'] ?? [];
        $this->uploadedFileIds = array_map('intval', $fileIds);
        $this->loadUploadedFiles();
    }

    /**
     * Setzt den Kontext dynamisch (für Fälle, wo ID erst nach Speichern bekannt ist).
     */
    #[On('inline-file-upload:set-context.{fieldId}')]
    public function setContext(array $payload): void
    {
        $this->contextType = $payload['context_type'] ?? $this->contextType;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : $this->contextId;
    }

    /**
     * Livewire File-Upload Hook: Dateien wurden ausgewählt
     */
    public function updatedPendingFiles(): void
    {
        $this->validate([
            'pendingFiles.*' => [
                'file',
                'max:' . ($this->maxFileSizeMb * 1024),
            ],
        ], [
            'pendingFiles.*.file' => 'Bitte nur gültige Dateien hochladen.',
            'pendingFiles.*.max' => 'Maximale Dateigröße: ' . $this->maxFileSizeMb . ' MB.',
        ]);

        $this->uploadFiles();
    }

    /**
     * Upload der ausgewählten Dateien
     */
    public function uploadFiles(): void
    {
        if (!$this->contextType || !$this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kein Kontext für den Upload vorhanden.',
            ]);
            return;
        }

        if (!Auth::check() || !Auth::user()->currentTeamRelation) {
            return;
        }

        if (empty($this->pendingFiles)) {
            return;
        }

        $this->isUploading = true;
        $service = app(ContextFileService::class);
        $newIds = [];

        $filesToUpload = is_array($this->pendingFiles) ? $this->pendingFiles : [$this->pendingFiles];

        foreach ($filesToUpload as $file) {
            try {
                $result = $service->uploadForContext(
                    $file,
                    $this->contextType,
                    $this->contextId,
                    [
                        'generate_variants' => $this->generateVariants,
                    ]
                );
                $newIds[] = $result['id'];
            } catch (\Exception $e) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Fehler beim Hochladen: ' . $e->getMessage(),
                ]);
            }
        }

        // Zur Liste hinzufügen (oder ersetzen bei single)
        if ($this->multiple) {
            $this->uploadedFileIds = array_values(array_unique(
                array_merge($this->uploadedFileIds, $newIds)
            ));
        } else {
            // Single: nur die letzte Datei behalten
            $this->uploadedFileIds = !empty($newIds) ? [end($newIds)] : $this->uploadedFileIds;
        }

        $this->pendingFiles = [];
        $this->isUploading = false;
        $this->uploadProgress = 0;

        $this->loadUploadedFiles();
        $this->emitValueChanged();

        if (!empty($newIds)) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => count($newIds) . ' Datei(en) hochgeladen.',
            ]);
        }
    }

    /**
     * Datei aus der Liste entfernen
     */
    public function removeFile(int $fileId): void
    {
        $this->uploadedFileIds = array_values(
            array_filter($this->uploadedFileIds, fn($id) => $id !== $fileId)
        );
        $this->uploadedFilesData = array_values(
            array_filter($this->uploadedFilesData, fn($f) => $f['id'] !== $fileId)
        );

        $this->emitValueChanged();
    }

    /**
     * Datei austauschen (remove + re-open upload zone)
     */
    public function replaceFile(int $fileId): void
    {
        // Alte Datei entfernen
        $this->removeFile($fileId);
        // Drop-Zone anzeigen für neue Datei
        $this->showDropZone = true;
    }

    /**
     * Reihenfolge ändern (Drag & Drop Sortierung)
     */
    public function reorderFiles(array $orderedIds): void
    {
        $orderedIds = array_map('intval', $orderedIds);
        // Nur IDs behalten, die auch tatsächlich vorhanden sind
        $this->uploadedFileIds = array_values(
            array_intersect($orderedIds, $this->uploadedFileIds)
        );
        $this->loadUploadedFiles();
        $this->emitValueChanged();
    }

    /**
     * Lädt die Metadaten der hochgeladenen Dateien
     */
    protected function loadUploadedFiles(): void
    {
        $this->missingFileIds = [];

        if (empty($this->uploadedFileIds)) {
            $this->uploadedFilesData = [];
            return;
        }

        $files = ContextFile::whereIn('id', $this->uploadedFileIds)
            ->with('variants')
            ->get()
            ->keyBy('id');

        // Track missing files and build data array
        $validFileIds = [];
        $this->uploadedFilesData = collect($this->uploadedFileIds)
            ->map(function ($id) use ($files, &$validFileIds) {
                $file = $files->get($id);
                if (!$file) {
                    $this->missingFileIds[] = $id;
                    return null;
                }
                $validFileIds[] = $id;
                return [
                    'id' => $file->id,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'file_size' => $file->file_size,
                    'is_image' => $file->isImage(),
                    'url' => $file->url,
                    'thumbnail_url' => $file->thumbnail?->url ?? ($file->isImage() ? $file->url : null),
                    'width' => $file->width,
                    'height' => $file->height,
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        // Auto-clean invalid file IDs and notify parent
        if (!empty($this->missingFileIds)) {
            $this->uploadedFileIds = $validFileIds;
            $this->emitValueChanged();
        }
    }

    /**
     * Benachrichtigt die Parent-Komponente über Wertänderungen.
     * Für Extra-Feld-Integration: emittiert die File-IDs.
     */
    protected function emitValueChanged(): void
    {
        $value = $this->multiple
            ? $this->uploadedFileIds
            : ($this->uploadedFileIds[0] ?? null);

        $this->dispatch('inline-file-upload:changed', [
            'field_id' => $this->fieldId,
            'value' => $value,
            'file_ids' => $this->uploadedFileIds,
        ]);
    }

    /**
     * Formatiert Dateigröße für Anzeige
     */
    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }

    public function render()
    {
        return view('platform::livewire.inline-file-upload');
    }
}
