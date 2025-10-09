<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-gray-900 m-0">Benutzer-Einstellungen</h2>
                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">Account</span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- User Profile --}}
        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <div class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center">
                <span class="font-semibold text-lg">{{ strtoupper(mb_substr((auth()->user()->fullname ?? auth()->user()->name ?? 'U'), 0, 2)) }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-gray-900 truncate">{{ auth()->user()->fullname ?? auth()->user()->name }}</div>
                <div class="text-sm text-gray-500 truncate">{{ auth()->user()->email }}</div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <x-ui-button variant="secondary-outline" @click="$dispatch('open-modal-team')" class="w-full">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-users', 'w-5 h-5')
                    Team verwalten
                </div>
            </x-ui-button>
            <x-ui-button variant="secondary-outline" @click="$dispatch('open-modal-modules', { tab: 'billing' })" class="w-full">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-banknotes', 'w-5 h-5')
                    Abrechnung & Kosten
                </div>
            </x-ui-button>
            <x-ui-button variant="secondary-outline" @click="$dispatch('open-modal-modules')" class="w-full">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-squares-2x2', 'w-5 h-5')
                    Module wechseln
                </div>
            </x-ui-button>
        </div>

        {{-- User Settings --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Konto-Einstellungen</h3>
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

        {{-- Logout --}}
        <div class="flex items-center justify-between w-full pt-4 border-t border-gray-200">
            <div class="text-xs text-gray-500">Angemeldet als {{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                <x-ui-button variant="danger" type="submit">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-arrow-right-start-on-rectangle', 'w-5 h-5')
                        Logout
                    </div>
                </x-ui-button>
            </form>
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