<div>
<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary)] to-[var(--ui-primary)]/70 flex items-center justify-center rounded-lg shadow-sm">
                    @svg('heroicon-o-squares-plus', 'w-6 h-6 text-white')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Extra-Felder konfigurieren</h3>
                <p class="text-sm text-[var(--ui-muted)] mt-0.5">
                    @if($contextId)
                        Individuelle Felder für diesen Kontext definieren und verwalten
                    @else
                        Felder für alle Einträge dieses Typs definieren und verwalten
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div>
        {{-- Tab Navigation --}}
        <div class="flex border-b border-[var(--ui-border)]/40 mb-8">
            <button
                wire:click="$set('activeTab', 'fields')"
                class="px-5 py-3 text-sm font-medium transition-all duration-200 {{ $activeTab === 'fields' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                    Felder
                </span>
            </button>
            <button
                wire:click="$set('activeTab', 'lookups')"
                class="px-5 py-3 text-sm font-medium transition-all duration-200 {{ $activeTab === 'lookups' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                <span class="flex items-center gap-2">
                    @svg('heroicon-o-book-open', 'w-4 h-4')
                    Lookups
                </span>
            </button>
        </div>

        {{-- ============================================ --}}
        {{-- FELDER TAB --}}
        {{-- ============================================ --}}
        @if($activeTab === 'fields' && $contextType)
            {{-- ========== EDITING MODE ========== --}}
            @if($editingDefinitionId)
                @php
                    $editingDef = collect($definitions)->firstWhere('id', $editingDefinitionId);
                    $currentTypeDesc = $this->typeDescriptions[$editField['type']] ?? null;
                @endphp
                <div class="space-y-6">
                    {{-- Edit Header --}}
                    <div class="flex items-center justify-between pb-5 border-b border-[var(--ui-border)]/40">
                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                wire:click="cancelEditDefinition"
                                class="p-2.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded-lg transition-all duration-200"
                                title="Zurück zur Übersicht"
                            >
                                @svg('heroicon-o-arrow-left', 'w-5 h-5')
                            </button>
                            <div>
                                <h4 class="text-lg font-semibold text-[var(--ui-secondary)]">
                                    Feld bearbeiten
                                </h4>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-sm text-[var(--ui-muted)]">{{ $editingDef['label'] ?? '' }}</span>
                                    <span class="text-xs text-[var(--ui-muted)]/60">·</span>
                                    <span class="text-xs text-[var(--ui-muted)]/60 font-mono">{{ $editingDef['name'] ?? '' }}</span>
                                </div>
                            </div>
                        </div>
                        <button
                            wire:click="saveEditDefinition"
                            class="px-5 py-2.5 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 rounded-lg transition-all duration-200 text-sm font-medium shadow-sm hover:shadow-md"
                        >
                            @svg('heroicon-o-check', 'w-4 h-4 inline mr-1.5')
                            Speichern
                        </button>
                    </div>

                    {{-- Edit Field Tabs --}}
                    <div class="flex gap-1 p-1 bg-[var(--ui-muted-5)] rounded-lg">
                        <button
                            wire:click="$set('editFieldTab', 'basis')"
                            class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $editFieldTab === 'basis' ? 'bg-white text-[var(--ui-primary)] shadow-sm' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                        >
                            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 inline mr-1.5')
                            Basis
                        </button>
                        @if(in_array($editField['type'], ['select', 'lookup', 'file']))
                            <button
                                wire:click="$set('editFieldTab', 'options')"
                                class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $editFieldTab === 'options' ? 'bg-white text-[var(--ui-primary)] shadow-sm' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                @svg('heroicon-o-adjustments-horizontal', 'w-4 h-4 inline mr-1.5')
                                Optionen
                            </button>
                        @endif
                        <button
                            wire:click="$set('editFieldTab', 'conditions')"
                            class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $editFieldTab === 'conditions' ? 'bg-white text-[var(--ui-primary)] shadow-sm' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                        >
                            @svg('heroicon-o-eye', 'w-4 h-4 inline mr-1.5')
                            Bedingungen
                            @if($editField['visibility']['enabled'] ?? false)
                                <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-[var(--ui-primary)] rounded-full"></span>
                            @endif
                        </button>
                        @if($editField['type'] === 'file')
                            <button
                                wire:click="$set('editFieldTab', 'verification')"
                                class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $editFieldTab === 'verification' ? 'bg-white text-[var(--ui-primary)] shadow-sm' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                @svg('heroicon-o-sparkles', 'w-4 h-4 inline mr-1.5')
                                KI-Verifikation
                                @if($editField['verify_by_llm'] ?? false)
                                    <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-[var(--ui-primary)] rounded-full"></span>
                                @endif
                            </button>
                        @endif
                        @if($editField['type'] !== 'file')
                            <button
                                wire:click="$set('editFieldTab', 'autofill')"
                                class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-all duration-200 {{ $editFieldTab === 'autofill' ? 'bg-white text-[var(--ui-primary)] shadow-sm' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                            >
                                @svg('heroicon-o-bolt', 'w-4 h-4 inline mr-1.5')
                                AutoFill
                                @if(!empty($editField['auto_fill_source']))
                                    <span class="ml-1 inline-flex items-center justify-center w-2 h-2 bg-[var(--ui-primary)] rounded-full"></span>
                                @endif
                            </button>
                        @endif
                    </div>

                    {{-- Tab Content --}}
                    <div class="p-6 bg-[var(--ui-surface)] border border-[var(--ui-border)]/30 rounded-lg">
                        {{-- ===== Basis Tab ===== --}}
                        @if($editFieldTab === 'basis')
                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <x-ui-input-text
                                        name="editField.label"
                                        label="Label"
                                        :required="true"
                                        wire:model="editField.label"
                                    />

                                    <x-ui-input-select
                                        name="editField.type"
                                        label="Feldtyp"
                                        :required="true"
                                        :options="$this->availableTypes"
                                        wire:model.live="editField.type"
                                        displayMode="dropdown"
                                    />
                                </div>

                                {{-- Feldtyp-Beschreibung --}}
                                @if($currentTypeDesc)
                                    <div class="flex items-start gap-3 p-4 bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/15 rounded-lg">
                                        <div class="flex-shrink-0 mt-0.5">
                                            @svg($currentTypeDesc['icon'], 'w-5 h-5 text-[var(--ui-primary)]')
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-[var(--ui-primary)]">{{ $this->availableTypes[$editField['type']] ?? $editField['type'] }}</p>
                                            <p class="text-sm text-[var(--ui-secondary)]/80 mt-0.5">{{ $currentTypeDesc['description'] }}</p>
                                        </div>
                                    </div>
                                @endif

                                {{-- Feld-Optionen --}}
                                <div class="pt-2">
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-3">Feld-Eigenschaften</label>
                                    <div class="flex flex-wrap gap-x-8 gap-y-3">
                                        <div class="flex items-start gap-2">
                                            <x-ui-input-checkbox
                                                name="editField.is_required"
                                                checkedLabel="Erforderlich (Fortschritt)"
                                                uncheckedLabel="Erforderlich (Fortschritt)"
                                                wire:model="editField.is_required"
                                            />
                                            <span class="text-[var(--ui-muted)] cursor-help" title="Zählt für den Vollständigkeits-Fortschritt, blockiert aber nicht das Speichern.">
                                                @svg('heroicon-o-information-circle', 'w-4 h-4')
                                            </span>
                                        </div>

                                        <div class="flex items-start gap-2">
                                            <x-ui-input-checkbox
                                                name="editField.is_mandatory"
                                                checkedLabel="Pflichtfeld (blockiert Speichern)"
                                                uncheckedLabel="Pflichtfeld (blockiert Speichern)"
                                                wire:model="editField.is_mandatory"
                                            />
                                            <span class="text-[var(--ui-muted)] cursor-help" title="Das Feld muss ausgefüllt sein, bevor der Eintrag gespeichert werden kann.">
                                                @svg('heroicon-o-information-circle', 'w-4 h-4')
                                            </span>
                                        </div>

                                        <div class="flex items-start gap-2">
                                            <x-ui-input-checkbox
                                                name="editField.is_encrypted"
                                                checkedLabel="Verschlüsselt"
                                                uncheckedLabel="Als verschlüsselt markieren"
                                                wire:model="editField.is_encrypted"
                                            />
                                            <span class="text-[var(--ui-muted)] cursor-help" title="Der Wert wird als sensibel markiert. Nützlich für vertrauliche Daten wie Gehälter oder persönliche Informationen.">
                                                @svg('heroicon-o-information-circle', 'w-4 h-4')
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ===== Options Tab (Select, Lookup, File) ===== --}}
                        @if($editFieldTab === 'options')
                            @if($editField['type'] === 'select')
                                <div class="space-y-5">
                                    <div class="flex items-start gap-2">
                                        <x-ui-input-checkbox
                                            name="editField.is_multiple"
                                            checkedLabel="Mehrfachauswahl aktiv"
                                            uncheckedLabel="Mehrfachauswahl aktivieren"
                                            wire:model="editField.is_multiple"
                                        />
                                        <span class="text-[var(--ui-muted)] cursor-help" title="Erlaubt die Auswahl von mehreren Werten gleichzeitig.">
                                            @svg('heroicon-o-information-circle', 'w-4 h-4')
                                        </span>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Auswahloptionen</label>
                                        <p class="text-xs text-[var(--ui-muted)] mb-3">Definieren Sie die Werte, die im Dropdown zur Auswahl stehen.</p>
                                        <div class="flex gap-2 mb-3">
                                            <input
                                                type="text"
                                                wire:model="editOptionText"
                                                wire:keydown.enter.prevent="addEditOption"
                                                class="flex-1 px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                                placeholder="Option eingeben und Enter drücken..."
                                            />
                                            <button
                                                type="button"
                                                wire:click="addEditOption"
                                                class="px-4 py-2 bg-[var(--ui-primary)] text-white text-sm rounded-md hover:bg-[var(--ui-primary)]/90 transition-colors"
                                            >
                                                @svg('heroicon-o-plus', 'w-4 h-4')
                                            </button>
                                        </div>

                                        @if(count($editField['options']) > 0)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($editField['options'] as $index => $option)
                                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[var(--ui-surface)] text-sm text-[var(--ui-secondary)] border border-[var(--ui-border)]/40 rounded-full">
                                                        {{ $option }}
                                                        <button
                                                            type="button"
                                                            wire:click="removeEditOption({{ $index }})"
                                                            class="text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                                                        >
                                                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                        </button>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2 p-3 bg-[var(--ui-muted-5)] rounded-md border border-dashed border-[var(--ui-border)]/40">
                                                @svg('heroicon-o-list-bullet', 'w-4 h-4 text-[var(--ui-muted)]')
                                                <p class="text-xs text-[var(--ui-muted)]">Noch keine Optionen hinzugefügt. Geben Sie oben die erste Option ein.</p>
                                            </div>
                                        @endif

                                        @error('editField.options')
                                            <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            @elseif($editField['type'] === 'lookup')
                                <div class="space-y-5">
                                    <div class="flex items-start gap-2">
                                        <x-ui-input-checkbox
                                            name="editField.is_multiple"
                                            checkedLabel="Mehrfachauswahl aktiv"
                                            uncheckedLabel="Mehrfachauswahl aktivieren"
                                            wire:model="editField.is_multiple"
                                        />
                                        <span class="text-[var(--ui-muted)] cursor-help" title="Erlaubt die Auswahl von mehreren Lookup-Werten gleichzeitig.">
                                            @svg('heroicon-o-information-circle', 'w-4 h-4')
                                        </span>
                                    </div>

                                    @if(count($this->availableLookups) > 0)
                                        <div>
                                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Lookup auswählen</label>
                                            <p class="text-xs text-[var(--ui-muted)] mb-3">Wählen Sie eine Lookup-Tabelle, deren Werte als Auswahloptionen dienen.</p>
                                            @php
                                                $lookupOptions = collect($this->availableLookups)->map(fn($l) => [
                                                    'value' => $l['id'],
                                                    'label' => $l['label'] . ' (' . $l['values_count'] . ' Werte)',
                                                ])->toArray();
                                            @endphp
                                            <x-ui-input-select
                                                name="editField.lookup_id"
                                                label=""
                                                :options="$lookupOptions"
                                                optionValue="value"
                                                optionLabel="label"
                                                :nullable="true"
                                                nullLabel="– Lookup auswählen –"
                                                wire:model.live="editField.lookup_id"
                                            />
                                        </div>
                                    @else
                                        <div class="flex items-start gap-3 p-4 bg-[var(--ui-warning-5)] border border-[var(--ui-warning)]/20 rounded-lg">
                                            @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-[var(--ui-warning)] flex-shrink-0 mt-0.5')
                                            <div>
                                                <p class="text-sm font-medium text-[var(--ui-secondary)]">Noch keine Lookups vorhanden</p>
                                                <p class="text-sm text-[var(--ui-muted)] mt-0.5">Erstellen Sie zuerst einen Lookup im Tab "Lookups", um ihn hier verwenden zu können.</p>
                                            </div>
                                        </div>
                                    @endif

                                    @error('editField.lookup_id')
                                        <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                                    @enderror
                                </div>
                            @elseif($editField['type'] === 'file')
                                <div class="space-y-4">
                                    <div class="flex items-start gap-2">
                                        <x-ui-input-checkbox
                                            name="editField.is_multiple"
                                            checkedLabel="Mehrere Dateien erlaubt"
                                            uncheckedLabel="Mehrere Dateien erlauben"
                                            wire:model="editField.is_multiple"
                                        />
                                        <span class="text-[var(--ui-muted)] cursor-help" title="Erlaubt das Hochladen mehrerer Dateien in diesem Feld.">
                                            @svg('heroicon-o-information-circle', 'w-4 h-4')
                                        </span>
                                    </div>
                                </div>
                            @endif
                        @endif

                        {{-- ===== Conditions Tab ===== --}}
                        @if($editFieldTab === 'conditions')
                            <div class="space-y-4">
                                <div class="flex items-start gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/20">
                                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-[var(--ui-muted)] flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-[var(--ui-muted)]">
                                        Mit bedingter Sichtbarkeit können Sie festlegen, dass dieses Feld nur angezeigt wird, wenn bestimmte andere Felder bestimmte Werte haben.
                                    </p>
                                </div>
                                <x-platform::condition-builder
                                    :visibility="$editField['visibility']"
                                    :availableFields="$this->conditionFields"
                                    :allOperators="$this->allOperators"
                                    :description="$this->visibilityDescription"
                                />
                            </div>
                        @endif

                        {{-- ===== Verification Tab (File only) ===== --}}
                        @if($editFieldTab === 'verification' && $editField['type'] === 'file')
                            <div class="space-y-5">
                                <div class="flex items-start gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/20">
                                    @svg('heroicon-o-sparkles', 'w-5 h-5 text-[var(--ui-muted)] flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-[var(--ui-muted)]">
                                        Die KI-Verifikation prüft hochgeladene Dateien automatisch anhand Ihrer Anweisungen und markiert sie als verifiziert oder abgelehnt.
                                    </p>
                                </div>

                                <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-[var(--ui-border)]/30 hover:bg-[var(--ui-muted-5)] transition-colors">
                                    <input
                                        type="checkbox"
                                        wire:model.live="editField.verify_by_llm"
                                        class="rounded border-[var(--ui-border)]/60 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                    />
                                    <div>
                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">KI-Verifikation aktivieren</span>
                                        <p class="text-xs text-[var(--ui-muted)] mt-0.5">Hochgeladene Dateien werden automatisch durch KI geprüft</p>
                                    </div>
                                </label>

                                @if($editField['verify_by_llm'])
                                    <div>
                                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Verifikations-Anweisungen</label>
                                        <p class="text-xs text-[var(--ui-muted)] mb-2">Beschreiben Sie, was die KI bei der hochgeladenen Datei prüfen soll.</p>
                                        <textarea
                                            wire:model="editField.verify_instructions"
                                            placeholder="z.B.: Prüfe ob dies ein gültiger Personalausweis ist. Ist er lesbar und nicht abgelaufen?"
                                            class="w-full px-4 py-3 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                            rows="3"
                                        ></textarea>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- ===== AutoFill Tab (non-file) ===== --}}
                        @if($editFieldTab === 'autofill' && $editField['type'] !== 'file')
                            <div class="space-y-5">
                                <div class="flex items-start gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/20">
                                    @svg('heroicon-o-bolt', 'w-5 h-5 text-[var(--ui-muted)] flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-[var(--ui-muted)]">
                                        AutoFill füllt leere Felder automatisch per Scheduler. Wählen Sie eine Quelle und geben Sie Anweisungen, wie der Wert ermittelt werden soll.
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">AutoFill-Quelle</label>
                                    <p class="text-xs text-[var(--ui-muted)] mb-3">Wählen Sie, wie das Feld automatisch befüllt werden soll.</p>
                                    @php
                                        $autoFillOptions = collect($this->autoFillSources)->map(fn($label, $value) => [
                                            'value' => $value,
                                            'label' => $label,
                                        ])->values()->toArray();
                                    @endphp
                                    <x-ui-input-select
                                        name="editField.auto_fill_source"
                                        label=""
                                        :options="$autoFillOptions"
                                        optionValue="value"
                                        optionLabel="label"
                                        :nullable="true"
                                        nullLabel="Deaktiviert"
                                        wire:model.live="editField.auto_fill_source"
                                    />
                                </div>

                                {{-- Quellen-Beschreibung --}}
                                @if(!empty($editField['auto_fill_source']) && isset($this->autoFillSourceDescriptions[$editField['auto_fill_source']]))
                                    <div class="flex items-start gap-3 p-3 bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/15 rounded-lg">
                                        @svg('heroicon-o-light-bulb', 'w-5 h-5 text-[var(--ui-primary)] flex-shrink-0 mt-0.5')
                                        <p class="text-sm text-[var(--ui-secondary)]/80">{{ $this->autoFillSourceDescriptions[$editField['auto_fill_source']] }}</p>
                                    </div>
                                @endif

                                @if(!empty($editField['auto_fill_source']))
                                    <div>
                                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">AutoFill-Anweisungen</label>
                                        <p class="text-xs text-[var(--ui-muted)] mb-2">Beschreiben Sie, wie der Wert ermittelt werden soll.</p>
                                        <textarea
                                            wire:model="editField.auto_fill_prompt"
                                            placeholder="{{ $editField['auto_fill_source'] === 'websearch' ? 'z.B.: Suche die Firmenadresse und Telefonnummer' : 'z.B.: Analysiere die vorhandenen Daten und schlage einen passenden Wert vor' }}"
                                            class="w-full px-4 py-3 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                            rows="2"
                                        ></textarea>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
            {{-- ========== LIST MODE ========== --}}
            <div class="space-y-8">
                {{-- Neues Feld erstellen --}}
                <div class="p-6 bg-[var(--ui-surface)] border border-[var(--ui-border)]/30 rounded-lg">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-8 h-8 bg-[var(--ui-primary-5)] rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-plus-circle', 'w-5 h-5 text-[var(--ui-primary)]')
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Neues Feld erstellen</h4>
                            <p class="text-xs text-[var(--ui-muted)]">Fügen Sie ein benutzerdefiniertes Feld hinzu, um zusätzliche Informationen zu erfassen.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <x-ui-input-text
                            name="newField.label"
                            label="Label"
                            :required="true"
                            wire:model="newField.label"
                            placeholder="z.B. Gehaltsvorstellung"
                        />

                        <x-ui-input-select
                            name="newField.type"
                            label="Feldtyp"
                            :required="true"
                            :options="$this->availableTypes"
                            wire:model.live="newField.type"
                            displayMode="dropdown"
                        />

                        <div class="flex items-end gap-4 flex-wrap">
                            <div class="flex items-start gap-2">
                                <x-ui-input-checkbox
                                    name="newField.is_required"
                                    checkedLabel="Erforderlich"
                                    uncheckedLabel="Erforderlich"
                                    wire:model="newField.is_required"
                                />
                                <span class="text-[var(--ui-muted)] cursor-help" title="Zählt für den Fortschritt, blockiert aber nicht das Speichern.">
                                    @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                                </span>
                            </div>

                            <div class="flex items-start gap-2">
                                <x-ui-input-checkbox
                                    name="newField.is_mandatory"
                                    checkedLabel="Pflichtfeld"
                                    uncheckedLabel="Pflichtfeld"
                                    wire:model="newField.is_mandatory"
                                />
                                <span class="text-[var(--ui-muted)] cursor-help" title="Muss ausgefüllt sein, bevor gespeichert werden kann.">
                                    @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                                </span>
                            </div>

                            <div class="flex items-start gap-2">
                                <x-ui-input-checkbox
                                    name="newField.is_encrypted"
                                    checkedLabel="Verschlüsselt"
                                    uncheckedLabel="Verschlüsselt"
                                    wire:model="newField.is_encrypted"
                                />
                                <span class="text-[var(--ui-muted)] cursor-help" title="Markiert den Wert als vertraulich.">
                                    @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Feldtyp-Beschreibung --}}
                    @php
                        $newTypeDesc = $this->typeDescriptions[$newField['type']] ?? null;
                    @endphp
                    @if($newTypeDesc)
                        <div class="flex items-start gap-3 p-3 mt-4 bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/15 rounded-lg">
                            <div class="flex-shrink-0 mt-0.5">
                                @svg($newTypeDesc['icon'], 'w-5 h-5 text-[var(--ui-primary)]')
                            </div>
                            <div>
                                <p class="text-sm font-medium text-[var(--ui-primary)]">{{ $this->availableTypes[$newField['type']] ?? $newField['type'] }}</p>
                                <p class="text-sm text-[var(--ui-secondary)]/80 mt-0.5">{{ $newTypeDesc['description'] }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Select-Optionen --}}
                    @if($newField['type'] === 'select')
                        <div class="mt-5 p-5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Auswahloptionen</span>
                                    <p class="text-xs text-[var(--ui-muted)] mt-0.5">Definieren Sie die Werte, die zur Auswahl stehen.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <x-ui-input-checkbox
                                        name="newField.is_multiple"
                                        checkedLabel="Mehrfachauswahl"
                                        uncheckedLabel="Mehrfachauswahl"
                                        wire:model="newField.is_multiple"
                                    />
                                    <span class="text-[var(--ui-muted)] cursor-help" title="Erlaubt die Auswahl mehrerer Werte gleichzeitig.">
                                        @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                                    </span>
                                </div>
                            </div>

                            <div class="flex gap-2 mb-3">
                                <input
                                    type="text"
                                    wire:model="newOptionText"
                                    wire:keydown.enter.prevent="addNewOption"
                                    class="flex-1 px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                    placeholder="Option eingeben und Enter drücken..."
                                />
                                <button
                                    type="button"
                                    wire:click="addNewOption"
                                    class="px-4 py-2 bg-[var(--ui-primary)] text-white text-sm rounded-md hover:bg-[var(--ui-primary)]/90 transition-colors"
                                >
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                </button>
                            </div>

                            @if(count($newField['options']) > 0)
                                <div class="flex flex-wrap gap-2">
                                    @foreach($newField['options'] as $index => $option)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[var(--ui-surface)] text-sm text-[var(--ui-secondary)] border border-[var(--ui-border)]/40 rounded-full">
                                            {{ $option }}
                                            <button
                                                type="button"
                                                wire:click="removeNewOption({{ $index }})"
                                                class="text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                                            >
                                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                            </button>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex items-center gap-2 p-3 bg-[var(--ui-surface)] rounded-md border border-dashed border-[var(--ui-border)]/40">
                                    @svg('heroicon-o-list-bullet', 'w-4 h-4 text-[var(--ui-muted)]')
                                    <p class="text-xs text-[var(--ui-muted)]">Noch keine Optionen. Geben Sie oben die erste Option ein.</p>
                                </div>
                            @endif

                            @error('newField.options')
                                <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Lookup-Optionen --}}
                    @if($newField['type'] === 'lookup')
                        <div class="mt-5 p-5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">Lookup auswählen</span>
                                    <p class="text-xs text-[var(--ui-muted)] mt-0.5">Wählen Sie eine Lookup-Tabelle als Quelle für die Auswahloptionen.</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <x-ui-input-checkbox
                                        name="newField.is_multiple"
                                        checkedLabel="Mehrfachauswahl"
                                        uncheckedLabel="Mehrfachauswahl"
                                        wire:model="newField.is_multiple"
                                    />
                                    <span class="text-[var(--ui-muted)] cursor-help" title="Erlaubt die Auswahl mehrerer Lookup-Werte gleichzeitig.">
                                        @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                                    </span>
                                </div>
                            </div>

                            @if(count($this->availableLookups) > 0)
                                @php
                                    $lookupOptionsNew = collect($this->availableLookups)->map(fn($l) => [
                                        'value' => $l['id'],
                                        'label' => $l['label'] . ' (' . $l['values_count'] . ' Werte)',
                                    ])->toArray();
                                @endphp
                                <x-ui-input-select
                                    name="newField.lookup_id"
                                    label=""
                                    :options="$lookupOptionsNew"
                                    optionValue="value"
                                    optionLabel="label"
                                    :nullable="true"
                                    nullLabel="– Lookup auswählen –"
                                    wire:model.live="newField.lookup_id"
                                />
                                <p class="text-xs text-[var(--ui-muted)] mt-2">
                                    Lookups können im Tab "Lookups" erstellt und verwaltet werden.
                                </p>
                            @else
                                <div class="flex items-start gap-3 p-3 bg-[var(--ui-warning-5)] border border-[var(--ui-warning)]/20 rounded-lg">
                                    @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-[var(--ui-warning)] flex-shrink-0 mt-0.5')
                                    <div>
                                        <p class="text-sm font-medium text-[var(--ui-secondary)]">Noch keine Lookups vorhanden</p>
                                        <p class="text-sm text-[var(--ui-muted)] mt-0.5">Erstellen Sie zuerst einen Lookup im Tab "Lookups".</p>
                                    </div>
                                </div>
                            @endif

                            @error('newField.lookup_id')
                                <p class="mt-2 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- File-Optionen --}}
                    @if($newField['type'] === 'file')
                        <div class="mt-5 p-5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg">
                            <div class="flex items-start gap-2 mb-4">
                                <x-ui-input-checkbox
                                    name="newField.is_multiple"
                                    checkedLabel="Mehrere Dateien erlaubt"
                                    uncheckedLabel="Mehrere Dateien erlauben"
                                    wire:model="newField.is_multiple"
                                />
                                <span class="text-[var(--ui-muted)] cursor-help" title="Erlaubt das Hochladen mehrerer Dateien in diesem Feld.">
                                    @svg('heroicon-o-information-circle', 'w-3.5 h-3.5')
                                </span>
                            </div>
                        </div>

                        {{-- LLM-Verifikation --}}
                        <div class="mt-4 p-5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg">
                            <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border border-[var(--ui-border)]/30 hover:bg-[var(--ui-surface)] transition-colors">
                                <input
                                    type="checkbox"
                                    wire:model.live="newField.verify_by_llm"
                                    class="rounded border-[var(--ui-border)]/60 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                />
                                <div>
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">KI-Verifikation aktivieren</span>
                                    <p class="text-xs text-[var(--ui-muted)] mt-0.5">Hochgeladene Dateien werden automatisch durch KI geprüft</p>
                                </div>
                            </label>

                            @if($newField['verify_by_llm'])
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Verifikations-Anweisungen</label>
                                    <p class="text-xs text-[var(--ui-muted)] mb-2">Beschreiben Sie, was die KI bei der hochgeladenen Datei prüfen soll.</p>
                                    <textarea
                                        wire:model="newField.verify_instructions"
                                        placeholder="z.B.: Prüfe ob dies ein gültiger Personalausweis ist. Ist er lesbar und nicht abgelaufen?"
                                        class="w-full px-4 py-3 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                        rows="3"
                                    ></textarea>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- AutoFill Optionen (für alle Feldtypen außer file) --}}
                    @if($newField['type'] !== 'file')
                        <div class="mt-5 p-5 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-bolt', 'w-4 h-4 text-[var(--ui-muted)]')
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">AutoFill</span>
                                </div>
                                @php
                                    $autoFillOptionsNew = collect($this->autoFillSources)->map(fn($label, $value) => [
                                        'value' => $value,
                                        'label' => $label,
                                    ])->values()->toArray();
                                @endphp
                                <div class="flex-1 max-w-xs">
                                    <x-ui-input-select
                                        name="newField.auto_fill_source"
                                        label=""
                                        :options="$autoFillOptionsNew"
                                        optionValue="value"
                                        optionLabel="label"
                                        :nullable="true"
                                        nullLabel="Deaktiviert"
                                        wire:model.live="newField.auto_fill_source"
                                    />
                                </div>
                            </div>

                            {{-- Quellen-Beschreibung --}}
                            @if(!empty($newField['auto_fill_source']) && isset($this->autoFillSourceDescriptions[$newField['auto_fill_source']]))
                                <div class="flex items-start gap-3 p-3 mb-4 bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/15 rounded-lg">
                                    @svg('heroicon-o-light-bulb', 'w-5 h-5 text-[var(--ui-primary)] flex-shrink-0 mt-0.5')
                                    <p class="text-sm text-[var(--ui-secondary)]/80">{{ $this->autoFillSourceDescriptions[$newField['auto_fill_source']] }}</p>
                                </div>
                            @endif

                            @if(!empty($newField['auto_fill_source']))
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">AutoFill-Anweisungen</label>
                                    <p class="text-xs text-[var(--ui-muted)] mb-2">Beschreiben Sie, wie der Wert ermittelt werden soll.</p>
                                    <textarea
                                        wire:model="newField.auto_fill_prompt"
                                        placeholder="{{ $newField['auto_fill_source'] === 'websearch' ? 'z.B.: Suche die Firmenadresse und Telefonnummer' : 'z.B.: Analysiere die vorhandenen Daten und schlage einen passenden Wert vor' }}"
                                        class="w-full px-4 py-3 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                        rows="2"
                                    ></textarea>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-5 flex justify-end">
                        <button
                            wire:click="createDefinition"
                            class="px-5 py-2.5 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 rounded-lg transition-all duration-200 text-sm font-medium shadow-sm hover:shadow-md"
                        >
                            @svg('heroicon-o-plus', 'w-4 h-4 inline mr-1.5')
                            Feld erstellen
                        </button>
                    </div>
                </div>

                {{-- Vorhandene Definitionen --}}
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 bg-[var(--ui-muted-5)] rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-rectangle-stack', 'w-5 h-5 text-[var(--ui-muted)]')
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Vorhandene Felder</h4>
                            <p class="text-xs text-[var(--ui-muted)]">Klicken Sie auf "Bearbeiten", um ein Feld zu konfigurieren.</p>
                        </div>
                    </div>

                    @if(count($definitions) > 0)
                        <div class="overflow-hidden border border-[var(--ui-border)]/30 rounded-lg">
                            <table class="min-w-full divide-y divide-[var(--ui-border)]/30">
                                <thead class="bg-[var(--ui-muted-5)]">
                                    <tr>
                                        <th scope="col" class="py-3 pl-5 pr-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
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
                                <tbody class="divide-y divide-[var(--ui-border)]/20 bg-[var(--ui-surface)]">
                                    @foreach($definitions as $def)
                                        @php $defTypeDesc = $this->typeDescriptions[$def['type']] ?? null; @endphp
                                        <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors duration-150">
                                            <td class="whitespace-nowrap py-4 pl-5 pr-3">
                                                <div class="flex items-center gap-3">
                                                    @if($defTypeDesc)
                                                        <div class="flex-shrink-0 w-8 h-8 bg-[var(--ui-muted-5)] rounded-md flex items-center justify-center">
                                                            @svg($defTypeDesc['icon'], 'w-4 h-4 text-[var(--ui-muted)]')
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $def['label'] }}</span>
                                                        <span class="block text-xs text-[var(--ui-muted)] font-mono">{{ $def['name'] }}</span>
                                                    </div>
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
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-danger-5)] text-[var(--ui-danger)] border border-[var(--ui-danger)]/20 rounded" title="Pflichtfeld – blockiert Speichern">
                                                            Pflicht
                                                        </span>
                                                    @elseif($def['is_required'])
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-warning-5)] text-[var(--ui-warning)] border border-[var(--ui-warning)]/20 rounded" title="Erforderlich für Fortschritt">
                                                            Erforderl.
                                                        </span>
                                                    @endif
                                                    @if($def['is_encrypted'] ?? false)
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40 rounded" title="Verschlüsselt gespeichert">
                                                            @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                    @if($def['has_visibility_conditions'] ?? false)
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20 rounded" title="Bedingte Sichtbarkeit aktiv">
                                                            @svg('heroicon-o-eye', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                    @if($def['verify_by_llm'] ?? false)
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-success-5)] text-[var(--ui-success)] border border-[var(--ui-success)]/20 rounded" title="KI-Verifikation aktiv">
                                                            @svg('heroicon-o-sparkles', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                    @if(!empty($def['auto_fill_source']))
                                                        <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-info-5)] text-[var(--ui-info)] border border-[var(--ui-info)]/20 rounded" title="AutoFill aktiv ({{ $this->autoFillSources[$def['auto_fill_source']] ?? $def['auto_fill_source'] }})">
                                                            @svg('heroicon-o-bolt', 'w-3 h-3')
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-center">
                                                @if($def['is_global'])
                                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20 rounded-full">
                                                        Global
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40 rounded-full">
                                                        Spezifisch
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button
                                                        wire:click="startEditDefinition({{ $def['id'] }})"
                                                        class="text-xs px-3 py-1.5 text-[var(--ui-primary)] hover:text-[var(--ui-primary)]/80 hover:bg-[var(--ui-primary-5)] rounded-md transition-all duration-200"
                                                    >
                                                        @svg('heroicon-o-pencil', 'w-3.5 h-3.5 inline mr-1')
                                                        Bearbeiten
                                                    </button>
                                                    <button
                                                        wire:click="deleteDefinition({{ $def['id'] }})"
                                                        wire:confirm="Feld und alle zugehörigen Werte wirklich löschen?"
                                                        class="text-xs px-3 py-1.5 text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] rounded-md transition-all duration-200"
                                                    >
                                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12 border border-dashed border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] rounded-lg">
                            @svg('heroicon-o-rectangle-stack', 'w-10 h-10 text-[var(--ui-muted)]/40 mx-auto mb-3')
                            <p class="text-sm font-medium text-[var(--ui-muted)]">Noch keine Felder definiert</p>
                            <p class="text-xs text-[var(--ui-muted)] mt-1">Erstellen Sie oben Ihr erstes Extra-Feld.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        @elseif($activeTab === 'fields' && !$contextType)
            <div class="text-center py-16">
                @svg('heroicon-o-cursor-arrow-rays', 'w-12 h-12 text-[var(--ui-muted)]/30 mx-auto mb-4')
                <p class="text-[var(--ui-muted)] font-medium">Kein Kontext ausgewählt</p>
                <p class="text-sm text-[var(--ui-muted)] mt-2">Wählen Sie einen Kontext aus, um Extra-Felder zu verwalten.</p>
            </div>

        {{-- ============================================ --}}
        {{-- LOOKUPS TAB --}}
        {{-- ============================================ --}}
        @elseif($activeTab === 'lookups')
            <div class="space-y-8">
                {{-- Neuen Lookup erstellen --}}
                <div class="p-6 bg-[var(--ui-surface)] border border-[var(--ui-border)]/30 rounded-lg">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-8 h-8 bg-[var(--ui-primary-5)] rounded-lg flex items-center justify-center">
                            @svg('heroicon-o-plus-circle', 'w-5 h-5 text-[var(--ui-primary)]')
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Neuen Lookup erstellen</h4>
                            <p class="text-xs text-[var(--ui-muted)]">Lookups sind zentrale Wertelisten, die in mehreren Feldern wiederverwendet werden können.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
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
                                class="px-5 py-2.5 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 rounded-lg transition-all duration-200 text-sm font-medium shadow-sm hover:shadow-md"
                            >
                                @svg('heroicon-o-plus', 'w-4 h-4 inline mr-1.5')
                                Lookup erstellen
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Vorhandene Lookups --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Lookup Liste --}}
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 bg-[var(--ui-muted-5)] rounded-lg flex items-center justify-center">
                                @svg('heroicon-o-book-open', 'w-5 h-5 text-[var(--ui-muted)]')
                            </div>
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Vorhandene Lookups</h4>
                        </div>

                        @if(count($lookups) > 0)
                            <div class="border border-[var(--ui-border)]/30 divide-y divide-[var(--ui-border)]/20 rounded-lg overflow-hidden">
                                @foreach($lookups as $lookup)
                                    <div
                                        class="p-3.5 {{ $selectedLookupId === $lookup['id'] ? 'bg-[var(--ui-primary-5)] border-l-2 border-l-[var(--ui-primary)]' : 'bg-[var(--ui-surface)] hover:bg-[var(--ui-muted-5)]' }} transition-colors duration-150 cursor-pointer"
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
                                                        class="text-xs px-3 py-1.5 text-white bg-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/90 rounded-md transition-colors"
                                                    >
                                                        Speichern
                                                    </button>
                                                    <button
                                                        wire:click="cancelEditLookup"
                                                        class="text-xs px-3 py-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] rounded-md transition-colors"
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
                                                            <span class="px-1.5 py-0.5 text-xs bg-[var(--ui-warning-5)] text-[var(--ui-warning)] border border-[var(--ui-warning)]/20 rounded">
                                                                System
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="text-xs text-[var(--ui-muted)]">
                                                        <span class="font-mono">{{ $lookup['name'] }}</span> · {{ $lookup['values_count'] }} Werte
                                                    </span>
                                                    @if($lookup['description'])
                                                        <p class="text-xs text-[var(--ui-muted)] mt-1">{{ $lookup['description'] }}</p>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-1" wire:click.stop>
                                                    <button
                                                        wire:click="startEditLookup({{ $lookup['id'] }})"
                                                        class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-primary-5)] rounded-md transition-colors"
                                                        title="Bearbeiten"
                                                    >
                                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                                    </button>
                                                    @if(!$lookup['is_system'])
                                                        <button
                                                            wire:click="deleteLookup({{ $lookup['id'] }})"
                                                            wire:confirm="Lookup und alle Werte wirklich löschen?"
                                                            class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded-md transition-colors"
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
                            <div class="text-center py-12 border border-dashed border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] rounded-lg">
                                @svg('heroicon-o-book-open', 'w-10 h-10 text-[var(--ui-muted)]/40 mx-auto mb-3')
                                <p class="text-sm font-medium text-[var(--ui-muted)]">Noch keine Lookups vorhanden</p>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Erstellen Sie oben Ihren ersten Lookup.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Lookup Werte --}}
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 bg-[var(--ui-muted-5)] rounded-lg flex items-center justify-center">
                                @svg('heroicon-o-queue-list', 'w-5 h-5 text-[var(--ui-muted)]')
                            </div>
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">
                                @if($this->selectedLookup)
                                    Werte: {{ $this->selectedLookup['label'] }}
                                @else
                                    Lookup-Werte
                                @endif
                            </h4>
                        </div>

                        @if($selectedLookupId)
                            {{-- Neuen Wert hinzufügen --}}
                            <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/30 rounded-lg mb-4">
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <input
                                        type="text"
                                        wire:model="newLookupValueLabel"
                                        wire:keydown.enter.prevent="addLookupValue"
                                        class="px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                        placeholder="Label (Anzeige)..."
                                    />
                                    <input
                                        type="text"
                                        wire:model="newLookupValueText"
                                        wire:keydown.enter.prevent="addLookupValue"
                                        class="px-3 py-2 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent transition-shadow"
                                        placeholder="Wert (optional)..."
                                    />
                                </div>
                                <p class="text-xs text-[var(--ui-muted)] mb-2">Label = Anzeige im Dropdown, Wert = interner Schlüssel (wenn leer, wird Label verwendet)</p>
                                <button
                                    type="button"
                                    wire:click="addLookupValue"
                                    class="w-full px-3 py-2 bg-[var(--ui-primary)] text-white text-sm rounded-md hover:bg-[var(--ui-primary)]/90 transition-colors"
                                >
                                    @svg('heroicon-o-plus', 'w-4 h-4 inline mr-1')
                                    Wert hinzufügen
                                </button>
                                @error('newLookupValueText')
                                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Werte Liste --}}
                            @if(count($lookupValues) > 0)
                                <div class="border border-[var(--ui-border)]/30 divide-y divide-[var(--ui-border)]/20 max-h-80 overflow-y-auto rounded-lg">
                                    @foreach($lookupValues as $index => $value)
                                        <div class="p-2.5 {{ $value['is_active'] ? 'bg-[var(--ui-surface)]' : 'bg-[var(--ui-muted-5)] opacity-60' }} flex items-center justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-[var(--ui-secondary)]">{{ $value['label'] }}</span>
                                                @if($value['value'] !== $value['label'])
                                                    <span class="text-xs text-[var(--ui-muted)] font-mono ml-1">({{ $value['value'] }})</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-0.5">
                                                <button
                                                    wire:click="moveLookupValueUp({{ $value['id'] }})"
                                                    class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded-md transition-colors {{ $index === 0 ? 'opacity-30 cursor-not-allowed' : '' }}"
                                                    {{ $index === 0 ? 'disabled' : '' }}
                                                    title="Nach oben"
                                                >
                                                    @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                                </button>
                                                <button
                                                    wire:click="moveLookupValueDown({{ $value['id'] }})"
                                                    class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded-md transition-colors {{ $index === count($lookupValues) - 1 ? 'opacity-30 cursor-not-allowed' : '' }}"
                                                    {{ $index === count($lookupValues) - 1 ? 'disabled' : '' }}
                                                    title="Nach unten"
                                                >
                                                    @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                                </button>
                                                <button
                                                    wire:click="toggleLookupValue({{ $value['id'] }})"
                                                    class="p-1.5 {{ $value['is_active'] ? 'text-[var(--ui-success)]' : 'text-[var(--ui-muted)]' }} hover:opacity-70 hover:bg-[var(--ui-muted-5)] rounded-md transition-colors"
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
                                                    class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded-md transition-colors"
                                                    title="Löschen"
                                                >
                                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8 border border-dashed border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] rounded-lg">
                                    @svg('heroicon-o-queue-list', 'w-8 h-8 text-[var(--ui-muted)]/40 mx-auto mb-2')
                                    <p class="text-xs text-[var(--ui-muted)]">Noch keine Werte vorhanden.</p>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-16 border border-dashed border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] rounded-lg">
                                @svg('heroicon-o-arrow-left', 'w-8 h-8 text-[var(--ui-muted)]/30 mx-auto mb-3')
                                <p class="text-sm text-[var(--ui-muted)]">Wählen Sie links einen Lookup aus,</p>
                                <p class="text-sm text-[var(--ui-muted)]">um dessen Werte zu verwalten.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-ui-modal>
</div>
