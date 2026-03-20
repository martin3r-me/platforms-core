<?php

namespace Platform\Core\Livewire\Public;

use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Core\Livewire\Concerns\WithExtraFields;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\CorePublicFormLink;
use Platform\Core\Services\ContextFileService;

class PublicExtraFieldForm extends Component
{
    use WithExtraFields;
    use WithFileUploads;

    public string $token = '';
    public string $state = 'loading';

    public ?int $linkId = null;
    public ?int $modelId = null;
    public ?string $modelClass = null;

    public int $totalFields = 0;
    public int $filledFields = 0;

    public array $pendingFileUploads = [];
    public array $uploadedFileData = [];

    /** All field values (including already-filled) for condition evaluation */
    public array $allFieldValues = [];

    /** All field definitions (including already-filled) for condition evaluation */
    public array $allFieldDefinitions = [];

    private function getModel()
    {
        if (!$this->modelId || !$this->modelClass) {
            return null;
        }
        return ($this->modelClass)::find($this->modelId);
    }

    private function getLink(): ?CorePublicFormLink
    {
        if (!$this->linkId) {
            return null;
        }
        return CorePublicFormLink::find($this->linkId);
    }

    public function mount(string $token): void
    {
        $this->token = $token;

        $link = CorePublicFormLink::where('token', $token)->first();

        if (!$link) {
            $this->state = 'notFound';
            return;
        }

        if (!$link->isValid()) {
            $this->state = 'notActive';
            return;
        }

        $model = $link->linkable;

        if (!$model) {
            $this->state = 'notFound';
            return;
        }

        $this->linkId = $link->id;
        $this->modelId = $model->id;
        $this->modelClass = get_class($model);
        $this->loadFormFields($model);
    }

    private function loadFormFields($model): void
    {
        $this->loadExtraFieldValues($model);

        // Store ALL definitions and values for condition evaluation (before filtering)
        $this->allFieldDefinitions = $this->extraFieldDefinitions;
        $this->allFieldValues = $this->extraFieldValues;

        // Filter: only show unfilled fields
        $filtered = [];
        $this->totalFields = 0;
        $this->filledFields = 0;

        foreach ($this->extraFieldDefinitions as $field) {
            $this->totalFields++;
            $value = $this->extraFieldValues[$field['id']] ?? null;
            // Phone-Felder: gefüllt wenn e164 vorhanden (= erfolgreich validiert)
            if ($field['type'] === 'phone') {
                $isFilled = is_array($value) && !empty($value['e164'] ?? null);
            } elseif ($field['type'] === 'address') {
                $isFilled = is_array($value) && !empty($value['street'] ?? null) && !empty($value['city'] ?? null);
            } else {
                $isFilled = $value !== null && $value !== '' && $value !== [];
            }

            if ($isFilled) {
                $this->filledFields++;
            } else {
                $filtered[] = $field;
            }
        }

        // Overwrite definitions with only unfilled fields
        $this->extraFieldDefinitions = $filtered;

        // Reset values to only contain filtered field IDs
        $filteredValues = [];
        foreach ($filtered as $field) {
            $filteredValues[$field['id']] = $this->extraFieldValues[$field['id']] ?? null;
        }
        $this->extraFieldValues = $filteredValues;
        $this->originalExtraFieldValues = $filteredValues;

        $this->loadUploadedFileData();

        if (empty($filtered)) {
            $this->state = 'completed';
        } else {
            $this->state = 'form';
        }
    }

    private function loadUploadedFileData(): void
    {
        $fileIds = [];
        foreach ($this->extraFieldDefinitions as $field) {
            if ($field['type'] !== 'file') continue;
            $val = $this->extraFieldValues[$field['id']] ?? null;
            if (is_array($val)) {
                $fileIds = array_merge($fileIds, $val);
            } elseif ($val) {
                $fileIds[] = $val;
            }
        }
        if (empty($fileIds)) {
            $this->uploadedFileData = [];
            return;
        }
        $files = ContextFile::whereIn('id', $fileIds)->with('variants')->get()->keyBy('id');
        $this->uploadedFileData = [];
        foreach ($files as $file) {
            $this->uploadedFileData[$file->id] = [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'file_size' => $file->file_size,
                'mime_type' => $file->mime_type,
                'is_image' => $file->isImage(),
                'url' => $file->url,
                'thumbnail_url' => $file->thumbnail?->url,
            ];
        }
    }

