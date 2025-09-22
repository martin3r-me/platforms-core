<x-ui-modal persistent="true" wire:model="modalShow">
    <x-slot name="header">
        Benutzerkonto
    </x-slot>

    {{-- Slot-Inhalt = Body --}}
    <div class="space-y-6">
        {{-- Account Kurzinfo --}}
        <div class="d-flex items-center gap-3 p-3 bg-muted-5 rounded">
            <div class="w-10 h-10 rounded-full bg-primary text-on-primary d-flex items-center justify-center">
                <span class="font-semibold">
                    {{ strtoupper(mb_substr((auth()->user()->fullname ?? auth()->user()->name ?? 'U'), 0, 2)) }}
                </span>
            </div>
            <div class="min-w-0">
                <div class="font-medium text-secondary truncate">{{ auth()->user()->fullname ?? auth()->user()->name }}</div>
                <div class="text-sm text-gray-600 truncate">{{ auth()->user()->email }}</div>
            </div>
        </div>

        {{-- Schnelle Aktionen --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <x-ui-button 
                variant="secondary-outline"
                @click="$dispatch('open-modal-team')"
            >
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-users', 'w-5 h-5')
                    Team verwalten
                </div>
            </x-ui-button>

            <x-ui-button 
                variant="secondary-outline"
                @click="$dispatch('open-modal-pricing')"
            >
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-banknotes', 'w-5 h-5')
                    Abrechnung & Kosten
                </div>
            </x-ui-button>

            <x-ui-button 
                variant="secondary-outline"
                :href="route('profile.show')"
                wire:navigate
            >
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-user', 'w-5 h-5')
                    Profil & Sicherheit
                </div>
            </x-ui-button>

            <x-ui-button 
                variant="secondary-outline"
                @click="$dispatch('open-modal-modules')"
            >
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-squares-2x2', 'w-5 h-5')
                    Module wechseln
                </div>
            </x-ui-button>
        </div>
    </div>

    <x-slot name="footer">
        <div class="d-flex items-center justify-between w-full">
            <div class="text-xs text-gray-500">
                Angemeldet als {{ auth()->user()->email }}
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
                @csrf
                <x-ui-button variant="danger" type="submit">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-right-start-on-rectangle', 'w-5 h-5')
                        Logout
                    </div>
                </x-ui-button>
            </form>
        </div>
    </x-slot>
</x-ui-modal>