<x-ui-modal size="md" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="relative">
                <span class="absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-60 animate-ping"></span>
                <span class="relative inline-flex w-5 h-5 rounded-full bg-red-600 items-center justify-center">
                    @svg('heroicon-s-bell-alert', 'w-3 h-3 text-white')
                </span>
            </div>
            <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Algedonic-Signal</h2>
            <span class="ml-auto text-xs text-[var(--ui-muted)]">Direkt an die oberste Ebene</span>
        </div>
    </x-slot>

    @if($sent)
        {{-- Bestaetigung: bleibt sichtbar bis Modal-Close --}}
        <div class="py-8 text-center space-y-3">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 text-green-600">
                @svg('heroicon-o-check', 'w-6 h-6')
            </div>
            <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Gesendet</h3>
            <p class="text-sm text-[var(--ui-muted)]">
                Das Signal ist auf dem Weg an die oberste Ebene.
            </p>
            @if($anonymous)
                <p class="text-xs text-[var(--ui-muted)] italic">
                    Anonym — deine Identität wurde nicht mitgesendet.
                </p>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-end">
                <button
                    type="button"
                    wire:click="close"
                    class="px-3 py-1.5 rounded-md text-sm font-medium text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition">
                    Schließen
                </button>
            </div>
        </x-slot>
    @else
        <div class="space-y-4" x-data="{ showSuggestions: false }">
            <p class="text-sm text-[var(--ui-muted)]">
                Der Algedonic-Kanal nach Stafford Beer: ein Signal, das alle Hierarchien überspringt und unmittelbar das normative Top-Level erreicht. Nutze diesen Knopf, wenn etwas <span class="font-medium text-[var(--ui-secondary)]">jetzt</span> Aufmerksamkeit von ganz oben braucht.
            </p>

            <div>
                <label for="algedonic-message" class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                    Was ist los?
                </label>
                <textarea
                    id="algedonic-message"
                    wire:model.live.debounce.300ms="message"
                    rows="4"
                    placeholder="Kurz und konkret. Was sieht oder spürt das System gerade nicht, was es sehen müsste?"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring focus:ring-red-500/30 text-sm"
                    autofocus
                ></textarea>
                @error('message')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="relative">
                <label for="algedonic-entity-search" class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                    Betroffene Entity (optional)
                </label>

                @if($entityId && $entityName)
                    <div class="flex items-center gap-2 px-3 py-2 rounded-md border border-[var(--ui-border)] bg-gray-50">
                        @svg('heroicon-o-building-office', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm flex-1">{{ $entityName }}</span>
                        <button type="button" wire:click="clearEntity" class="text-xs text-[var(--ui-muted)] hover:text-red-600">
                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                        </button>
                    </div>
                @else
                    <input
                        id="algedonic-entity-search"
                        type="text"
                        wire:model.live.debounce.250ms="entitySearch"
                        @focus="showSuggestions = true"
                        @click.away="showSuggestions = false"
                        placeholder="Name der Entity tippen — leer lassen, wenn die Perspektive als Ganzes betroffen ist"
                        autocomplete="off"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring focus:ring-red-500/30 text-sm"
                    >

                    @php($suggestions = $this->entitySuggestions)
                    @if(! empty($suggestions))
                        <div
                            x-show="showSuggestions"
                            x-transition.opacity
                            class="absolute z-30 mt-1 w-full max-h-64 overflow-y-auto rounded-md border border-[var(--ui-border)] bg-white shadow-lg">
                            @foreach($suggestions as $sug)
                                <button
                                    type="button"
                                    wire:click="selectEntity({{ $sug['id'] }}, @js($sug['name']))"
                                    @click="showSuggestions = false"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-red-50 hover:text-red-700 transition flex items-center gap-2">
                                    @svg('heroicon-o-building-office', 'w-4 h-4 text-[var(--ui-muted)]')
                                    <span>{{ $sug['name'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(strlen(trim($entitySearch)) >= 2)
                        <div x-show="showSuggestions" class="absolute z-30 mt-1 w-full rounded-md border border-[var(--ui-border)] bg-white shadow-lg px-3 py-2 text-xs text-[var(--ui-muted)]">
                            Keine Treffer.
                        </div>
                    @endif
                @endif
            </div>

            <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer select-none">
                <input type="checkbox" wire:model.live="anonymous" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                <span class="inline-flex items-center gap-1">
                    @svg('heroicon-o-eye-slash', 'w-4 h-4 text-[var(--ui-muted)]')
                    Anonym senden
                </span>
                <span class="text-xs text-[var(--ui-muted)]">— ohne deine Identität</span>
            </label>
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-end gap-2">
                <button
                    type="button"
                    wire:click="close"
                    class="px-3 py-1.5 rounded-md text-sm font-medium text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition">
                    Abbrechen
                </button>
                <button
                    type="button"
                    wire:click="send"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 transition">
                    @svg('heroicon-o-bell-alert', 'w-4 h-4')
                    Algedonic senden
                </button>
            </div>
        </x-slot>
    @endif
</x-ui-modal>
