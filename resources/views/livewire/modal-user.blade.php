<x-nx-modal size="lg" height="fixed" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[color:var(--nx-text)] m-0">Benutzer-Einstellungen</h2>
                <span class="text-xs text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] px-2 py-1 rounded-full">ACCOUNT</span>
            </div>
        </div>
    </x-slot>

    {{-- Tabs --}}
    <x-nx-tabs>
        <x-nx-tab :active="$activeTab === 'profile'" wire:click="setTab('profile')">Profil</x-nx-tab>
        <x-nx-tab :active="$activeTab === 'tokens'" wire:click="setTab('tokens')">API-Tokens</x-nx-tab>
        <x-nx-tab :active="$activeTab === 'mcp'" wire:click="setTab('mcp')">MCP</x-nx-tab>
        <x-nx-tab :active="$activeTab === 'obsidian'" wire:click="setTab('obsidian')">Obsidian</x-nx-tab>
        <x-nx-tab :active="$activeTab === 'notifications'" wire:click="setTab('notifications')">Benachrichtigungen</x-nx-tab>
    </x-nx-tabs>

    <div class="space-y-6">
        {{-- Tab: Profil --}}
        <div x-show="$wire.activeTab === 'profile'" x-transition>
            {{-- User Profile --}}
            <div class="flex items-center gap-4 p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                <x-nx-avatar :name="auth()->user()->fullname ?? auth()->user()->name" :src="auth()->user()->avatar ?? null" size="lg" />
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-[color:var(--nx-text)] truncate">{{ auth()->user()->fullname ?? auth()->user()->name }}</div>
                    <div class="text-sm text-[color:var(--nx-muted)] truncate">{{ auth()->user()->email }}</div>
                </div>
            </div>

            {{-- User Settings --}}
            <div>
                <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Konto-Einstellungen</h3>
                <div class="space-y-4">
                    <x-nx-input-text
                        name="user.fullname"
                        label="Vollständiger Name"
                        wire:model.live.debounce.500ms="user.fullname"
                        placeholder="Vollständiger Name"
                        :errorKey="'user.fullname'"
                    />
                    <x-nx-input-text
                        name="user.email"
                        label="E-Mail-Adresse"
                        wire:model.live.debounce.500ms="user.email"
                        placeholder="E-Mail-Adresse"
                        :errorKey="'user.email'"
                    />
                </div>
            </div>

            {{-- Actions --}}
            <div class="pt-4 border-t border-[color:var(--nx-line)]">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-[color:var(--nx-muted)]">Angemeldet als {{ auth()->user()->email }}</div>
                    <div class="flex gap-2">
                        <x-nx-button variant="secondary" wire:click="save">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-check', 'w-4 h-4')
                                Speichern
                            </div>
                        </x-nx-button>
                        <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                            <x-nx-button variant="danger" type="submit">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-arrow-right-start-on-rectangle', 'w-4 h-4')
                                    Logout
                                </div>
                            </x-nx-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab: API-Tokens --}}
        <div x-show="$wire.activeTab === 'tokens'" x-transition>
            <div class="space-y-6">
                {{-- Token erstellen --}}
                <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Neuen Token erstellen</h3>

                    @if($showNewToken && $newTokenCreated)
                        {{-- Neuer Token wurde erstellt - Anzeige --}}
                        <div class="space-y-4">
                            <div class="p-4 bg-[rgba(47,158,68,0.10)] border border-[rgba(47,158,68,0.25)] rounded-lg">
                                <div class="flex items-center gap-2 text-[color:var(--nx-success)] mb-2">
                                    @svg('heroicon-o-check-circle', 'w-5 h-5')
                                    <span class="font-medium">Token erfolgreich erstellt!</span>
                                </div>
                                <p class="text-sm text-[color:var(--nx-success)] mb-3">
                                    Kopieren Sie den Token jetzt. Er wird nur einmal angezeigt!
                                </p>
                                <div class="relative">
                                    <textarea
                                        readonly
                                        rows="4"
                                        class="w-full p-3 pr-12 text-xs font-mono bg-[color:var(--nx-surface)] border border-[rgba(47,158,68,0.30)] rounded-lg resize-none"
                                    >{{ $newTokenCreated }}</textarea>
                                    <button
                                        type="button"
                                        x-data="{ copied: false }"
                                        @click="
                                            navigator.clipboard.writeText('{{ $newTokenCreated }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        "
                                        class="absolute top-2 right-2 p-2 text-[color:var(--nx-success)] hover:text-[color:var(--nx-success)] hover:bg-[rgba(47,158,68,0.16)] rounded transition-colors"
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
                            <x-nx-button variant="secondary" wire:click="closeNewTokenDisplay">
                                Fertig
                            </x-nx-button>
                        </div>
                    @else
                        {{-- Formular zum Token erstellen --}}
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-nx-input-text
                                    name="newTokenName"
                                    label="Token-Name"
                                    wire:model="newTokenName"
                                    placeholder="z.B. Datawarehouse Token"
                                    :errorKey="'newTokenName'"
                                />
                                <x-nx-input-select
                                    name="newTokenExpiry"
                                    label="Ablaufdatum"
                                    wire:model="newTokenExpiry"
                                    :options="[['value' => '30_days', 'label' => '30 Tage'], ['value' => '1_year', 'label' => '1 Jahr'], ['value' => 'never', 'label' => 'Nie']]"
                                />
                            </div>
                            <x-nx-button variant="primary" wire:click="createApiToken">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Token erstellen
                                </div>
                            </x-nx-button>
                        </div>
                    @endif
                </div>

                {{-- Token-Liste --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-[color:var(--nx-text)]">Aktive Tokens</h3>
                        <span class="px-3 py-1 text-xs font-medium bg-[color:var(--nx-bg)] text-[color:var(--nx-muted)] rounded-full">
                            {{ $this->apiTokens->count() }} Tokens
                        </span>
                    </div>

                    @if($this->apiTokens->count() > 0)
                        <div class="space-y-2">
                            @foreach($this->apiTokens as $token)
                                <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-[color:var(--nx-text)]">{{ $token->name }}</div>
                                            <div class="mt-1 text-xs text-[color:var(--nx-muted)] space-y-0.5">
                                                <div>Erstellt: {{ $token->created_at?->format('d.m.Y H:i') ?? '-' }}</div>
                                                <div>
                                                    Läuft ab:
                                                    @if($token->expires_at)
                                                        <span class="{{ $token->expires_at->isPast() ? 'text-[color:var(--nx-danger)]' : ($token->expires_at->isBefore(now()->addDays(7)) ? 'text-[color:var(--nx-warning)]' : '') }}">
                                                            {{ $token->expires_at->format('d.m.Y H:i') }}
                                                            @if($token->expires_at->isPast())
                                                                (abgelaufen)
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="text-[color:var(--nx-success)]">Nie</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            wire:click="revokeApiToken('{{ $token->id }}')"
                                            wire:confirm="Token wirklich widerrufen? Dieser Vorgang kann nicht rückgängig gemacht werden."
                                            class="px-3 py-1.5 text-xs font-medium text-[color:var(--nx-danger)] bg-[rgba(224,49,49,0.10)] rounded-lg hover:bg-[rgba(224,49,49,0.16)] transition-colors"
                                        >
                                            Widerrufen
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <div class="mb-2">@svg('heroicon-o-key', 'w-8 h-8 mx-auto opacity-50')</div>
                            <p>Noch keine API-Tokens erstellt.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tab: MCP Clients --}}
        <div x-show="$wire.activeTab === 'mcp'" x-transition>
            <div class="space-y-6">
                {{-- Info-Box --}}
                <div class="p-4 bg-[rgba(25,113,194,0.10)] border border-[rgba(25,113,194,0.25)] rounded-lg">
                    <div class="flex items-start gap-3">
                        @svg('heroicon-o-information-circle', 'w-5 h-5 text-[color:var(--nx-info)] flex-shrink-0 mt-0.5')
                        <div class="text-sm text-[color:var(--nx-info)]">
                            <p class="font-medium mb-1">MCP Clients für Claude Code, Cursor & Co.</p>
                            <p>Mit MCP Clients kannst du KI-Assistenten wie Claude Code oder Cursor mit diesem System verbinden. Der Client nutzt OAuth 2.0 zur sicheren Authentifizierung.</p>
                        </div>
                    </div>
                </div>

                {{-- Client erstellen --}}
                <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Neuen MCP Client erstellen</h3>

                    @if($showNewMcpClient && $newMcpClientCreated)
                        {{-- Neuer Client wurde erstellt - Anzeige --}}
                        <div class="space-y-4">
                            <div class="p-4 bg-[rgba(47,158,68,0.10)] border border-[rgba(47,158,68,0.25)] rounded-lg">
                                <div class="flex items-center gap-2 text-[color:var(--nx-success)] mb-2">
                                    @svg('heroicon-o-check-circle', 'w-5 h-5')
                                    <span class="font-medium">MCP Client erfolgreich erstellt!</span>
                                </div>
                                <p class="text-sm text-[color:var(--nx-success)] mb-4">
                                    @if($newMcpClientSecret)
                                        Kopiere die Daten jetzt. Das Secret wird nur einmal angezeigt!
                                    @else
                                        Kopiere die Client ID. Dieser ist ein Public Client (kein Secret).
                                    @endif
                                </p>

                                <div class="space-y-3">
                                    {{-- Client ID --}}
                                    <div>
                                        <label class="block text-xs font-medium text-[color:var(--nx-success)] mb-1">Client ID</label>
                                        <div class="relative">
                                            <input
                                                type="text"
                                                readonly
                                                value="{{ $newMcpClientCreated }}"
                                                class="w-full p-2 pr-10 text-sm font-mono bg-[color:var(--nx-surface)] border border-[rgba(47,158,68,0.30)] rounded-lg"
                                            />
                                            <button
                                                type="button"
                                                x-data="{ copied: false }"
                                                @click="
                                                    navigator.clipboard.writeText('{{ $newMcpClientCreated }}');
                                                    copied = true;
                                                    setTimeout(() => copied = false, 2000);
                                                "
                                                class="absolute top-1/2 -translate-y-1/2 right-2 p-1 text-[color:var(--nx-success)] hover:text-[color:var(--nx-success)] hover:bg-[rgba(47,158,68,0.16)] rounded transition-colors"
                                            >
                                                <template x-if="!copied">
                                                    @svg('heroicon-o-clipboard', 'w-4 h-4')
                                                </template>
                                                <template x-if="copied">
                                                    @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                                                </template>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Client Secret (nur bei confidential clients) --}}
                                    @if($newMcpClientSecret)
                                        <div>
                                            <label class="block text-xs font-medium text-[color:var(--nx-success)] mb-1">Client Secret</label>
                                            <div class="relative">
                                                <input
                                                    type="text"
                                                    readonly
                                                    value="{{ $newMcpClientSecret }}"
                                                    class="w-full p-2 pr-10 text-sm font-mono bg-[color:var(--nx-surface)] border border-[rgba(47,158,68,0.30)] rounded-lg"
                                                />
                                                <button
                                                    type="button"
                                                    x-data="{ copied: false }"
                                                    @click="
                                                        navigator.clipboard.writeText('{{ $newMcpClientSecret }}');
                                                        copied = true;
                                                        setTimeout(() => copied = false, 2000);
                                                    "
                                                    class="absolute top-1/2 -translate-y-1/2 right-2 p-1 text-[color:var(--nx-success)] hover:text-[color:var(--nx-success)] hover:bg-[rgba(47,158,68,0.16)] rounded transition-colors"
                                                >
                                                    <template x-if="!copied">
                                                        @svg('heroicon-o-clipboard', 'w-4 h-4')
                                                    </template>
                                                    <template x-if="copied">
                                                        @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                                                    </template>
                                                </button>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- MCP URL --}}
                                    <div>
                                        <label class="block text-xs font-medium text-[color:var(--nx-success)] mb-1">MCP Server URL</label>
                                        <div class="relative">
                                            <input
                                                type="text"
                                                readonly
                                                value="{{ config('app.url') }}/mcp/sse"
                                                class="w-full p-2 pr-10 text-sm font-mono bg-[color:var(--nx-surface)] border border-[rgba(47,158,68,0.30)] rounded-lg"
                                            />
                                            <button
                                                type="button"
                                                x-data="{ copied: false }"
                                                @click="
                                                    navigator.clipboard.writeText('{{ config('app.url') }}/mcp/sse');
                                                    copied = true;
                                                    setTimeout(() => copied = false, 2000);
                                                "
                                                class="absolute top-1/2 -translate-y-1/2 right-2 p-1 text-[color:var(--nx-success)] hover:text-[color:var(--nx-success)] hover:bg-[rgba(47,158,68,0.16)] rounded transition-colors"
                                            >
                                                <template x-if="!copied">
                                                    @svg('heroicon-o-clipboard', 'w-4 h-4')
                                                </template>
                                                <template x-if="copied">
                                                    @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                                                </template>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <x-nx-button variant="secondary" wire:click="closeNewMcpClientDisplay">
                                Fertig
                            </x-nx-button>
                        </div>
                    @else
                        {{-- Formular zum Client erstellen --}}
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-nx-input-text
                                    name="newMcpClientName"
                                    label="Client-Name"
                                    wire:model="newMcpClientName"
                                    placeholder="z.B. Claude Code"
                                    :errorKey="'newMcpClientName'"
                                />
                                <x-nx-input-text
                                    name="newMcpClientRedirect"
                                    label="Redirect URI"
                                    wire:model="newMcpClientRedirect"
                                    placeholder="http://127.0.0.1"
                                    :errorKey="'newMcpClientRedirect'"
                                />
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="newMcpClientPublic"
                                    wire:model="newMcpClientPublic"
                                    class="w-4 h-4 text-[color:var(--nx-accent)] border-[color:var(--nx-line-strong)] rounded focus:ring-[color:var(--nx-accent)]"
                                />
                                <label for="newMcpClientPublic" class="text-sm text-[color:var(--nx-text)]">
                                    Public Client (kein Secret, für PKCE)
                                </label>
                            </div>
                            <x-nx-button variant="primary" wire:click="createMcpClient">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Client erstellen
                                </div>
                            </x-nx-button>
                        </div>
                    @endif
                </div>

                {{-- MCP Bearer Token erstellen (für Open WebUI etc.) --}}
                <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-2">API Token für MCP</h3>
                    <p class="text-sm text-[color:var(--nx-muted)] mb-4">
                        Erstelle einen Bearer Token für Clients wie Open WebUI, die kein OAuth unterstützen.
                    </p>

                    @if($showNewMcpToken && $newMcpTokenCreated)
                        {{-- Token wurde erstellt - Anzeige --}}
                        <div class="space-y-4">
                            <div class="p-4 bg-[rgba(47,158,68,0.10)] border border-[rgba(47,158,68,0.25)] rounded-lg">
                                <div class="flex items-center gap-2 text-[color:var(--nx-success)] mb-2">
                                    @svg('heroicon-o-check-circle', 'w-5 h-5')
                                    <span class="font-medium">Token erfolgreich erstellt!</span>
                                </div>
                                <p class="text-sm text-[color:var(--nx-success)] mb-3">
                                    Kopiere den Token jetzt. Er wird nur einmal angezeigt!
                                </p>

                                {{-- Token --}}
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-[color:var(--nx-success)] mb-1">Bearer Token</label>
                                    <div class="relative">
                                        <textarea
                                            readonly
                                            rows="4"
                                            class="w-full p-3 pr-12 text-xs font-mono bg-[color:var(--nx-surface)] border border-[rgba(47,158,68,0.30)] rounded-lg resize-none"
                                        >{{ $newMcpTokenCreated }}</textarea>
                                        <button
                                            type="button"
                                            x-data="{ copied: false }"
                                            @click="
                                                navigator.clipboard.writeText($el.closest('.relative').querySelector('textarea').value);
                                                copied = true;
                                                setTimeout(() => copied = false, 2000);
                                            "
                                            class="absolute top-2 right-2 p-2 text-[color:var(--nx-success)] hover:text-[color:var(--nx-success)] hover:bg-[rgba(47,158,68,0.16)] rounded transition-colors"
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

                                {{-- MCP URL --}}
                                <div>
                                    <label class="block text-xs font-medium text-[color:var(--nx-success)] mb-1">MCP Server URL</label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            readonly
                                            value="{{ config('app.url') }}/mcp"
                                            class="w-full p-2 pr-10 text-sm font-mono bg-[color:var(--nx-surface)] border border-[rgba(47,158,68,0.30)] rounded-lg"
                                        />
                                        <button
                                            type="button"
                                            x-data="{ copied: false }"
                                            @click="
                                                navigator.clipboard.writeText('{{ config('app.url') }}/mcp');
                                                copied = true;
                                                setTimeout(() => copied = false, 2000);
                                            "
                                            class="absolute top-1/2 -translate-y-1/2 right-2 p-1 text-[color:var(--nx-success)] hover:text-[color:var(--nx-success)] hover:bg-[rgba(47,158,68,0.16)] rounded transition-colors"
                                        >
                                            <template x-if="!copied">
                                                @svg('heroicon-o-clipboard', 'w-4 h-4')
                                            </template>
                                            <template x-if="copied">
                                                @svg('heroicon-o-clipboard-document-check', 'w-4 h-4')
                                            </template>
                                        </button>
                                    </div>
                                </div>

                                <p class="mt-3 text-xs text-[color:var(--nx-success)]">
                                    Nutze diesen Token als Bearer Token in Open WebUI (Header: <code class="bg-[rgba(47,158,68,0.16)] px-1 rounded">Authorization: Bearer &lt;token&gt;</code>).
                                </p>
                            </div>
                            <x-nx-button variant="secondary" wire:click="closeMcpTokenDisplay">
                                Fertig
                            </x-nx-button>
                        </div>
                    @else
                        {{-- Formular --}}
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-nx-input-text
                                    name="newMcpTokenName"
                                    label="Token-Name"
                                    wire:model="newMcpTokenName"
                                    placeholder="z.B. Open WebUI"
                                    :errorKey="'newMcpTokenName'"
                                />
                                <x-nx-input-select
                                    name="newMcpTokenExpiry"
                                    label="Ablaufdatum"
                                    wire:model="newMcpTokenExpiry"
                                    :options="[['value' => '30_days', 'label' => '30 Tage'], ['value' => '1_year', 'label' => '1 Jahr'], ['value' => 'never', 'label' => 'Nie']]"
                                />
                            </div>
                            <x-nx-button variant="primary" wire:click="createMcpToken">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    Token erstellen
                                </div>
                            </x-nx-button>
                        </div>
                    @endif
                </div>

                {{-- Client-Liste --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-[color:var(--nx-text)]">Aktive MCP Clients</h3>
                        <span class="px-3 py-1 text-xs font-medium bg-[color:var(--nx-bg)] text-[color:var(--nx-muted)] rounded-full">
                            {{ $this->mcpClients->count() }} Clients
                        </span>
                    </div>

                    @if($this->mcpClients->count() > 0)
                        <div class="space-y-2">
                            @foreach($this->mcpClients as $client)
                                <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-[color:var(--nx-text)]">{{ $client->name }}</div>
                                            <div class="mt-1 text-xs text-[color:var(--nx-muted)] space-y-0.5">
                                                <div class="font-mono">ID: {{ $client->id }}</div>
                                                <div>Typ: {{ $client->confidential() ? 'Confidential' : 'Public (PKCE)' }}</div>
                                                <div>Erstellt: {{ $client->created_at?->format('d.m.Y H:i') ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <button
                                            wire:click="revokeMcpClient('{{ $client->id }}')"
                                            wire:confirm="MCP Client wirklich widerrufen? Alle verbundenen Sessions werden beendet."
                                            class="px-3 py-1.5 text-xs font-medium text-[color:var(--nx-danger)] bg-[rgba(224,49,49,0.10)] rounded-lg hover:bg-[rgba(224,49,49,0.16)] transition-colors"
                                        >
                                            Widerrufen
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <div class="mb-2">@svg('heroicon-o-link', 'w-8 h-8 mx-auto opacity-50')</div>
                            <p>Noch keine MCP Clients erstellt.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tab: Obsidian Vaults --}}
        <div x-show="$wire.activeTab === 'obsidian'" x-transition>
            <div class="space-y-6">
                {{-- Info-Box --}}
                <div class="p-4 bg-[rgba(25,113,194,0.10)] border border-[rgba(25,113,194,0.25)] rounded-lg">
                    <div class="flex items-start gap-3">
                        @svg('heroicon-o-cloud', 'w-5 h-5 text-[color:var(--nx-info)] flex-shrink-0 mt-0.5')
                        <div class="text-sm text-[color:var(--nx-info)]">
                            <p class="font-medium mb-1">Obsidian Vault Verbindungen</p>
                            <p>Verbinde deine Obsidian Vaults via S3-kompatiblem Storage (AWS S3, Cloudflare R2, MinIO, Wasabi). Nutze das Plugin "Remotely Save" in Obsidian, um deinen Vault mit dem Bucket zu synchronisieren.</p>
                        </div>
                    </div>
                </div>

                {{-- Vault erstellen/bearbeiten --}}
                @if($showVaultForm)
                    <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                        <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">
                            {{ $editingVaultId ? 'Vault bearbeiten' : 'Neuen Vault anlegen' }}
                        </h3>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-nx-input-text
                                    name="vaultForm.name"
                                    label="Name"
                                    wire:model="vaultForm.name"
                                    placeholder="z.B. Work, Personal"
                                    :errorKey="'vaultForm.name'"
                                />
                                <x-nx-input-select
                                    name="vaultForm.driver"
                                    label="Driver"
                                    wire:model="vaultForm.driver"
                                    :options="[['value' => 's3', 'label' => 'AWS S3'], ['value' => 'r2', 'label' => 'Cloudflare R2'], ['value' => 'minio', 'label' => 'MinIO'], ['value' => 'wasabi', 'label' => 'Wasabi']]"
                                />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-nx-input-text
                                    name="vaultForm.bucket"
                                    label="Bucket"
                                    wire:model="vaultForm.bucket"
                                    placeholder="my-obsidian-bucket"
                                    :errorKey="'vaultForm.bucket'"
                                />
                                <x-nx-input-text
                                    name="vaultForm.region"
                                    label="Region"
                                    wire:model="vaultForm.region"
                                    placeholder="eu-central-1 (optional)"
                                />
                            </div>
                            <x-nx-input-text
                                name="vaultForm.endpoint"
                                label="Custom Endpoint"
                                wire:model="vaultForm.endpoint"
                                placeholder="https://... (optional, für R2/MinIO/Wasabi)"
                            />
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-nx-input-text
                                    name="vaultForm.access_key"
                                    label="Access Key"
                                    wire:model="vaultForm.access_key"
                                    placeholder="AKIA..."
                                    :errorKey="'vaultForm.access_key'"
                                />
                                <div>
                                    <label class="block text-sm font-medium text-[color:var(--nx-text)] mb-1.5">Secret Key</label>
                                    <input
                                        type="password"
                                        wire:model="vaultForm.secret_key"
                                        placeholder="••••••••"
                                        class="w-full px-3 py-2 border border-[color:var(--nx-line-strong)] rounded-lg bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--nx-accent)] focus:border-transparent"
                                    />
                                    @error('vaultForm.secret_key')
                                        <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <x-nx-input-text
                                name="vaultForm.prefix"
                                label="Prefix / Subfolder"
                                wire:model="vaultForm.prefix"
                                placeholder="obsidian/ (optional)"
                            />

                            {{-- Skills & Team-Zuordnung --}}
                            <div class="p-4 bg-[rgba(25,113,194,0.08)] border border-[rgba(25,113,194,0.2)] rounded-lg space-y-4">
                                <div class="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        wire:model="vaultForm.skills_enabled"
                                        id="vaultSkillsEnabled"
                                        class="w-4 h-4 text-[color:var(--nx-info)] border-[color:var(--nx-line-strong)] rounded focus:ring-[color:var(--nx-info)]"
                                    />
                                    <label for="vaultSkillsEnabled" class="text-sm font-medium text-[color:var(--nx-text)]">
                                        Als Skill-Vault verwenden
                                    </label>
                                </div>
                                <p class="text-xs text-[color:var(--nx-muted)] -mt-2 ml-7">Skill-Vaults werden nach Markdown-Anleitungen im <code class="px-1 py-0.5 bg-[rgba(25,113,194,0.16)] rounded text-[color:var(--nx-info)]">skills/</code>-Ordner durchsucht.</p>

                                @php
                                    $ownedTeams = auth()->user()->teams->filter(fn($t) => $t->user_id === auth()->id());
                                @endphp
                                @if($ownedTeams->isNotEmpty())
                                    <div>
                                        <x-nx-input-select
                                            name="vaultForm.team_id"
                                            label="Team-Zuordnung"
                                            wire:model="vaultForm.team_id"
                                            :options="$ownedTeams"
                                            optionValue="id"
                                            optionLabel="name"
                                            nullable
                                            nullLabel="Persönlicher Vault"
                                        />
                                        <p class="mt-1 text-xs text-[color:var(--nx-muted)]">Team-Vaults stellen Skills & Inhalte für alle Team-Mitglieder bereit.</p>
                                    </div>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <x-nx-button variant="primary" wire:click="saveVault">
                                    <div class="flex items-center gap-2">
                                        @svg('heroicon-o-check', 'w-4 h-4')
                                        {{ $editingVaultId ? 'Speichern' : 'Vault anlegen' }}
                                    </div>
                                </x-nx-button>
                                <x-nx-button variant="secondary" wire:click="cancelVaultForm">
                                    Abbrechen
                                </x-nx-button>
                            </div>
                        </div>
                    </div>
                @else
                    <div>
                        <x-nx-button variant="primary" wire:click="showCreateVault">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                Vault anlegen
                            </div>
                        </x-nx-button>
                    </div>
                @endif

                {{-- Vault-Liste --}}
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-[color:var(--nx-text)]">Deine Vaults</h3>
                        <span class="px-3 py-1 text-xs font-medium bg-[color:var(--nx-bg)] text-[color:var(--nx-muted)] rounded-full">
                            {{ $this->obsidianVaults->count() }} {{ $this->obsidianVaults->count() === 1 ? 'Vault' : 'Vaults' }}
                        </span>
                    </div>

                    @if($this->obsidianVaults->count() > 0)
                        <div class="space-y-2">
                            @foreach($this->obsidianVaults as $vault)
                                <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium text-[color:var(--nx-text)]">{{ $vault->name }}</span>
                                                @if($vault->team_id)
                                                    <span class="px-2 py-0.5 text-xs font-medium text-[color:var(--nx-info)] bg-[rgba(25,113,194,0.16)] rounded-full">{{ $vault->team?->name ?? 'Team' }}</span>
                                                @endif
                                                @if($vault->settings['skills_enabled'] ?? false)
                                                    <span class="px-2 py-0.5 text-xs font-medium text-[color:var(--nx-text)] bg-[color:var(--nx-accent-soft)] rounded-full">Skills</span>
                                                @endif
                                            </div>
                                            <div class="mt-1 text-xs text-[color:var(--nx-muted)] space-y-0.5">
                                                <div>Driver: {{ strtoupper($vault->driver) }} &middot; Bucket: {{ $vault->bucket }}</div>
                                                @if($vault->region)
                                                    <div>Region: {{ $vault->region }}</div>
                                                @endif
                                                @if($vault->prefix)
                                                    <div>Prefix: {{ $vault->prefix }}</div>
                                                @endif
                                                @if(isset($vaultTestResults[$vault->id]))
                                                    <div class="{{ $vaultTestResults[$vault->id] ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">
                                                        {{ $vaultTestResults[$vault->id] ? 'Verbindung OK' : 'Verbindung fehlgeschlagen' }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                wire:click="testVaultConnection({{ $vault->id }})"
                                                class="px-3 py-1.5 text-xs font-medium text-[color:var(--nx-info)] bg-[rgba(25,113,194,0.10)] rounded-lg hover:bg-[rgba(25,113,194,0.16)] transition-colors"
                                                title="Verbindung testen"
                                            >
                                                Test
                                            </button>
                                            <button
                                                wire:click="editVault({{ $vault->id }})"
                                                class="px-3 py-1.5 text-xs font-medium text-[color:var(--nx-text)] bg-[color:var(--nx-bg)] rounded-lg hover:bg-[color:var(--nx-line)] transition-colors"
                                            >
                                                Bearbeiten
                                            </button>
                                            <button
                                                wire:click="deleteVault({{ $vault->id }})"
                                                wire:confirm="Vault wirklich löschen? Die S3-Inhalte bleiben erhalten."
                                                class="px-3 py-1.5 text-xs font-medium text-[color:var(--nx-danger)] bg-[rgba(224,49,49,0.10)] rounded-lg hover:bg-[rgba(224,49,49,0.16)] transition-colors"
                                            >
                                                Löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <div class="mb-2">@svg('heroicon-o-cloud', 'w-8 h-8 mx-auto opacity-50')</div>
                            <p>Noch keine Obsidian Vaults konfiguriert.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tab: Benachrichtigungen --}}
        <div x-show="$wire.activeTab === 'notifications'" x-transition>
            <div class="space-y-6">

                {{-- Abschnitt A: Kanäle konfigurieren --}}
                <div>
                    <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Benachrichtigungs-Kanäle</h3>
                    <div class="space-y-4">

                        {{-- Pushover --}}
                        <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-[color:var(--nx-text)]">Pushover</span>
                                    @if($this->pushoverChannel?->is_active)
                                        <span class="px-2 py-0.5 text-xs font-medium text-[color:var(--nx-success)] bg-[rgba(47,158,68,0.16)] rounded-full">Verbunden</span>
                                    @endif
                                </div>
                                @if($this->pushoverChannel)
                                    <button
                                        wire:click="removePushover"
                                        wire:confirm="Pushover-Verbindung wirklich entfernen?"
                                        class="text-xs text-[color:var(--nx-danger)] hover:text-[color:var(--nx-danger)]"
                                    >
                                        Entfernen
                                    </button>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <input
                                        type="text"
                                        wire:model="pushoverUserKey"
                                        placeholder="Pushover User Key"
                                        class="w-full px-3 py-2 text-sm border border-[color:var(--nx-line-strong)] rounded-lg bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--nx-accent)] focus:border-transparent"
                                    />
                                    @error('pushoverUserKey')
                                        <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                                    @enderror
                                </div>
                                <x-nx-button variant="secondary" wire:click="savePushoverKey">
                                    Speichern
                                </x-nx-button>
                                @if($this->pushoverChannel)
                                    <x-nx-button variant="secondary" wire:click="testPushover">
                                        Test
                                    </x-nx-button>
                                @endif
                            </div>
                            @if($this->pushoverChannel?->last_tested_at)
                                <div class="mt-2 text-xs text-[color:var(--nx-muted)]">
                                    Letzter Test: {{ $this->pushoverChannel->last_tested_at->format('d.m.Y H:i') }}
                                    @if($this->pushoverChannel->last_error)
                                        <span class="text-[color:var(--nx-danger)]">- {{ $this->pushoverChannel->last_error }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- MS Teams Webhook --}}
                        <div class="p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-[color:var(--nx-text)]">MS Teams Webhook</span>
                                    @if($this->teamsChannel?->is_active)
                                        <span class="px-2 py-0.5 text-xs font-medium text-[color:var(--nx-success)] bg-[rgba(47,158,68,0.16)] rounded-full">Verbunden</span>
                                    @endif
                                </div>
                                @if($this->teamsChannel)
                                    <button
                                        wire:click="removeTeamsWebhook"
                                        wire:confirm="Teams Webhook wirklich entfernen?"
                                        class="text-xs text-[color:var(--nx-danger)] hover:text-[color:var(--nx-danger)]"
                                    >
                                        Entfernen
                                    </button>
                                @endif
                            </div>
                            <div class="flex gap-2">
                                <div class="flex-1">
                                    <input
                                        type="url"
                                        wire:model="teamsWebhookUrl"
                                        placeholder="https://outlook.office.com/webhook/..."
                                        class="w-full px-3 py-2 text-sm border border-[color:var(--nx-line-strong)] rounded-lg bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] focus:outline-none focus:ring-2 focus:ring-[color:var(--nx-accent)] focus:border-transparent"
                                    />
                                    @error('teamsWebhookUrl')
                                        <p class="mt-1 text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                                    @enderror
                                </div>
                                <x-nx-button variant="secondary" wire:click="saveTeamsWebhook">
                                    Speichern
                                </x-nx-button>
                                @if($this->teamsChannel)
                                    <x-nx-button variant="secondary" wire:click="testTeamsWebhook">
                                        Test
                                    </x-nx-button>
                                @endif
                            </div>
                            @if($this->teamsChannel?->last_tested_at)
                                <div class="mt-2 text-xs text-[color:var(--nx-muted)]">
                                    Letzter Test: {{ $this->teamsChannel->last_tested_at->format('d.m.Y H:i') }}
                                    @if($this->teamsChannel->last_error)
                                        <span class="text-[color:var(--nx-danger)]">- {{ $this->teamsChannel->last_error }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                    </div>
                </div>

                {{-- Abschnitt B: Preferences pro Notification-Typ --}}
                @if(count($this->notificationTypes) > 0)
                    <div>
                        <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Benachrichtigungs-Einstellungen</h3>
                        <p class="text-sm text-[color:var(--nx-muted)] mb-4">Wähle pro Benachrichtigungstyp, über welche Kanäle du informiert werden möchtest.</p>

                        <div class="space-y-6">
                            @foreach($this->notificationTypes as $group => $types)
                                <div>
                                    <h4 class="text-sm font-semibold text-[color:var(--nx-text)] uppercase tracking-wider mb-3">{{ ucfirst($group) }}</h4>
                                    <div class="space-y-2">
                                        @foreach($types as $typeKey => $typeConfig)
                                            <div class="p-3 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                                <div class="flex items-center justify-between">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="text-sm font-medium text-[color:var(--nx-text)]">{{ $typeConfig['label'] }}</div>
                                                        @if($typeConfig['description'])
                                                            <div class="text-xs text-[color:var(--nx-muted)]">{{ $typeConfig['description'] }}</div>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-3 ml-4">
                                                        @foreach($this->notificationChannels as $channelKey => $channel)
                                                            <label class="flex items-center gap-1.5 cursor-pointer" title="{{ $channel->label() }}">
                                                                <input
                                                                    type="checkbox"
                                                                    wire:click="toggleNotificationPreference('{{ $typeKey }}', '{{ $channelKey }}')"
                                                                    @checked($notificationPreferences[$typeKey][$channelKey] ?? false)
                                                                    class="w-4 h-4 text-[color:var(--nx-accent)] border-[color:var(--nx-line-strong)] rounded focus:ring-[color:var(--nx-accent)]"
                                                                />
                                                                <span class="text-xs text-[color:var(--nx-muted)]">{{ $channel->label() }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                        <div class="mb-2">@svg('heroicon-o-bell', 'w-8 h-8 mx-auto opacity-50')</div>
                        <p>Noch keine Benachrichtigungstypen registriert.</p>
                    </div>
                @endif

            </div>
        </div>

    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-nx-button variant="secondary" @click="modalShow = false">
                Schließen
            </x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>
