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
                wire:click="setTab('tokens')"
                class="px-4 py-2 text-sm font-medium transition-colors border-b-2"
                :class="$wire.activeTab === 'tokens' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'"
            >
                API-Tokens
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

        {{-- Tab: API-Tokens --}}
        <div x-show="$wire.activeTab === 'tokens'" x-transition>
            <div class="space-y-6">
                {{-- Token erstellen --}}
                <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Neuen Token erstellen</h3>

                    @if($showNewToken && $newTokenCreated)
                        {{-- Neuer Token wurde erstellt - Anzeige --}}
                        <div class="space-y-4">
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center gap-2 text-green-700 mb-2">
                                    @svg('heroicon-o-check-circle', 'w-5 h-5')
                                    <span class="font-medium">Token erfolgreich erstellt!</span>
                                </div>
                                <p class="text-sm text-green-600 mb-3">
                                    Kopieren Sie den Token jetzt. Er wird nur einmal angezeigt!
                                </p>
                                <div class="relative">
                                    <textarea
                                        readonly
                                        rows="4"
                                        class="w-full p-3 pr-12 text-xs font-mono bg-white border border-green-300 rounded-lg resize-none"
                                    >{{ $newTokenCreated }}</textarea>
                                    <button
                                        type="button"
                                        x-data="{ copied: false }"
                                        @click="
                                            navigator.clipboard.writeText('{{ $newTokenCreated }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        "
                                        class="absolute top-2 right-2 p-2 text-green-600 hover:text-green-800 hover:bg-green-100 rounded transition-colors"
                                        title="In Zwischenablage kopieren"
                                    >
                                        <template x-if="!copied">
                                            @svg('heroicon-o-clipboard', 'w-5 h-5')
                                        </template>
                                        <template x-if="copied">
                                            @svg('heroicon-o-clipboard-document-check', 'w-5 h-5')
                                        </template>
                                    </button>
                                </div>
                            </div>
                            <x-ui-button variant="secondary-outline" wire:click="closeNewTokenDisplay">
                                Fertig
                            </x-ui-button>
                        </div>
                    @else
                        {{-- Formular zum Token erstellen --}}
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-ui-input-text
                                    name="newTokenName"
                                    label="Token-Name"
                                    wire:model="newTokenName"
                                    placeholder="z.B. Datawarehouse Token"
                                    :errorKey="'newTokenName'"
                                />
                                <div>
                                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1.5">Ablaufdatum</label>
                                    <select
                                        wire:model="newTokenExpiry"
                                        class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                                    >
                                        <option value="30_days">30 Tage</option>
                                        <option value="1_year">1 Jahr</option>
                                        <option value="never">Nie</option>
                                    </select>
                                </div>
                            </div>
                            <x-ui-button variant="primary" wire:click="createApiToken">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Token erstellen
                                </div>
                            </x-ui-button>
                        </div>
                    @endif
                </div>

                {{-- Token-Liste --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Aktive Tokens</h3>
                        <span class="px-3 py-1 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] rounded-full">
                            {{ $this->apiTokens->count() }} Tokens
                        </span>
                    </div>

                    @if($this->apiTokens->count() > 0)
                        <div class="space-y-2">
                            @foreach($this->apiTokens as $token)
                                <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-[var(--ui-secondary)]">{{ $token->name }}</div>
                                            <div class="mt-1 text-xs text-[var(--ui-muted)] space-y-0.5">
                                                <div>Erstellt: {{ $token->created_at?->format('d.m.Y H:i') ?? '-' }}</div>
                                                <div>
                                                    Läuft ab:
                                                    @if($token->expires_at)
                                                        <span class="{{ $token->expires_at->isPast() ? 'text-red-600' : ($token->expires_at->isBefore(now()->addDays(7)) ? 'text-amber-600' : '') }}">
                                                            {{ $token->expires_at->format('d.m.Y H:i') }}
                                                            @if($token->expires_at->isPast())
                                                                (abgelaufen)
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="text-green-600">Nie</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            wire:click="revokeApiToken('{{ $token->id }}')"
                                            wire:confirm="Token wirklich widerrufen? Dieser Vorgang kann nicht rückgängig gemacht werden."
                                            class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"
                                        >
                                            Widerrufen
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center text-[var(--ui-muted)] bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                            <div class="mb-2">@svg('heroicon-o-key', 'w-8 h-8 mx-auto opacity-50')</div>
                            <p>Noch keine API-Tokens erstellt.</p>
                        </div>
                    @endif
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
