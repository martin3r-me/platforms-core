<?php

namespace Platform\Core\Livewire;

use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;

class InlineContextFiles extends Component
{
    use WithFileUploads;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public array $contextFiles = [];

    public $pendingFiles = [];
    public bool $generateVariants = true;
    public bool $showUploadZone = false;

    public function mount(?string $contextType = null, ?int $contextId = null): void
    {
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->loadFiles();
    }

    public function updatedPendingFiles(): void
    {
        $this->validate([
            'pendingFiles.*' => 'file|max:51200', // 50MB max
        ]);

        $this->uploadFiles();
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

        if (empty($this->pendingFiles)) {
            return;
        }

        $service = app(ContextFileService::class);
        $uploaded = [];

        foreach ($this->pendingFiles as $file) {
            try {
                $result = $service->uploadForContext(
                    $file,
                    $this->contextType,
                    $this->contextId,
                    [
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

        $this->pendingFiles = [];
        $this->showUploadZone = false;
        $this->loadFiles();

        if (count($uploaded) > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => count($uploaded) . ' Datei(en) erfolgreich hochgeladen.',
            ]);
        }
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
            $this->contextFiles = [];
            return;
        }

        $contextVariants = $this->getContextTypeVariants();

        $this->contextFiles = ContextFile::query()
            ->where(function ($q) use ($contextVariants) {
                foreach ($contextVariants as $variant) {
                    $q->orWhere('context_type', $variant);
                }
            })
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
                    'created_at' => $file->created_at->diffForHumans(),
                    'uploaded_by' => $file->user->name ?? 'Unbekannt',
                ];
            })
            ->toArray();
    }

    /**
     * Get both morph alias and full class name variants for context_type queries.
     */
    protected function getContextTypeVariants(): array
    {
        if (!$this->contextType) {
            return [];
        }

        $variants = [$this->contextType];

        // Also match the short class name (e.g. "RecApplicant") used by LLM tools
        $shortName = class_basename($this->contextType);
        if ($shortName !== $this->contextType) {
            $variants[] = $shortName;
        }

        if (!str_contains($this->contextType, '\\')) {
            // Morph alias given — resolve full class
            $fullClass = Relation::getMorphedModel($this->contextType);
            if ($fullClass && $fullClass !== $this->contextType) {
                $variants[] = $fullClass;
            }
        } else {
            // Full class given — resolve morph alias
            $morphMap = Relation::morphMap();
            $alias = array_search($this->contextType, $morphMap, true);
            if ($alias !== false) {
                $variants[] = $alias;
            }
        }

        return array_unique($variants);
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        return number_format($bytes / 1024, 0) . ' KB';
    }

    public function render()
    {
        return view('platform::livewire.inline-context-files');
    }
}
