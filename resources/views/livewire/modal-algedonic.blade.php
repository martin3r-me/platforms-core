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

    <div class="space-y-4">
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

        <div>
            <label for="algedonic-entity" class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                Betroffene Entity (optional)
            </label>
            <input
                id="algedonic-entity"
                type="number"
                wire:model.live.debounce.300ms="entityId"
                placeholder="ID der Entity, falls eine konkrete betroffen ist"
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring focus:ring-red-500/30 text-sm"
            >
            <p class="mt-1 text-xs text-[var(--ui-muted)]">
                Leer lassen, wenn das Signal die aktive Perspektive als Ganzes betrifft.
            </p>
            @error('entityId')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex items-center justify-end gap-2">
            <button
                type="button"
                wire:click="$set('modalShow', false)"
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
</x-ui-modal>