    public function updatedPendingFileUploads(): void
    {
        $model = $this->getModel();
        if (!$model) return;

        $service = app(ContextFileService::class);

        foreach ($this->pendingFileUploads as $fieldId => $file) {
            if (!$file) continue;
            $field = collect($this->extraFieldDefinitions)->firstWhere('id', $fieldId);
            if (!$field || $field['type'] !== 'file') continue;

            $isMultiple = $field['options']['multiple'] ?? false;
            $files = is_array($file) ? $file : [$file];

            foreach ($files as $uploadedFile) {
                $result = $service->uploadForContext(
                    $uploadedFile,
                    get_class($model),
                    $model->id,
                    ['team_id' => $model->team_id, 'user_id' => null]
                );
                if ($isMultiple) {
                    $current = $this->extraFieldValues[$fieldId] ?? [];
                    $current = is_array($current) ? $current : [];
                    $current[] = $result['id'];
                    $this->extraFieldValues[$fieldId] = $current;
                } else {
                    $this->extraFieldValues[$fieldId] = $result['id'];
                }
            }
        }

        $this->pendingFileUploads = [];
        $this->loadUploadedFileData();
    }

    public function removeFile(int $fieldId, int $fileId): void
    {
        $current = $this->extraFieldValues[$fieldId] ?? null;
        if (is_array($current)) {
            $this->extraFieldValues[$fieldId] = array_values(array_filter($current, fn($id) => $id != $fileId));
        } else {
            $this->extraFieldValues[$fieldId] = null;
        }
        unset($this->uploadedFileData[$fileId]);
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 0) . ' KB';
        return $bytes . ' B';
    }

    public function save(): void
    {
        $this->validate($this->getExtraFieldValidationRules(), $this->getExtraFieldValidationMessages());

        $model = $this->getModel();
        if (!$model) {
            $this->state = 'notFound';
            return;
        }

        $this->saveExtraFieldValues($model);

        // Sync form values into allFieldValues for condition evaluation
        foreach ($this->extraFieldValues as $fieldId => $value) {
            $this->allFieldValues[$fieldId] = $value;
        }

        // Update progress if the model supports it
        if (method_exists($model, 'calculateProgress')) {
            $model->progress = $model->calculateProgress();
            $model->save();
        }

        // AutoPilot: check if all required fields are now complete
        if (method_exists($model, 'checkAutoPilotCompletion')) {
            $model->checkAutoPilotCompletion();
        }

        // Recount filled fields
        $this->filledFields = 0;
        $allDefinitions = $model->getExtraFieldsWithLabels();
        $this->totalFields = 0;
        $remainingUnfilled = 0;

        foreach ($allDefinitions as $field) {
            $this->totalFields++;
            if ($field['type'] === 'phone') {
                $isFilled = is_array($field['value']) && !empty($field['value']['e164'] ?? null);
            } else {
                $isFilled = $field['value'] !== null && $field['value'] !== '' && $field['value'] !== [];
            }
            if ($isFilled) {
                $this->filledFields++;
            } else {
                $remainingUnfilled++;
            }
        }

        $this->state = $remainingUnfilled === 0 ? 'completed' : 'saved';
    }

    public function continueEditing(): void
    {
        $model = $this->getModel();
        if (!$model) {
            $this->state = 'notFound';
            return;
        }
        $this->loadFormFields($model);
    }

    public function render()
    {
        return view('platform::livewire.public.public-extra-field-form')
            ->layout('platform::layouts.guest');
    }
}
