<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Benutzer-Einstellungen</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">ACCOUNT</span>
            </div>
        </div>
    </x-slot>

    {{-- Tabs --}}
    <div class="border-b border-[var(--ui-border)]/60 mb-6">
        <nav class="flex gap-1">
            <button
                type="button"
                wire:click="setTab('profile')"
                class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                :class="$wire.activeTab === 'profile' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
            >
                Profil
            </button>
            <button
                type="button"
                wire:click="setTab('meta')"
                class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                :class="$wire.activeTab === 'meta' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
            >
                Meta-Verbindungen
            </button>
        </nav>
    </div>

    <div class="space-y-6">
        {{-- Tab: Profil --}}
        <div x-show="$wire.activeTab === 'profile'" x-transition>
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

        {{-- Tab: Meta-Verbindungen --}}
        <div x-show="$wire.activeTab === 'meta'" x-transition>
            <div class="space-y-6">
                {{-- Meta Token Status --}}
                <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Meta-Verbindung</h3>
                        @if($this->metaToken)
                            <span class="px-3 py-1 text-xs font-medium bg-green-100 text-green-700 rounded-full">
                                Verbunden
                            </span>
                        @else
                            <span class="px-3 py-1 text-xs font-medium bg-red-100 text-red-700 rounded-full">
                                Nicht verbunden
                            </span>
                        @endif
                    </div>

                    @if($this->metaToken)
                        <div class="space-y-3">
                            <div class="text-sm text-[var(--ui-muted)]">
                                <div class="flex items-center justify-between mb-2">
                                    <span>Token abgelaufen:</span>
                                    <span class="font-medium">
                                        @if($this->metaToken->isExpired)
                                            <span class="text-red-600">Abgelaufen</span>
                                        @elseif($this->metaToken->isExpiringSoon)
                                            <span class="text-amber-600">Läuft bald ab</span>
                                        @else
                                            <span class="text-green-600">Aktiv</span>
                                        @endif
                                    </span>
                                </div>
                                @if($this->metaToken->expires_at)
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $this->metaToken->expires_at->format('d.m.Y H:i') }} Uhr
                                    </div>
                                @endif
                            </div>

                            <div class="flex gap-2">
                                <a
                                    href="{{ route('meta.oauth.redirect') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                    Erneut verbinden
                                </a>
                                <button
                                    wire:click="deleteMetaToken"
                                    wire:confirm="Meta-Verbindung wirklich löschen? Alle verknüpften Facebook Pages und Instagram Accounts werden entfernt."
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                    Verbindung löschen
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="space-y-3">
                            <p class="text-sm text-[var(--ui-muted)]">
                                Verbinde dein Meta-Konto, um Facebook Pages und Instagram Accounts zu verwalten.
                            </p>
                            <a
                                href="{{ route('meta.oauth.redirect') }}"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                @svg('heroicon-o-link', 'w-4 h-4')
                                Mit Meta verbinden
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Facebook Pages --}}
                @if($this->metaToken)
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Facebook Pages</h3>
                            <span class="px-3 py-1 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] rounded-full">
                                {{ $this->facebookPages->count() }} Pages
                            </span>
                        </div>
                        @if($this->facebookPages->count() > 0)
                            <div class="space-y-2">
                                @foreach($this->facebookPages as $page)
                                    <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $page->name }}</div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-1">{{ $page->external_id }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-[var(--ui-muted)]">Noch keine Facebook Pages synchronisiert.</p>
                        @endif
                    </div>

                    {{-- Instagram Accounts --}}
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Instagram Accounts</h3>
                            <span class="px-3 py-1 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] rounded-full">
                                {{ $this->instagramAccounts->count() }} Accounts
                            </span>
                        </div>
                        @if($this->instagramAccounts->count() > 0)
                            <div class="space-y-2">
                                @foreach($this->instagramAccounts as $account)
                                    <div class="p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                                        <div class="font-medium text-[var(--ui-secondary)]">@{{ $account->username }}</div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-1">{{ $account->external_id }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-[var(--ui-muted)]">Noch keine Instagram Accounts synchronisiert.</p>
                        @endif
                    </div>
                @endif
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