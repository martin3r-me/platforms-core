<?php

namespace Platform\Core\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\ContextFileReference;
use Platform\Core\Services\ContextFileService;

class Files extends Component
{
    use WithFileUploads;
    use WithTerminalContext;

    public string $filesFilter = 'all'; // all | images | documents
    public $pendingFiles = [];

    // ── File Picker/Assign ───────────────────────────────────
    public bool $filePickerActive = false;
    public bool $filePickerMultiple = true;
    public ?string $filePickerCallback = null;
    public array $filePickerSelected = [];
    public ?string $filePickerReferenceType = null;
    public ?int $filePickerReferenceId = null;
    public ?int $filePickerAssignReferenceId = null;

    protected function onContextChanged(): void
    {
        unset($this->contextFiles);
        $this->resetFilePicker();
    }

    /**
     * Receive file picker activation from parent Terminal.
     */
    #[On('terminal-file-picker-open')]
    public function activateFilePicker(
        bool $multiple = true,
        ?string $callback = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $assignReferenceId = null,
    ): void {
        $this->filePickerActive = true;
        $this->filePickerMultiple = $multiple;
        $this->filePickerCallback = $callback;
        $this->filePickerReferenceType = $referenceType;
        $this->filePickerReferenceId = $referenceId;
        $this->filePickerAssignReferenceId = $assignReferenceId;
        $this->filePickerSelected = [];
    }

    #[Computed]
    public function contextFiles(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        return ContextFile::where('context_type', $this->contextType)
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
                    'url' => $file->url,
                    'download_url' => $file->download_url,
                    'is_image' => $file->isImage(),
                    'thumbnail' => $file->variants()->where('variant_type', 'thumbnail_4_3')->first()?->url
                        ?? $file->variants()->where('variant_type', 'like', 'thumbnail_%')->first()?->url
                        ?? null,
                    'created_at' => $file->created_at->diffForHumans(),
                    'uploaded_by' => $file->user?->name ?? 'Unbekannt',
                ];
            })
            ->toArray();
    }

    public function uploadContextFiles(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            return;
        }

        if (empty($this->pendingFiles)) {
            return;
        }

        $service = app(ContextFileService::class);

        foreach ($this->pendingFiles as $file) {
            try {
                $service->uploadForContext(
                    $file,
                    $this->contextType,
                    $this->contextId,
                    [
                        'keep_original' => false,
                        'generate_variants' => true,
                    ]
                );
            } catch (\Exception $e) {
                // Continue with remaining files
            }
        }

        $this->pendingFiles = [];
        unset($this->contextFiles);

        // Notify parent to refresh its badge count
        $this->dispatch('terminal-files-changed');
    }

    public function deleteContextFile(int $fileId): void
    {
        $file = ContextFile::find($fileId);
        if (! $file) {
            unset($this->contextFiles);
            return;
        }

        if ($this->contextType && $this->contextId) {
            if ($file->context_type !== $this->contextType || $file->context_id !== $this->contextId) {
                return;
            }
        }

        try {
            $service = app(ContextFileService::class);
            $service->delete($fileId);

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Datei gelöscht.']);
        } catch (\Exception $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()]);
        }

        unset($this->contextFiles);

        // Notify parent to refresh its badge count
        $this->dispatch('terminal-files-changed');
    }

    public function toggleFilePickerSelection(int $fileId): void
    {
        if (in_array($fileId, $this->filePickerSelected)) {
            $this->filePickerSelected = array_values(array_diff($this->filePickerSelected, [$fileId]));
        } else {
            if ($this->filePickerMultiple) {
                $this->filePickerSelected[] = $fileId;
            } else {
                $this->filePickerSelected = [$fileId];
            }
        }
    }

    public function confirmFilePicker(): void
    {
        if (empty($this->filePickerSelected)) {
            return;
        }

        // Assign mode: update existing reference
        if ($this->filePickerAssignReferenceId) {
            $reference = ContextFileReference::find($this->filePickerAssignReferenceId);
            if ($reference) {
                $file = ContextFile::find($this->filePickerSelected[0]);
                if ($file) {
                    $reference->update([
                        'context_file_id' => $file->id,
                        'context_file_variant_id' => null,
                        'meta' => array_merge($reference->meta ?? [], ['title' => $file->original_name]),
                    ]);
                    $this->dispatch('terminal:files:reference-updated', [
                        'reference_id' => $this->filePickerAssignReferenceId,
                    ]);
                }
            }
            $this->resetFilePicker();

            return;
        }

        // Picker mode with reference_type: create ContextFileReference(s)
        if ($this->filePickerReferenceType && $this->filePickerReferenceId) {
            foreach ($this->filePickerSelected as $fileId) {
                $file = ContextFile::find($fileId);
                if (! $file) {
                    continue;
                }

                $exists = ContextFileReference::where('context_file_id', $file->id)
                    ->where('context_file_variant_id', null)
                    ->where('reference_type', $this->filePickerReferenceType)
                    ->where('reference_id', $this->filePickerReferenceId)
                    ->exists();

                if (! $exists) {
                    ContextFileReference::create([
                        'context_file_id' => $file->id,
                        'context_file_variant_id' => null,
                        'reference_type' => $this->filePickerReferenceType,
                        'reference_id' => $this->filePickerReferenceId,
                        'meta' => ['title' => $file->original_name],
                    ]);
                }
            }

            $this->dispatch('terminal:files:reference-created', [
                'reference_type' => $this->filePickerReferenceType,
                'reference_id' => $this->filePickerReferenceId,
            ]);
            $this->resetFilePicker();

            return;
        }

        // Simple picker mode: return selected file data
        $files = ContextFile::whereIn('id', $this->filePickerSelected)
            ->with('variants')
            ->get();

        $this->dispatch('terminal:files:picked', [
            'files' => $files->map(fn ($f) => [
                'id' => $f->id,
                'token' => $f->token,
                'original_name' => $f->original_name,
                'url' => $f->url,
                'thumbnail' => $f->variants()->where('variant_type', 'thumbnail_4_3')->first()?->url
                    ?? $f->variants()->where('variant_type', 'like', 'thumbnail_%')->first()?->url
                    ?? $f->url,
            ])->toArray(),
            'callback' => $this->filePickerCallback,
        ]);
        $this->resetFilePicker();
    }

    public function cancelFilePicker(): void
    {
        $this->resetFilePicker();
    }

    protected function resetFilePicker(): void
    {
        $this->filePickerActive = false;
        $this->filePickerMultiple = true;
        $this->filePickerCallback = null;
        $this->filePickerSelected = [];
        $this->filePickerReferenceType = null;
        $this->filePickerReferenceId = null;
        $this->filePickerAssignReferenceId = null;
    }

    public function render()
    {
        return view('platform::livewire.terminal.files');
    }
}
