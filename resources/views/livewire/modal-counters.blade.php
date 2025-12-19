<div x-data="{ tab: 'count' }" x-init="
    window.addEventListener('open-modal-counters', () => { tab = 'count'; });
">
<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Counter</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">TEAM</span>
            </div>
            <div class="text-right">
                <div class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">
                    {{ $baseTeam?->name ?? 'Kein Team' }}
                </div>
            </div>
        </div>

        <div class="flex gap-1 mt-4 border-b border-gray-200">
            <button type="button"
                    class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'count', 'text-gray-500 hover:text-gray-700' : tab !== 'count' }"
                    @click="tab = 'count'">
                Zählen
            </button>
            @if($canManage)
                <button type="button"
                        class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors"
                        :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'settings', 'text-gray-500 hover:text-gray-700' : tab !== 'settings' }"
                        @click="tab = 'settings'">
                    Einstellungen
                </button>
            @endif
        </div>
    </x-slot>

    {{-- Count Tab --}}
    <div class="mt-6" x-show="tab === 'count'" x-cloak>
        @if(empty($counters))
            <div class="text-sm text-[var(--ui-muted)] p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">
                Noch keine Counter definiert.
                @if($canManage)
                    <div class="mt-2">Lege im Tab „Einstellungen“ den ersten Counter an.</div>
                @endif
            </div>
        @else
            <div class="space-y-3">
                @foreach($counters as $counter)
                    @php
                        $id = (int) ($counter['id'] ?? 0);
                        $isActive = (bool) ($counter['is_active'] ?? true);
                        $today = (int) ($countsToday[$id] ?? 0);
                        $all = (int) ($countsAllTime[$id] ?? 0);
                    @endphp
                    <div class="flex items-center justify-between p-4 rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <div class="font-semibold text-[var(--ui-secondary)] truncate">
                                    {{ $counter['label'] ?? 'Counter' }}
                                </div>
                                @if(!$isActive)
                                    <x-ui-badge variant="secondary" size="sm">inaktiv</x-ui-badge>
                                @endif
                            </div>
                            @if(!empty($counter['description']))
                                <div class="text-sm text-[var(--ui-muted)] mt-1">
                                    {{ $counter['description'] }}
                                </div>
                            @endif
                            <div class="flex gap-3 mt-2 text-xs text-[var(--ui-muted)]">
                                <span>Heute: <span class="font-semibold text-[var(--ui-secondary)]">{{ $today }}</span></span>
                                <span>Gesamt: <span class="font-semibold text-[var(--ui-secondary)]">{{ $all }}</span></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <x-ui-button
                                variant="primary"
                                size="sm"
                                wire:click="increment({{ $id }})"
                                @disabled(!$isActive)
                            >
                                +1
                            </x-ui-button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Settings Tab --}}
    @if($canManage)
        <div class="mt-6" x-show="tab === 'settings'" x-cloak>
            <div class="space-y-8">
                <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Neuen Counter anlegen</h3>
                    <div class="space-y-4">
                        <x-ui-input-text
                            name="newLabel"
                            label="Titel"
                            wire:model.live="newLabel"
                            placeholder="z.B. Nein zu Anfrage gesagt"
                            :errorKey="'newLabel'"
                        />
                        <x-ui-input-text
                            name="newDescription"
                            label="Beschreibung (optional)"
                            wire:model.live="newDescription"
                            placeholder="Wofür zählt ihr das?"
                            :errorKey="'newDescription'"
                        />
                        <div>
                            <x-ui-button wire:click="createCounter" variant="primary">
                                Counter erstellen
                            </x-ui-button>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Counter verwalten</h3>

                    @if(empty($counters))
                        <div class="text-sm text-[var(--ui-muted)] p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">
                            Noch keine Counter.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($counters as $counter)
                                @php
                                    $id = (int) ($counter['id'] ?? 0);
                                    $isActive = (bool) ($counter['is_active'] ?? true);
                                @endphp
                                <div class="p-4 rounded-lg border border-[var(--ui-border)]/40 bg-white">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 mb-3">
                                                <div class="font-semibold text-[var(--ui-secondary)]">
                                                    #{{ $id }}
                                                </div>
                                                @if($isActive)
                                                    <x-ui-badge variant="success" size="sm">aktiv</x-ui-badge>
                                                @else
                                                    <x-ui-badge variant="secondary" size="sm">inaktiv</x-ui-badge>
                                                @endif
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <x-ui-input-text
                                                    name="editLabel.{{ $id }}"
                                                    label="Titel"
                                                    wire:model.live="editLabel.{{ $id }}"
                                                    :errorKey="'editLabel.'.$id"
                                                />
                                                <x-ui-input-text
                                                    name="editDescription.{{ $id }}"
                                                    label="Beschreibung"
                                                    wire:model.live="editDescription.{{ $id }}"
                                                    :errorKey="'editDescription.'.$id"
                                                />
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-2 shrink-0">
                                            <x-ui-button variant="secondary-outline" wire:click="saveCounter({{ $id }})">
                                                Speichern
                                            </x-ui-button>
                                            <x-ui-button variant="{{ $isActive ? 'danger' : 'success' }}" wire:click="toggleActive({{ $id }})">
                                                {{ $isActive ? 'Deaktivieren' : 'Aktivieren' }}
                                            </x-ui-button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>


