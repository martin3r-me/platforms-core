{{--
    Extra Fields Form Component

    Renders extra field inputs for inline editing.
    Works with the WithExtraFields Livewire trait.

    Usage:
    <x-core-extra-fields-form :definitions="$this->extraFieldDefinitions" />

    The wire:model binds to extraFieldValues.{id} from the trait.
--}}
@props([
    'definitions' => [],
    'columns' => 2,
])

@if(count($definitions) > 0)
    <div {{ $attributes->merge(['class' => 'grid gap-4 ' . match($columns) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2',
    }]) }}>
        @foreach($definitions as $field)
            <div>
                @switch($field['type'])
                    @case('textarea')
                        <x-ui-input-textarea
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_required']"
                            :hint="$field['is_encrypted'] ? 'Verschlüsselt' : null"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            rows="3"
                            placeholder="Wert eingeben..."
                        />
                        @break

                    @case('number')
                        <x-ui-input-number
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_required']"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            step="any"
                            placeholder="Wert eingeben..."
                        />
                        @if($field['is_encrypted'])
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
                                :required="$field['is_required']"
                                class="mb-2"
                            />
                            <x-ui-input-select
                                :name="'extraFieldValues.' . $field['id']"
                                :options="['1' => 'Ja', '0' => 'Nein']"
                                :nullable="!$field['is_required']"
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
                        @endphp
                        <div>
                            <x-ui-label
                                :text="$field['label']"
                                :required="$field['is_required']"
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

                            @if($field['is_encrypted'])
                                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    Verschlüsselt
                                </span>
                            @endif
                        </div>
                        @break

                    @case('select')
                        @php
                            $choices = $field['options']['choices'] ?? [];
                            $isMultiple = $field['options']['multiple'] ?? false;
                            $selectOptions = array_combine($choices, $choices);
                        @endphp
                        @if($isMultiple)
                            {{-- Mehrfachauswahl mit Checkboxen --}}
                            <div>
                                <x-ui-label
                                    :text="$field['label']"
                                    :required="$field['is_required']"
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
                                :required="$field['is_required']"
                                :options="$selectOptions"
                                :nullable="!$field['is_required']"
                                nullLabel="Bitte wählen..."
                                wire:model.live="extraFieldValues.{{ $field['id'] }}"
                                :displayMode="count($choices) <= 5 ? 'badges' : 'dropdown'"
                            />
                            @if($field['is_encrypted'])
                                <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                    Verschlüsselt
                                </span>
                            @endif
                        @endif
                        @break

                    @default
                        <x-ui-input-text
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_required']"
                            :hint="$field['is_encrypted'] ? 'Verschlüsselt' : null"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            placeholder="Wert eingeben..."
                        />
                @endswitch
            </div>
        @endforeach
    </div>
@endif
