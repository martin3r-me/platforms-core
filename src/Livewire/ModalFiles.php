<?php

namespace Platform\Core\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
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

        $this->loadFiles();
        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->open = false;
        $this->reset('contextType', 'contextId', 'files', 'uploadedFiles', 'keepOriginal', 'generateVariants');
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
                    'thumbnail' => $file->thumbnail?->url ?? null,
                    'variants' => $file->variants->map(fn($v) => [
                        'type' => $v->variant_type,
                        'url' => $v->url,
                        'width' => $v->width,
                        'height' => $v->height,
                    ])->toArray(),
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

