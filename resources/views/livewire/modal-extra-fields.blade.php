<div>
<x-ui-modal size="lg" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-[var(--ui-primary-5)] flex items-center justify-center">
                    @svg('heroicon-o-squares-plus', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Extra-Felder Definitionen</h3>
                <p class="text-sm text-[var(--ui-muted)] mt-1">Felder für alle Einträge dieses Typs definieren</p>
            </div>
        </div>
    </x-slot>

    <div>
        @if($contextType && !$contextId)
            <!-- Definitionen -->
            <div class="space-y-6">
                <!-- Neues Feld erstellen -->
                <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Neues Feld erstellen
                    </h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Label <span class="text-[var(--ui-danger)]">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="newField.label"
                                class="w-full px-4 py-2 border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                                placeholder="z.B. Gehaltsvorstellung"
                            />
                            @error('newField.label')
                                <p class="mt-1 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Typ <span class="text-[var(--ui-danger)]">*</span>
                            </label>
                            <select
                                wire:model="newField.type"
                                class="w-full px-4 py-2 border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                            >
                                @foreach($this->availableTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end gap-4 flex-wrap">
                            <label class="flex items-center gap-2 pb-2">
                                <input
                                    type="checkbox"
                                    wire:model="newField.is_required"
                                    class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] focus:ring-[var(--ui-primary)]"
                                />
                                <span class="text-sm text-[var(--ui-secondary)]">Pflichtfeld</span>
                            </label>

                            <label class="flex items-center gap-2 pb-2" title="Wert wird verschlüsselt in der Datenbank gespeichert">
                                <input
                                    type="checkbox"
                                    wire:model="newField.is_encrypted"
                                    class="w-4 h-4 text-[var(--ui-warning)] border-[var(--ui-border)] focus:ring-[var(--ui-warning)]"
                                />
                                <span class="text-sm text-[var(--ui-secondary)] flex items-center gap-1">
                                    @svg('heroicon-o-lock-closed', 'w-3.5 h-3.5')
                                    Verschlüsselt
                                </span>
                            </label>

                            <button
                                wire:click="createDefinition"
                                class="px-4 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors text-sm font-medium"
                            >
                                Erstellen
                            </button>
                        </div>
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
                                            Pflicht
                                        </th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                            Verschl.
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
                                                @if($editingDefinitionId === $def['id'])
                                                    <input
                                                        type="text"
                                                        wire:model="editField.label"
                                                        class="px-2 py-1 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                                    />
                                                @else
                                                    <div>
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $def['label'] }}</span>
                                                        <span class="block text-xs text-[var(--ui-muted)]">{{ $def['name'] }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4">
                                                @if($editingDefinitionId === $def['id'])
                                                    <select
                                                        wire:model="editField.type"
                                                        class="px-2 py-1 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                                    >
                                                        @foreach($this->availableTypes as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-sm text-[var(--ui-muted)]">{{ $def['type_label'] }}</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-center">
                                                @if($editingDefinitionId === $def['id'])
                                                    <input
                                                        type="checkbox"
                                                        wire:model="editField.is_required"
                                                        class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] focus:ring-[var(--ui-primary)]"
                                                    />
                                                @else
                                                    @if($def['is_required'])
                                                        @svg('heroicon-o-check', 'w-5 h-5 text-[var(--ui-success)] mx-auto')
                                                    @else
                                                        @svg('heroicon-o-minus', 'w-5 h-5 text-[var(--ui-muted)] mx-auto')
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-center">
                                                @if($editingDefinitionId === $def['id'])
                                                    <input
                                                        type="checkbox"
                                                        wire:model="editField.is_encrypted"
                                                        class="w-4 h-4 text-[var(--ui-warning)] border-[var(--ui-border)] focus:ring-[var(--ui-warning)]"
                                                    />
                                                @else
                                                    @if($def['is_encrypted'] ?? false)
                                                        @svg('heroicon-o-lock-closed', 'w-5 h-5 text-[var(--ui-warning)] mx-auto')
                                                    @else
                                                        @svg('heroicon-o-lock-open', 'w-5 h-5 text-[var(--ui-muted)] mx-auto')
                                                    @endif
                                                @endif
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
                                                @if($editingDefinitionId === $def['id'])
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button
                                                            wire:click="saveEditDefinition"
                                                            class="text-xs px-3 py-1.5 text-white bg-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/90 transition-colors"
                                                        >
                                                            Speichern
                                                        </button>
                                                        <button
                                                            wire:click="cancelEditDefinition"
                                                            class="text-xs px-3 py-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                                                        >
                                                            Abbrechen
                                                        </button>
                                                    </div>
                                                @else
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button
                                                            wire:click="startEditDefinition({{ $def['id'] }})"
                                                            class="text-xs px-3 py-1.5 text-[var(--ui-primary)] hover:text-[var(--ui-primary)]/80 hover:bg-[var(--ui-primary-5)] transition-colors"
                                                        >
                                                            Bearbeiten
                                                        </button>
                                                        <button
                                                            wire:click="deleteDefinition({{ $def['id'] }})"
                                                            wire:confirm="Feld wirklich löschen? Nur möglich, wenn keine Werte existieren."
                                                            class="text-xs px-3 py-1.5 text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] transition-colors"
                                                        >
                                                            Löschen
                                                        </button>
                                                    </div>
                                                @endif
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

        @elseif(!$contextType)
            <div class="text-center py-12">
                <p class="text-[var(--ui-muted)]">Kein Kontext ausgewählt.</p>
                <p class="text-sm text-[var(--ui-muted)] mt-2">Wählen Sie einen Kontext aus, um Extra-Felder zu verwalten.</p>
            </div>
        @else
            {{-- contextId ist gesetzt - Modal sollte nicht geöffnet werden, Werte werden inline bearbeitet --}}
            <div class="text-center py-12">
                <p class="text-[var(--ui-muted)]">Werte können direkt auf der Detailseite bearbeitet werden.</p>
            </div>
        @endif
    </div>
</x-ui-modal>
</div>
