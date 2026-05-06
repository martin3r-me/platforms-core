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

    /**
     * Pflicht/Optional-getrennte Counter — nur befuellt wenn das Linkable-
     * Modell das Akkordeon-Layout opted-in hat (siehe usesAccordionLayout()).
     */
    public int $requiredTotal = 0;
    public int $requiredFilled = 0;
    public int $optionalTotal = 0;
    public int $optionalFilled = 0;

    /**
     * Field-IDs aufgeteilt fuer Akkordeon-Render. Werden nur befuellt wenn
     * Akkordeon-Layout aktiv ist; sonst leer und Blade rendert klassisch.
     *
     * @var int[]
     */
    public array $openFieldIds = [];
    public array $accordionFieldIds = [];

    /**
     * True wenn das Linkable-Modell Akkordeon-Layout opted-in hat.
     * Steuert ob das Blade die Aufteilung rendert oder klassisch.
     */
    public bool $useAccordionLayout = false;

    /**
     * Phase-Order des aktuellen Modells (fuer required_in_phase_orders).
     * Wird in der Render-Logik fuers Pflicht-Sternchen genutzt.
     */
    public ?int $currentPhaseOrder = null;

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

        $this->useAccordionLayout = $this->usesAccordionLayout($model);
        $this->currentPhaseOrder = $this->resolveCurrentPhaseOrder($model);
        $useAccordion = $this->useAccordionLayout;
        $currentPhaseOrder = $this->currentPhaseOrder;

        // Filter: only show unfilled fields — Felder mit options.always_show_in_form
        // bleiben sichtbar auch wenn schon befuellt (zum Bestaetigen / Korrigieren).
        $filtered = [];
        $this->totalFields = 0;
        $this->filledFields = 0;

        foreach ($this->extraFieldDefinitions as $field) {
            $this->totalFields++;
            $isFilled = $this->isFieldValueFilled($field);

            if ($isFilled) {
                $this->filledFields++;
                if ($this->isAlwaysShownInForm($field)) {
                    $filtered[] = $field; // bleibt sichtbar trotz "filled"
                }
            } else {
                $filtered[] = $field;
            }
        }

        // Overwrite definitions with only unfilled fields (+ always_show)
        $this->extraFieldDefinitions = $filtered;

        // Reset values to only contain filtered field IDs (Werte fuer always_show
        // bleiben dabei erhalten als Prefill)
        $filteredValues = [];
        foreach ($filtered as $field) {
            $filteredValues[$field['id']] = $this->extraFieldValues[$field['id']] ?? null;
        }
        $this->extraFieldValues = $filteredValues;
        $this->originalExtraFieldValues = $filteredValues;

        // Akkordeon-Layout: aufteilen in openFields (Pflicht + bedingt sichtbar)
        // und accordionFields (rein optional). Nur wenn das Linkable-Modell
        // das opted-in hat. Sonst bleiben die Listen leer und das Blade
        // rendert klassisch.
        $this->openFieldIds = [];
        $this->accordionFieldIds = [];
        $this->requiredTotal = 0;
        $this->requiredFilled = 0;
        $this->optionalTotal = 0;
        $this->optionalFilled = 0;

        if ($useAccordion) {
            foreach ($filtered as $field) {
                $isFilled = $this->isFieldValueFilled($field);
                $isRequired = $this->isFieldEffectivelyRequired($field, $currentPhaseOrder);
                $isConditional = $this->isFieldConditionallyVisible($field);

                if ($isRequired) {
                    $this->requiredTotal++;
                    if ($isFilled) {
                        $this->requiredFilled++;
                    }
                    $this->openFieldIds[] = $field['id'];
                } elseif ($isConditional) {
                    // bedingt-sichtbares Feld zaehlt als optional aber
                    // bleibt im Hauptbereich sichtbar
                    $this->optionalTotal++;
                    if ($isFilled) {
                        $this->optionalFilled++;
                    }
                    $this->openFieldIds[] = $field['id'];
                } else {
                    $this->optionalTotal++;
                    if ($isFilled) {
                        $this->optionalFilled++;
                    }
                    $this->accordionFieldIds[] = $field['id'];
                }
            }
        }

        $this->loadUploadedFileData();

        if (empty($filtered)) {
            $this->state = 'completed';
        } else {
            $this->state = 'form';
        }
    }

    /**
     * Prueft ob ein Field-Wert als befuellt gilt (typ-aware).
     */
    protected function isFieldValueFilled(array $field): bool
    {
        $value = $this->extraFieldValues[$field['id']] ?? null;
        if ($field['type'] === 'phone') {
            return is_array($value) && !empty(trim($value['raw'] ?? ''));
        }
        if ($field['type'] === 'address') {
            return is_array($value) && !empty($value['street'] ?? null) && !empty($value['city'] ?? null);
        }
        if ($field['type'] === 'date') {
            return is_array($value)
                ? (($value['day'] ?? '') !== '' && ($value['month'] ?? '') !== '' && ($value['year'] ?? '') !== '')
                : ($value !== null && $value !== '');
        }
        return $value !== null && $value !== '' && $value !== [];
    }

    /**
     * Effektiv-pflicht: is_mandatory ODER is_required ODER der Phase-Override
     * options.required_in_phase_orders greift fuer die aktuelle Phase-Order.
     *
     * Wird sowohl fuer das Pflicht-Sternchen im Render als auch fuer die
     * Pflicht/Optional-Aufteilung verwendet — Single Source of Truth.
     */
    public function isFieldEffectivelyRequired(array $field, ?int $currentPhaseOrder = null): bool
    {
        if (!empty($field['is_mandatory']) || !empty($field['is_required'])) {
            return true;
        }
        $overrideOrders = $field['options']['required_in_phase_orders'] ?? [];
        if (empty($overrideOrders) || !is_array($overrideOrders)) {
            return false;
        }
        if ($currentPhaseOrder === null) {
            return false;
        }
        return in_array((int) $currentPhaseOrder, array_map('intval', $overrideOrders), true);
    }

    /**
     * Bedingt sichtbar: hat aktive visibility_config. Solche Felder werden
     * im Akkordeon-Layout im Hauptbereich gerendert (nicht eingeklappt),
     * weil sie kontextuell relevant sind sobald sie sichtbar werden.
     */
    public function isFieldConditionallyVisible(array $field): bool
    {
        return ($field['visibility_config']['enabled'] ?? false) === true;
    }

    /**
     * options.always_show_in_form=true → das Feld bleibt im Form sichtbar
     * auch wenn schon ein Wert gespeichert ist. Geeignet fuer kanonische
     * Felder die der Bewerber bestaetigen oder ueberschreiben koennen soll
     * (z.B. Mail die vom Inbound als Indeed-Proxy gesetzt wurde).
     */
    public function isAlwaysShownInForm(array $field): bool
    {
        return ($field['options']['always_show_in_form'] ?? false) === true;
    }

    /**
     * Modell-Opt-In via method_exists-Check. Recruiting-Modelle haben den
     * Trait UsesAccordionPublicForm, andere Module nicht — damit ist das
     * Akkordeon-Layout strikt opt-in pro Modul ohne dass Core ein Modul
     * kennen muss.
     */
    protected function usesAccordionLayout($model): bool
    {
        if (!$model) {
            return false;
        }
        if (!method_exists($model, 'usesAccordionFormLayout')) {
            return false;
        }
        return (bool) $model->usesAccordionFormLayout();
    }

    /**
     * Phase-Order des aktuellen Modells fuer den required_in_phase_orders-
     * Override-Check. Modul-agnostisch via method_exists — Recruiting
     * RecApplicant hat phase()->order; andere Modelle ohne Phase-Konzept
     * returnen null und der Override greift schlicht nicht.
     */
    protected function resolveCurrentPhaseOrder($model): ?int
    {
        if (!$model) {
            return null;
        }
        if (method_exists($model, 'phase')) {
            $phase = $model->phase;
            if ($phase && isset($phase->order)) {
                return (int) $phase->order;
            }
        }
        return null;
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

        // Sync extra field values to linked CRM contact
        if (method_exists($model, 'syncExtraFieldsToCrmContact')) {
            $model->syncExtraFieldsToCrmContact();
        }

        // Recount filled fields
        $this->filledFields = 0;
        $allDefinitions = $model->getExtraFieldsWithLabels();
        $this->totalFields = 0;
        $remainingUnfilled = 0;

        foreach ($allDefinitions as $field) {
            $this->totalFields++;
            if ($field['type'] === 'phone') {
                $isFilled = is_array($field['value']) && (!empty($field['value']['e164'] ?? null) || !empty(trim($field['value']['raw'] ?? '')));
            } elseif ($field['type'] === 'date') {
                $isFilled = $field['value'] !== null && $field['value'] !== '';
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
