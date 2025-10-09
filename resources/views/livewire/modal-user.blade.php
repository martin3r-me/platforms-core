<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Benutzer-Einstellungen</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">ACCOUNT</span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- User Profile --}}
        <div class="flex items-center gap-4 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
            <div class="w-12 h-12 rounded-full bg-[var(--ui-primary)] text-[var(--ui-on-primary)] flex items-center justify-center">
                <span class="font-semibold text-lg">{{ strtoupper(mb_substr((auth()->user()->fullname ?? auth()->user()->name ?? 'U'), 0, 2)) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-[var(--ui-secondary)] truncate">{{ auth()->user()->fullname ?? auth()->user()->name }}</div>
                <div class="text-sm text-[var(--ui-muted)] truncate">{{ auth()->user()->email }}</div>
            </div>
        </div>

        {{-- User Settings --}}
        <div>
            <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Konto-Einstellungen</h3>
            <div class="space-y-4">
                <x-ui-input-text
                    name="user.fullname"
                    label="Vollständiger Name"
                    wire:model.live.debounce.500ms="user.fullname"
                    placeholder="Vollständiger Name"
                    :errorKey="'user.fullname'"
                />
                <x-ui-input-text
                    name="user.email"
                    label="E-Mail-Adresse"
                    wire:model.live.debounce.500ms="user.email"
                    placeholder="E-Mail-Adresse"
                    :errorKey="'user.email'"
                />
            </div>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-[var(--ui-border)]/60">
            <div class="flex items-center justify-between">
                <div class="text-sm text-[var(--ui-muted)]">Angemeldet als {{ auth()->user()->email }}</div>
                <div class="flex gap-2">
                    <x-ui-button variant="secondary-outline" wire:click="save">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-check', 'w-4 h-4')
                            Speichern
                        </div>
                    </x-ui-button>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                        <x-ui-button variant="danger" type="submit">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-arrow-right-start-on-rectangle', 'w-4 h-4')
                                Logout
                            </div>
                        </x-ui-button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>