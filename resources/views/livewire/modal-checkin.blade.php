<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Check-in</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">STATUS</span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Check-in Content --}}
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                @svg('heroicon-o-clock', 'w-8 h-8 text-[var(--ui-muted)]')
            </div>
            <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Check-in Modal</h3>
            <p class="text-[var(--ui-muted)]">Hier wird der Check-in Inhalt implementiert.</p>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" wire:click="$set('modalShow', false)">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    Schließen
                </div>
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
