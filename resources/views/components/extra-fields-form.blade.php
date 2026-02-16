{{--
    Extra Fields Form Component

    Renders extra field inputs for inline editing.
    Works with the WithExtraFields Livewire trait.

    Usage:
    <x-core-extra-fields-form :definitions="$this->extraFieldDefinitions" />

    The wire:model binds to extraFieldValues.{id} from the trait.
    Supports conditional visibility via Alpine.js.
--}}
@props([
    'definitions' => [],
    'columns' => 2,
])

@if(count($definitions) > 0)
    @php
        // Build field map for visibility evaluation
        $fieldMap = [];
        foreach ($definitions as $def) {
            $fieldMap[$def['name']] = [
                'id' => $def['id'],
                'type' => $def['type'],
                'visibility_config' => $def['visibility_config'] ?? null,
            ];
        }
    @endphp

    <div
        x-data="{
            fieldValues: @entangle('extraFieldValues').live,
            fieldDefinitions: @js($definitions),

            // Check if a field should be visible
            isFieldVisible(fieldId) {
                const field = this.fieldDefinitions.find(f => f.id === fieldId);
                if (!field) return true;

                const visibility = field.visibility_config;
                if (!visibility || !visibility.enabled) return true;

                return this.evaluateVisibility(visibility);
            },

            // Evaluate visibility configuration
            evaluateVisibility(config) {
                if (!config.groups || config.groups.length === 0) return true;

                const mainLogic = (config.logic || 'AND').toUpperCase();
                const groupResults = config.groups.map(group => this.evaluateGroup(group));

                if (mainLogic === 'OR') {
                    return groupResults.includes(true);
                }
                return !groupResults.includes(false);
            },

            // Evaluate a condition group
            evaluateGroup(group) {
                if (!group.conditions || group.conditions.length === 0) return true;

                const groupLogic = (group.logic || 'AND').toUpperCase();
                const conditionResults = group.conditions.map(cond => this.evaluateCondition(cond));

                if (groupLogic === 'OR') {
                    return conditionResults.includes(true);
                }
                return !conditionResults.includes(false);
            },

            // Evaluate a single condition
            evaluateCondition(condition) {
                if (!condition.field) return true;

                // Find the field definition by name
                const targetField = this.fieldDefinitions.find(f => f.name === condition.field);
                if (!targetField) return true;

                // Get current value
                const actualValue = this.fieldValues[targetField.id];
                const expectedValue = condition.value;
                const operator = condition.operator || 'equals';

                return this.compareValues(actualValue, operator, expectedValue);
            },

            // Compare values with operator
            compareValues(actual, operator, expected) {
                switch (operator) {
                    case 'equals':
                        return this.isEqual(actual, expected);
                    case 'not_equals':
                        return !this.isEqual(actual, expected);
                    case 'greater_than':
                        return parseFloat(actual) > parseFloat(expected);
                    case 'greater_or_equal':
                        return parseFloat(actual) >= parseFloat(expected);
                    case 'less_than':
                        return parseFloat(actual) < parseFloat(expected);
                    case 'less_or_equal':
                        return parseFloat(actual) <= parseFloat(expected);
                    case 'is_null':
                        return this.isEmpty(actual);
                    case 'is_not_null':
                        return !this.isEmpty(actual);
                    case 'in':
                        return this.isIn(actual, expected);
                    case 'not_in':
                        return !this.isIn(actual, expected);
                    case 'contains':
                        return String(actual || '').toLowerCase().includes(String(expected || '').toLowerCase());
                    case 'starts_with':
                        return String(actual || '').toLowerCase().startsWith(String(expected || '').toLowerCase());
                    case 'ends_with':
                        return String(actual || '').toLowerCase().endsWith(String(expected || '').toLowerCase());
                    case 'is_true':
                        return actual === true || actual === 1 || actual === '1' || String(actual).toLowerCase() === 'true';
                    case 'is_false':
                        return actual === false || actual === 0 || actual === '0' || String(actual).toLowerCase() === 'false' || this.isEmpty(actual);
                    default:
                        return true;
                }
            },

            isEqual(a, b) {
                if (a === b) return true;
                if (a === null && b === null) return true;
                if (typeof a === 'number' && typeof b === 'number') return a === b;
                if (!isNaN(a) && !isNaN(b)) return parseFloat(a) === parseFloat(b);
                return String(a || '').toLowerCase() === String(b || '').toLowerCase();
            },

            isEmpty(value) {
                if (value === null || value === undefined) return true;
                if (typeof value === 'string' && value.trim() === '') return true;
                if (Array.isArray(value) && value.length === 0) return true;
                return false;
            },

            isIn(actual, expected) {
                if (!Array.isArray(expected)) expected = [expected];
                if (Array.isArray(actual)) {
                    return actual.some(item => expected.includes(item));
                }
                return expected.includes(actual);
            }
        }"
        {{ $attributes->merge(['class' => 'grid gap-4 ' . match($columns) {
            1 => 'grid-cols-1',
            3 => 'grid-cols-1 md:grid-cols-3',
            4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
            default => 'grid-cols-1 md:grid-cols-2',
        }]) }}
    >
        @foreach($definitions as $field)
            <div
                x-show="isFieldVisible({{ $field['id'] }})"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
            >
                @switch($field['type'])
                    @case('textarea')
                        @php
                            $isAutoFilled = $extraFieldMeta[$field['id']]['auto_filled'] ?? false;
                        @endphp
                        <x-ui-input-textarea
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_mandatory'] || $field['is_required']"
                            :hint="$field['is_encrypted'] ? 'Verschlüsselt' : null"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            rows="3"
                            placeholder="Wert eingeben..."
                        />
                        @if($isAutoFilled)
                            <span class="text-xs text-[var(--ui-primary)] flex items-center gap-1 mt-1">
                                @svg('heroicon-o-sparkles', 'w-3 h-3')
                                Automatisch ausgefüllt
                            </span>
                        @endif
                        @break

                    @case('number')
                        @php
                            $isAutoFilled = $extraFieldMeta[$field['id']]['auto_filled'] ?? false;
                        @endphp
                        <x-ui-input-number
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_mandatory'] || $field['is_required']"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            step="any"
                            placeholder="Wert eingeben..."
                        />
                        @if($isAutoFilled)
                            <span class="text-xs text-[var(--ui-primary)] flex items-center gap-1 mt-1">
                                @svg('heroicon-o-sparkles', 'w-3 h-3')
                                Automatisch ausgefüllt
                            </span>
                        @elseif($field['is_encrypted'])
                            <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                Verschlüsselt
                            </span>
                        @endif
                        @break

                    @case('boolean')
                        <div>
                            <x-ui-label
                                :text="$field['label']"
                                :required="$field['is_mandatory'] || $field['is_required']"
                                class="mb-2"
                            />
                            <x-ui-input-select
                                :name="'extraFieldValues.' . $field['id']"
                                :options="['1' => 'Ja', '0' => 'Nein']"
                                :nullable="!$field['is_mandatory'] && !$field['is_required']"
                                nullLabel="Nicht ausgewählt"
                                wire:model.live="extraFieldValues.{{ $field['id'] }}"
                                displayMode="badges"
                            />
                            @if($field['is_encrypted'])
                                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    Verschlüsselt
                                </span>
                            @endif
                        </div>
                        @break

                    @case('file')
                        @php
                            $isMultiple = $field['options']['multiple'] ?? false;
                            $fileIds = $extraFieldValues[$field['id']] ?? ($isMultiple ? [] : null);
                            $fileIds = is_array($fileIds) ? $fileIds : ($fileIds ? [$fileIds] : []);
                            $verificationStatus = $extraFieldMeta[$field['id']]['verification_status'] ?? null;
                            $verificationResult = $extraFieldMeta[$field['id']]['verification_result'] ?? null;
                        @endphp
                        <div>
                            <x-ui-label
                                :text="$field['label']"
                                :required="$field['is_mandatory'] || $field['is_required']"
                                class="mb-2"
                            />

                            {{-- Datei-Vorschau --}}
                            @if(count($fileIds) > 0)
                                <div class="flex flex-wrap gap-2 mb-2">
                                    @foreach($fileIds as $fileId)
                                        @php
                                            $file = \Platform\Core\Models\ContextFile::find($fileId);
                                        @endphp
                                        @if($file)
                                            <div class="relative group">
                                                @if($file->isImage())
                                                    <img
                                                        src="{{ $file->thumbnail?->url ?? $file->url }}"
                                                        alt="{{ $file->original_name }}"
                                                        class="w-16 h-16 object-cover border border-[var(--ui-border)]/40"
                                                    />
                                                @else
                                                    <div class="w-16 h-16 flex items-center justify-center bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                                        @svg('heroicon-o-document', 'w-6 h-6 text-[var(--ui-muted)]')
                                                    </div>
                                                @endif
                                                <button
                                                    type="button"
                                                    wire:click="removeExtraFieldFile({{ $field['id'] }}, {{ $fileId }})"
                                                    class="absolute -top-1 -right-1 w-5 h-5 bg-[var(--ui-danger)] text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                                >
                                                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                                                </button>
                                                <span class="block text-xs text-[var(--ui-muted)] truncate max-w-[4rem]" title="{{ $file->original_name }}">
                                                    {{ $file->original_name }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- Datei-Auswahl Button --}}
                            <button
                                type="button"
                                wire:click="openExtraFieldFilePicker({{ $field['id'] }}, {{ $isMultiple ? 'true' : 'false' }})"
                                class="px-3 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-2"
                            >
                                @svg('heroicon-o-paper-clip', 'w-4 h-4')
                                {{ $isMultiple ? 'Dateien auswählen' : 'Datei auswählen' }}
                            </button>

                            {{-- LLM Verification Status --}}
                            @if($field['type'] === 'file' && ($field['verify_by_llm'] ?? false) && $verificationStatus)
                                <div class="flex items-center gap-2 mt-2 flex-wrap">
                                    {{-- Status Badge --}}
                                    <span class="px-2 py-0.5 text-xs rounded {{ match($verificationStatus) {
                                        'verified' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        'verifying' => 'bg-yellow-100 text-yellow-800 animate-pulse',
                                        'pending' => 'bg-gray-100 text-gray-600',
                                        'error' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100'
                                    } }}">
                                        {{ \Platform\Core\Models\CoreExtraFieldValue::VERIFICATION_STATUSES[$verificationStatus] ?? $verificationStatus }}
                                    </span>

                                    {{-- Re-Verify Button (nicht während Prüfung) --}}
                                    @if($verificationStatus !== 'verifying' && count($fileIds) > 0)
                                        <button
                                            type="button"
                                            wire:click="retryExtraFieldVerification({{ $field['id'] }})"
                                            class="text-xs text-[var(--ui-primary)] hover:underline"
                                        >
                                            Erneut prüfen
                                        </button>
                                    @endif

                                    {{-- Begründung bei rejected/verified --}}
                                    @if(in_array($verificationStatus, ['verified', 'rejected']) && ($reason = $verificationResult['reason'] ?? null))
                                        <span class="text-xs text-[var(--ui-muted)]" title="{{ $reason }}">
                                            {{ \Illuminate\Support\Str::limit($reason, 50) }}
                                        </span>
                                    @endif

                                    {{-- Error message --}}
                                    @if($verificationStatus === 'error' && ($error = $verificationResult['error'] ?? null))
                                        <span class="text-xs text-[var(--ui-danger)]" title="{{ $error }}">
                                            {{ \Illuminate\Support\Str::limit($error, 50) }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            @if($field['is_encrypted'])
                                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    Verschlüsselt
                                </span>
                            @endif
                        </div>
                        @break

                    @case('lookup')
                        @php
                            $lookupId = $field['options']['lookup_id'] ?? null;
                            $isMultiple = $field['options']['multiple'] ?? false;
                            $lookup = $lookupId ? \Platform\Core\Models\CoreLookup::find($lookupId) : null;
                            $lookupOptions = $lookup ? $lookup->getOptionsArray() : [];
                            $isAutoFilled = $extraFieldMeta[$field['id']]['auto_filled'] ?? false;
                        @endphp
                        @if($isMultiple)
                            {{-- Mehrfachauswahl mit Checkboxen --}}
                            <div>
                                <x-ui-label
                                    :text="$field['label']"
                                    :required="$field['is_mandatory'] || $field['is_required']"
                                    class="mb-2"
                                />
                                @if($field['is_encrypted'])
                                    <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mb-2">
                                        @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                        Verschlüsselt
                                    </span>
                                @endif
                                @if(count($lookupOptions) > 0)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($lookupOptions as $value => $label)
                                            <label class="inline-flex items-center gap-2 px-3 py-1.5 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40 cursor-pointer hover:bg-[var(--ui-muted-5)] transition-colors">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $value }}"
                                                    wire:model.live="extraFieldValues.{{ $field['id'] }}"
                                                    class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] focus:ring-[var(--ui-primary)]"
                                                />
                                                <span class="text-sm text-[var(--ui-secondary)]">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-[var(--ui-muted)]">Lookup enthält keine Werte.</p>
                                @endif
                            </div>
                        @else
                            {{-- Einzelauswahl --}}
                            <x-ui-input-select
                                :name="'extraFieldValues.' . $field['id']"
                                :label="$field['label']"
                                :required="$field['is_mandatory'] || $field['is_required']"
                                :options="$lookupOptions"
                                :nullable="!$field['is_mandatory'] && !$field['is_required']"
                                nullLabel="Bitte wählen..."
                                wire:model.live="extraFieldValues.{{ $field['id'] }}"
                                :displayMode="count($lookupOptions) <= 5 ? 'badges' : 'dropdown'"
                            />
                            @if($isAutoFilled)
                                <span class="text-xs text-[var(--ui-primary)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-sparkles', 'w-3 h-3')
                                    Automatisch ausgefüllt
                                </span>
                            @elseif($field['is_encrypted'])
                                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    Verschlüsselt
                                </span>
                            @endif
                        @endif
                        @break

                    @case('select')
                        @php
                            $choices = $field['options']['choices'] ?? [];
                            $isMultiple = $field['options']['multiple'] ?? false;
                            $selectOptions = array_combine($choices, $choices);
                            $isAutoFilled = $extraFieldMeta[$field['id']]['auto_filled'] ?? false;
                        @endphp
                        @if($isMultiple)
                            {{-- Mehrfachauswahl mit Checkboxen --}}
                            <div>
                                <x-ui-label
                                    :text="$field['label']"
                                    :required="$field['is_mandatory'] || $field['is_required']"
                                    class="mb-2"
                                />
                                @if($field['is_encrypted'])
                                    <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mb-2">
                                        @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                        Verschlüsselt
                                    </span>
                                @endif
                                <div class="flex flex-wrap gap-2">
                                    @foreach($choices as $choice)
                                        <label class="inline-flex items-center gap-2 px-3 py-1.5 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40 cursor-pointer hover:bg-[var(--ui-muted-5)] transition-colors">
                                            <input
                                                type="checkbox"
                                                value="{{ $choice }}"
                                                wire:model.live="extraFieldValues.{{ $field['id'] }}"
                                                class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] focus:ring-[var(--ui-primary)]"
                                            />
                                            <span class="text-sm text-[var(--ui-secondary)]">{{ $choice }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- Einzelauswahl --}}
                            <x-ui-input-select
                                :name="'extraFieldValues.' . $field['id']"
                                :label="$field['label']"
                                :required="$field['is_mandatory'] || $field['is_required']"
                                :options="$selectOptions"
                                :nullable="!$field['is_mandatory'] && !$field['is_required']"
                                nullLabel="Bitte wählen..."
                                wire:model.live="extraFieldValues.{{ $field['id'] }}"
                                :displayMode="count($choices) <= 5 ? 'badges' : 'dropdown'"
                            />
                            @if($isAutoFilled)
                                <span class="text-xs text-[var(--ui-primary)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-sparkles', 'w-3 h-3')
                                    Automatisch ausgefüllt
                                </span>
                            @elseif($field['is_encrypted'])
                                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    Verschlüsselt
                                </span>
                            @endif
                        @endif
                        @break

                    @default
                        @php
                            $isAutoFilled = $extraFieldMeta[$field['id']]['auto_filled'] ?? false;
                        @endphp
                        <x-ui-input-text
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_mandatory'] || $field['is_required']"
                            :hint="$field['is_encrypted'] ? 'Verschlüsselt' : null"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            placeholder="Wert eingeben..."
                        />
                        @if($isAutoFilled)
                            <span class="text-xs text-[var(--ui-primary)] flex items-center gap-1 mt-1">
                                @svg('heroicon-o-sparkles', 'w-3 h-3')
                                Automatisch ausgefüllt
                            </span>
                        @endif
                @endswitch
            </div>
        @endforeach
    </div>
@endif
