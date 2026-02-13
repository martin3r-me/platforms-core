<div>
<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-[var(--ui-primary-5)] flex items-center justify-center">
                    @svg('heroicon-o-squares-plus', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Extra-Felder Definitionen</h3>
                <p class="text-sm text-[var(--ui-muted)] mt-1">
                    @if($contextId)
                        Felder für diesen Kontext definieren
                    @else
                        Felder für alle Einträge dieses Typs definieren
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div>
        {{-- Tab Navigation --}}
        <div class="flex border-b border-[var(--ui-border)]/40 mb-6">
            <button
                wire:click="$set('activeTab', 'fields')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'fields' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                Felder
            </button>
            <button
                wire:click="$set('activeTab', 'lookups')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'lookups' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                Lookups
            </button>
        </div>

        {{-- Felder Tab --}}
        @if($activeTab === 'fields' && $contextType)
            {{-- Editing Mode: Full-width panel with tabs --}}
            @if($editingDefinitionId)
                @php
                    $editingDef = collect($definitions)->firstWhere('id', $editingDefinitionId);
                @endphp
                <div class="space-y-4">
                    {{-- Edit Header --}}
                    <div class="flex items-center justify-between pb-4 border-b border-[var(--ui-border)]/40">
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                wire:click="cancelEditDefinition"
                                class="p-2 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                            >
                                @svg('heroicon-o-arrow-left', 'w-5 h-5')
                            </button>
                            <div>
                                <h4 class="text-lg font-semibold text-[var(--ui-secondary)]">
                                    Feld bearbeiten: {{ $editingDef['label'] ?? '' }}
                                </h4>
                                <span class="text-xs text-[var(--ui-muted)]">{{ $editingDef['name'] ?? '' }}</span>
                            </div>
                        </div>
                        <button
                            wire:click="saveEditDefinition"
                            class="px-4 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors text-sm font-medium"
                        >
                            Speichern
                        </button>
                    </div>

                    {{-- Edit Field Tabs --}}
                    <div class="flex border-b border-[var(--ui-border)]/40">
                        <button
                            wire:click="$set('editFieldTab', 'basis')"
                            class="px-4 py-2 text-sm font-medium transition-colors {{ $editFieldTab === 'basis' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                        >
                            Basis
                        </button>
                        @if(in_array($editField['type'], ['select', 'lookup', 'file']))
                            <button
                                wire:click="$set('editFieldTab', 'options')"
                                class="px-4 py-2 text-sm font-medium transition-colors {{ $editFieldTab === 'options' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                Optionen
                            </button>
                        @endif
                        <button
                            wire:click="$set('editFieldTab', 'conditions')"
                            class="px-4 py-2 text-sm font-medium transition-colors {{ $editFieldTab === 'conditions' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                        >
                            Bedingungen
                            @if($editField['visibility']['enabled'] ?? false)
                                <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-[var(--ui-primary)] rounded-full"></span>
                            @endif
                        </button>
                        @if($editField['type'] === 'file')
                            <button
                                wire:click="$set('editFieldTab', 'verification')"
                                class="px-4 py-2 text-sm font-medium transition-colors {{ $editFieldTab === 'verification' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                KI-Verifikation
                                @if($editField['verify_by_llm'] ?? false)
                                    <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-[var(--ui-primary)] rounded-full"></span>
                                @endif
                            </button>
                        @endif
                        @if($editField['type'] !== 'file')
                            <button
                                wire:click="$set('editFieldTab', 'autofill')"
                                class="px-4 py-2 text-sm font-medium transition-colors {{ $editFieldTab === 'autofill' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                AutoFill
                                @if(!empty($editField['auto_fill_source']))
                                    <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-[var(--ui-primary)] rounded-full"></span>
                                @endif
                            </button>
                        @endif
                    </div>

                    {{-- Tab Content --}}
                    <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                        {{-- Basis Tab --}}
                        @if($editFieldTab === 'basis')
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <x-ui-input-text
                                        name="editField.label"
                                        label="Label"
                                        :required="true"
                                        wire:model="editField.label"
                                    />

                                    <x-ui-input-select
                                        name="editField.type"
                                        label="Typ"
                                        :required="true"
                                        :options="$this->availableTypes"
                                        wire:model.live="editField.type"
                                        displayMode="dropdown"
                                    />
                                </div>

                                <div class="flex flex-wrap gap-6 pt-2">
                                    <x-ui-input-checkbox
                                        name="editField.is_required"
                                        checkedLabel="Erforderlich (Fortschritt)"
                                        uncheckedLabel="Erforderlich (Fortschritt)"
                                        wire:model="editField.is_required"
                                    />

                                    <x-ui-input-checkbox
                                        name="editField.is_mandatory"
                                        checkedLabel="Pflichtfeld (blockiert Speichern)"
                                        uncheckedLabel="Pflichtfeld (blockiert Speichern)"
                                        wire:model="editField.is_mandatory"
                                    />

                                    <x-ui-input-checkbox
                                        name="editField.is_encrypted"
                                        checkedLabel="Verschlüsselt"
                                        uncheckedLabel="Als verschlüsselt markieren"
                                        wire:model="editField.is_encrypted"
                                    />
                                </div>
                            </div>
                        @endif

                        {{-- Options Tab (Select, Lookup, File) --}}
                        @if($editFieldTab === 'options')
                            @if($editField['type'] === 'select')
                                <div class="space-y-4">
                                    <x-ui-input-checkbox
                                        name="editField.is_multiple"
                                        checkedLabel="Mehrfachauswahl aktiv"
                                        uncheckedLabel="Mehrfachauswahl aktivieren"
                                        wire:model="editField.is_multiple"
                                    />

                                    <div>
                                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Auswahloptionen</label>
                                        <div class="flex gap-2 mb-3">
                                            <input
                                                type="text"
                                                wire:model="editOptionText"
                                                wire:keydown.enter.prevent="addEditOption"
                                                class="flex-1 px-3 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                                placeholder="Option eingeben..."
                                            />
                                            <button
                                                type="button"
                                                wire:click="addEditOption"
                                                class="px-3 py-1.5 bg-[var(--ui-primary)] text-white text-sm hover:bg-[var(--ui-primary)]/90"
                                            >
                                                Hinzufügen
                                            </button>
                                        </div>

                                        @if(count($editField['options']) > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($editField['options'] as $index => $option)
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-[var(--ui-surface)] text-sm text-[var(--ui-secondary)] border border-[var(--ui-border)]/40">
                                                        {{ $option }}
                                                        <button
                                                            type="button"
                                                            wire:click="removeEditOption({{ $index }})"
                                                            class="text-[var(--ui-muted)] hover:text-[var(--ui-danger)]"
                                                        >
                                                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-xs text-[var(--ui-muted)]">Noch keine Optionen hinzugefügt.</p>
                                        @endif

                                        @error('editField.options')
                                            <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            @elseif($editField['type'] === 'lookup')
                                <div class="space-y-4">
                                    <x-ui-input-checkbox
                                        name="editField.is_multiple"
                                        checkedLabel="Mehrfachauswahl aktiv"
                                        uncheckedLabel="Mehrfachauswahl aktivieren"
                                        wire:model="editField.is_multiple"
                                    />

                                    @if(count($this->availableLookups) > 0)
                                        <div>
                                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Lookup auswählen</label>
                                            <select
                                                wire:model="editField.lookup_id"
                                                class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                            >
                                                <option value="">Bitte wählen...</option>
                                                @foreach($this->availableLookups as $lookup)
                                                    <option value="{{ $lookup['id'] }}">{{ $lookup['label'] }} ({{ $lookup['values_count'] }} Werte)</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <p class="text-sm text-[var(--ui-muted)]">
                                            Noch keine Lookups vorhanden. Erstellen Sie zuerst einen Lookup im Tab "Lookups".
                                        </p>
                                    @endif

                                    @error('editField.lookup_id')
                                        <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                                    @enderror
                                </div>
                            @elseif($editField['type'] === 'file')
                                <x-ui-input-checkbox
                                    name="editField.is_multiple"
                                    checkedLabel="Mehrere Dateien erlaubt"
                                    uncheckedLabel="Mehrere Dateien erlauben"
                                    wire:model="editField.is_multiple"
                                />
                            @endif
                        @endif

                        {{-- Conditions Tab --}}
                        @if($editFieldTab === 'conditions')
                            <x-platform::condition-builder
                                :visibility="$editField['visibility']"
                                :availableFields="$this->conditionFields"
                                :allOperators="$this->allOperators"
                                :description="$this->visibilityDescription"
                            />
                        @endif

                        {{-- Verification Tab (File only) --}}
                        @if($editFieldTab === 'verification' && $editField['type'] === 'file')
                            <div class="space-y-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model.live="editField.verify_by_llm"
                                        class="rounded border-[var(--ui-border)]/60 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                    />
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Durch KI verifizieren</span>
                                </label>

                                @if($editField['verify_by_llm'])
                                    <div>
                                        <label class="block text-sm text-[var(--ui-muted)] mb-1">Verifikations-Anweisungen</label>
                                        <textarea
                                            wire:model="editField.verify_instructions"
                                            placeholder="z.B.: Prüfe ob dies ein gültiger Personalausweis ist. Ist er lesbar und nicht abgelaufen?"
                                            class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                            rows="3"
                                        ></textarea>
                                        <p class="text-xs text-[var(--ui-muted)] mt-1">Die KI prüft hochgeladene Bilder nach diesen Anweisungen.</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- AutoFill Tab (non-file) --}}
                        @if($editFieldTab === 'autofill' && $editField['type'] !== 'file')
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">AutoFill-Quelle</label>
                                    <select
                                        wire:model.live="editField.auto_fill_source"
                                        class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                    >
                                        <option value="">Deaktiviert</option>
                                        @foreach($this->autoFillSources as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if(!empty($editField['auto_fill_source']))
                                    <div>
                                        <label class="block text-sm text-[var(--ui-muted)] mb-1">AutoFill-Anweisungen</label>
                                        <textarea
                                            wire:model="editField.auto_fill_prompt"
                                            placeholder="{{ $editField['auto_fill_source'] === 'websearch' ? 'z.B.: Suche die Firmenadresse und Telefonnummer' : 'z.B.: Analysiere die vorhandenen Daten und schlage einen passenden Wert vor' }}"
                                            class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                            rows="2"
                                        ></textarea>
                                        <p class="text-xs text-[var(--ui-muted)] mt-1">
                                            @if($editField['auto_fill_source'] === 'websearch')
                                                Per Scheduler werden leere Felder via Web-Suche automatisch gefüllt.
                                            @else
                                                Per Scheduler werden leere Felder via KI-Analyse automatisch gefüllt.
                                            @endif
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
            {{-- List Mode --}}
            <!-- Definitionen -->
            <div class="space-y-6">
                <!-- Neues Feld erstellen -->
                <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Neues Feld erstellen
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-ui-input-text
                            name="newField.label"
                            label="Label"
                            :required="true"
                            wire:model="newField.label"
                            placeholder="z.B. Gehaltsvorstellung"
                        />

                        <x-ui-input-select
                            name="newField.type"
                            label="Typ"
                            :required="true"
                            :options="$this->availableTypes"
                            wire:model.live="newField.type"
                            displayMode="dropdown"
                        />

                        <div class="flex items-end gap-4 flex-wrap">
                            <x-ui-input-checkbox
                                name="newField.is_required"
                                checkedLabel="Erforderlich (Fortschritt)"
                                uncheckedLabel="Erforderlich (Fortschritt)"
                                wire:model="newField.is_required"
                            />

                            <x-ui-input-checkbox
                                name="newField.is_mandatory"
                                checkedLabel="Pflichtfeld (blockiert Speichern)"
                                uncheckedLabel="Pflichtfeld (blockiert Speichern)"
                                wire:model="newField.is_mandatory"
                            />

                            <x-ui-input-checkbox
                                name="newField.is_encrypted"
                                checkedLabel="Verschlüsselt"
                                uncheckedLabel="Als verschlüsselt markieren"
                                wire:model="newField.is_encrypted"
                            />
                        </div>
                    </div>

                    {{-- Select-Optionen --}}
                    @if($newField['type'] === 'select')
                        <div class="mt-4 p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Auswahloptionen</span>
                                <x-ui-input-checkbox
                                    name="newField.is_multiple"
                                    checkedLabel="Mehrfachauswahl aktiv"
                                    uncheckedLabel="Mehrfachauswahl aktivieren"
                                    wire:model="newField.is_multiple"
                                />
                            </div>

                            <div class="flex gap-2 mb-3">
                                <input
                                    type="text"
                                    wire:model="newOptionText"
                                    wire:keydown.enter.prevent="addNewOption"
                                    class="flex-1 px-3 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                    placeholder="Option eingeben..."
                                />
                                <button
                                    type="button"
                                    wire:click="addNewOption"
                                    class="px-3 py-1.5 bg-[var(--ui-primary)] text-white text-sm hover:bg-[var(--ui-primary)]/90"
                                >
                                    Hinzufügen
                                </button>
                            </div>

                            @if(count($newField['options']) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($newField['options'] as $index => $option)
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-[var(--ui-muted-5)] text-sm text-[var(--ui-secondary)] border border-[var(--ui-border)]/40">
                                            {{ $option }}
                                            <button
                                                type="button"
                                                wire:click="removeNewOption({{ $index }})"
                                                class="text-[var(--ui-muted)] hover:text-[var(--ui-danger)]"
                                            >
                                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-[var(--ui-muted)]">Noch keine Optionen hinzugefügt.</p>
                            @endif

                            @error('newField.options')
                                <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Lookup-Optionen --}}
                    @if($newField['type'] === 'lookup')
                        <div class="mt-4 p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Lookup auswählen</span>
                                <x-ui-input-checkbox
                                    name="newField.is_multiple"
                                    checkedLabel="Mehrfachauswahl aktiv"
                                    uncheckedLabel="Mehrfachauswahl aktivieren"
                                    wire:model="newField.is_multiple"
                                />
                            </div>

                            @if(count($this->availableLookups) > 0)
                                <select
                                    wire:model="newField.lookup_id"
                                    class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                >
                                    <option value="">Bitte wählen...</option>
                                    @foreach($this->availableLookups as $lookup)
                                        <option value="{{ $lookup['id'] }}">{{ $lookup['label'] }} ({{ $lookup['values_count'] }} Werte)</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-[var(--ui-muted)] mt-2">
                                    Lookups können im Tab "Lookups" verwaltet werden.
                                </p>
                            @else
                                <p class="text-sm text-[var(--ui-muted)]">
                                    Noch keine Lookups vorhanden. Erstellen Sie zuerst einen Lookup im Tab "Lookups".
                                </p>
                            @endif

                            @error('newField.lookup_id')
                                <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- File-Optionen --}}
                    @if($newField['type'] === 'file')
                        <div class="mt-4 p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                            <x-ui-input-checkbox
                                name="newField.is_multiple"
                                checkedLabel="Mehrere Dateien erlaubt"
                                uncheckedLabel="Mehrere Dateien erlauben"
                                wire:model="newField.is_multiple"
                            />
                        </div>

                        {{-- LLM-Verifikation --}}
                        <div class="mt-4 p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model.live="newField.verify_by_llm"
                                    class="rounded border-[var(--ui-border)]/60 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                />
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Durch KI verifizieren</span>
                            </label>

                            @if($newField['verify_by_llm'])
                                <div class="mt-3">
                                    <label class="block text-sm text-[var(--ui-muted)] mb-1">Verifikations-Anweisungen</label>
                                    <textarea
                                        wire:model="newField.verify_instructions"
                                        placeholder="z.B.: Prüfe ob dies ein gültiger Personalausweis ist. Ist er lesbar und nicht abgelaufen?"
                                        class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                        rows="3"
                                    ></textarea>
                                    <p class="text-xs text-[var(--ui-muted)] mt-1">Die KI prüft hochgeladene Bilder nach diesen Anweisungen und markiert sie als verifiziert oder abgelehnt.</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- AutoFill Optionen (für alle Feldtypen außer file) --}}
                    @if($newField['type'] !== 'file')
                        <div class="mt-4 p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-4 mb-3">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">AutoFill</span>
                                <select
                                    wire:model.live="newField.auto_fill_source"
                                    class="px-3 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                >
                                    <option value="">Deaktiviert</option>
                                    @foreach($this->autoFillSources as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if(!empty($newField['auto_fill_source']))
                                <div class="mt-3">
                                    <label class="block text-sm text-[var(--ui-muted)] mb-1">AutoFill-Anweisungen</label>
                                    <textarea
                                        wire:model="newField.auto_fill_prompt"
                                        placeholder="{{ $newField['auto_fill_source'] === 'websearch' ? 'z.B.: Suche die Firmenadresse und Telefonnummer' : 'z.B.: Analysiere die vorhandenen Daten und schlage einen passenden Wert vor' }}"
                                        class="w-full px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                        rows="2"
                                    ></textarea>
                                    <p class="text-xs text-[var(--ui-muted)] mt-1">
                                        @if($newField['auto_fill_source'] === 'websearch')
                                            Per Scheduler werden leere Felder via Web-Suche automatisch gefüllt.
                                        @else
                                            Per Scheduler werden leere Felder via KI-Analyse automatisch gefüllt.
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 flex justify-end">
                        <button
                            wire:click="createDefinition"
                            class="px-4 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors text-sm font-medium"
                        >
                            Feld erstellen
                        </button>
                    </div>
                </div>

                <!-- Vorhandene Definitionen -->
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Vorhandene Felder
                    </h4>

                    @if(count($definitions) > 0)
                        <div class="overflow-hidden border border-[var(--ui-border)]/40">
                            <table class="min-w-full divide-y divide-[var(--ui-border)]/40">
                                <thead class="bg-[var(--ui-muted-5)]">
                                    <tr>
                                        <th scope="col" class="py-3 pl-6 pr-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                            Label
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                            Typ
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                            Status
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                            Scope
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                            Aktionen
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
                                    @foreach($definitions as $def)
                                        <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                            <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                                <div>
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $def['label'] }}</span>
                                                    <span class="block text-xs text-[var(--ui-muted)]">{{ $def['name'] }}</span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4">
                                                <span class="text-sm text-[var(--ui-muted)]">{{ $def['type_label'] }}</span>
                                                @if(($def['type'] === 'select' || $def['type'] === 'file' || $def['type'] === 'lookup') && isset($def['options']['multiple']) && $def['options']['multiple'])
                                                    <span class="ml-1 text-xs text-[var(--ui-primary)]">(Mehrfach)</span>
                                                @endif
                                                @if($def['type'] === 'lookup' && isset($def['options']['lookup_id']))
                                                    @php
                                                        $lookupForDisplay = collect($this->availableLookups)->firstWhere('id', $def['options']['lookup_id']);
                                                    @endphp
                                                    @if($lookupForDisplay)
                                                        <span class="block text-xs text-[var(--ui-muted)]">{{ $lookupForDisplay['label'] }}</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4">
                                                <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                                    @if($def['is_mandatory'])
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-danger-5)] text-[var(--ui-danger)] border border-[var(--ui-danger)]/20" title="Pflichtfeld">
                                                            Pflicht
                                                        </span>
                                                    @elseif($def['is_required'])
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-warning-5)] text-[var(--ui-warning)] border border-[var(--ui-warning)]/20" title="Erforderlich für Fortschritt">
                                                            Erforderl.
                                                        </span>
                                                    @endif
                                                    @if($def['is_encrypted'] ?? false)
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40" title="Verschlüsselt">
                                                            @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                    @if($def['has_visibility_conditions'] ?? false)
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20" title="Hat Sichtbarkeitsbedingungen">
                                                            @svg('heroicon-o-eye', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                    @if($def['verify_by_llm'] ?? false)
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-success-5)] text-[var(--ui-success)] border border-[var(--ui-success)]/20" title="KI-Verifikation">
                                                            @svg('heroicon-o-sparkles', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                    @if(!empty($def['auto_fill_source']))
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-info-5)] text-[var(--ui-info)] border border-[var(--ui-info)]/20" title="AutoFill aktiv">
                                                            @svg('heroicon-o-bolt', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-center">
                                                @if($def['is_global'])
                                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">
                                                        Global
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40">
                                                        Spezifisch
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        wire:click="startEditDefinition({{ $def['id'] }})"
                                                        class="text-xs px-3 py-1.5 text-[var(--ui-primary)] hover:text-[var(--ui-primary)]/80 hover:bg-[var(--ui-primary-5)] transition-colors"
                                                    >
                                                        Bearbeiten
                                                    </button>
                                                    <button
                                                        wire:click="deleteDefinition({{ $def['id'] }})"
                                                        wire:confirm="Feld und alle zugehörigen Werte wirklich löschen?"
                                                        class="text-xs px-3 py-1.5 text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] transition-colors"
                                                    >
                                                        Löschen
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                            <p class="text-sm text-[var(--ui-muted)]">Noch keine Felder definiert.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        @elseif($activeTab === 'fields' && !$contextType)
            <div class="text-center py-12">
                <p class="text-[var(--ui-muted)]">Kein Kontext ausgewählt.</p>
                <p class="text-sm text-[var(--ui-muted)] mt-2">Wählen Sie einen Kontext aus, um Extra-Felder zu verwalten.</p>
            </div>

        {{-- Lookups Tab --}}
        @elseif($activeTab === 'lookups')
            <div class="space-y-6">
                {{-- Neuen Lookup erstellen --}}
                <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Neuen Lookup erstellen
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-ui-input-text
                            name="newLookup.label"
                            label="Label"
                            :required="true"
                            wire:model="newLookup.label"
                            placeholder="z.B. Nationalität"
                        />

                        <x-ui-input-textarea
                            name="newLookup.description"
                            label="Beschreibung"
                            wire:model="newLookup.description"
                            placeholder="Optionale Beschreibung..."
                            rows="1"
                        />

                        <div class="flex items-end">
                            <button
                                wire:click="createLookup"
                                class="px-4 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors text-sm font-medium"
                            >
                                Lookup erstellen
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Vorhandene Lookups --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Lookup Liste --}}
                    <div>
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                            Vorhandene Lookups
                        </h4>

                        @if(count($lookups) > 0)
                            <div class="border border-[var(--ui-border)]/40 divide-y divide-[var(--ui-border)]/40">
                                @foreach($lookups as $lookup)
                                    <div
                                        class="p-3 {{ $selectedLookupId === $lookup['id'] ? 'bg-[var(--ui-primary-5)]' : 'bg-[var(--ui-surface)] hover:bg-[var(--ui-muted-5)]' }} transition-colors cursor-pointer"
                                        wire:click="selectLookup({{ $lookup['id'] }})"
                                    >
                                        @if($editingLookupId === $lookup['id'])
                                            <div class="space-y-3" wire:click.stop>
                                                <x-ui-input-text
                                                    name="editLookup.label"
                                                    wire:model="editLookup.label"
                                                    size="sm"
                                                />
                                                <x-ui-input-text
                                                    name="editLookup.description"
                                                    wire:model="editLookup.description"
                                                    size="sm"
                                                    placeholder="Beschreibung..."
                                                />
                                                <div class="flex gap-2">
                                                    <button
                                                        wire:click="saveEditLookup"
                                                        class="text-xs px-3 py-1.5 text-white bg-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/90"
                                                    >
                                                        Speichern
                                                    </button>
                                                    <button
                                                        wire:click="cancelEditLookup"
                                                        class="text-xs px-3 py-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]"
                                                    >
                                                        Abbrechen
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $lookup['label'] }}</span>
                                                        @if($lookup['is_system'])
                                                            <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-warning-5)] text-[var(--ui-warning)] border border-[var(--ui-warning)]/20">
                                                                System
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="text-xs text-[var(--ui-muted)]">
                                                        {{ $lookup['name'] }} · {{ $lookup['values_count'] }} Werte
                                                    </span>
                                                    @if($lookup['description'])
                                                        <p class="text-xs text-[var(--ui-muted)] mt-1">{{ $lookup['description'] }}</p>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-1" wire:click.stop>
                                                    <button
                                                        wire:click="startEditLookup({{ $lookup['id'] }})"
                                                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)]"
                                                        title="Bearbeiten"
                                                    >
                                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                                    </button>
                                                    @if(!$lookup['is_system'])
                                                        <button
                                                            wire:click="deleteLookup({{ $lookup['id'] }})"
                                                            wire:confirm="Lookup und alle Werte wirklich löschen?"
                                                            class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-danger)]"
                                                            title="Löschen"
                                                        >
                                                            @svg('heroicon-o-trash', 'w-4 h-4')
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                <p class="text-sm text-[var(--ui-muted)]">Noch keine Lookups vorhanden.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Lookup Werte --}}
                    <div>
                        <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                            @if($this->selectedLookup)
                                Werte: {{ $this->selectedLookup['label'] }}
                            @else
                                Lookup auswählen
                            @endif
                        </h4>

                        @if($selectedLookupId)
                            {{-- Neuen Wert hinzufügen --}}
                            <div class="p-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 mb-4">
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <input
                                        type="text"
                                        wire:model="newLookupValueLabel"
                                        wire:keydown.enter.prevent="addLookupValue"
                                        class="px-3 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                        placeholder="Label (Anzeige)..."
                                    />
                                    <input
                                        type="text"
                                        wire:model="newLookupValueText"
                                        wire:keydown.enter.prevent="addLookupValue"
                                        class="px-3 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                        placeholder="Wert (optional)..."
                                    />
                                </div>
                                <button
                                    type="button"
                                    wire:click="addLookupValue"
                                    class="w-full px-3 py-1.5 bg-[var(--ui-primary)] text-white text-sm hover:bg-[var(--ui-primary)]/90"
                                >
                                    Wert hinzufügen
                                </button>
                                @error('newLookupValueText')
                                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Werte Liste --}}
                            @if(count($lookupValues) > 0)
                                <div class="border border-[var(--ui-border)]/40 divide-y divide-[var(--ui-border)]/40 max-h-80 overflow-y-auto">
                                    @foreach($lookupValues as $index => $value)
                                        <div class="p-2 {{ $value['is_active'] ? 'bg-[var(--ui-surface)]' : 'bg-[var(--ui-muted-5)] opacity-60' }} flex items-center justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-[var(--ui-secondary)]">{{ $value['label'] }}</span>
                                                @if($value['value'] !== $value['label'])
                                                    <span class="text-xs text-[var(--ui-muted)]">({{ $value['value'] }})</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <button
                                                    wire:click="moveLookupValueUp({{ $value['id'] }})"
                                                    class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] {{ $index === 0 ? 'opacity-30 cursor-not-allowed' : '' }}"
                                                    {{ $index === 0 ? 'disabled' : '' }}
                                                    title="Nach oben"
                                                >
                                                    @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                                </button>
                                                <button
                                                    wire:click="moveLookupValueDown({{ $value['id'] }})"
                                                    class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] {{ $index === count($lookupValues) - 1 ? 'opacity-30 cursor-not-allowed' : '' }}"
                                                    {{ $index === count($lookupValues) - 1 ? 'disabled' : '' }}
                                                    title="Nach unten"
                                                >
                                                    @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                                </button>
                                                <button
                                                    wire:click="toggleLookupValue({{ $value['id'] }})"
                                                    class="p-1 {{ $value['is_active'] ? 'text-[var(--ui-success)]' : 'text-[var(--ui-muted)]' }} hover:opacity-70"
                                                    title="{{ $value['is_active'] ? 'Deaktivieren' : 'Aktivieren' }}"
                                                >
                                                    @if($value['is_active'])
                                                        @svg('heroicon-o-eye', 'w-4 h-4')
                                                    @else
                                                        @svg('heroicon-o-eye-slash', 'w-4 h-4')
                                                    @endif
                                                </button>
                                                <button
                                                    wire:click="deleteLookupValue({{ $value['id'] }})"
                                                    wire:confirm="Wert wirklich löschen?"
                                                    class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-danger)]"
                                                    title="Löschen"
                                                >
                                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-6 border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                    <p class="text-xs text-[var(--ui-muted)]">Noch keine Werte vorhanden.</p>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-12 border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                <p class="text-sm text-[var(--ui-muted)]">Wählen Sie einen Lookup aus, um Werte zu verwalten.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-ui-modal>
</div>
