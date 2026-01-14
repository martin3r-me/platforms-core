<div>
    {{-- Use a dedicated in-between size: big enough to work, but not full-screen. --}}
    <x-ui-modal size="wide" hideFooter="1" wire:model="open" :closeButton="true">
        <x-slot name="header">
            {{-- Match Playground header layout 1:1 --}}
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                        @svg('heroicon-o-paper-airplane', 'w-6 h-6 text-[var(--ui-primary)]')
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Kommunikation</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Chat, Threads, Kontext (Demo) – im Modal.</p>
                </div>
                {{-- Tabs in the modal header (requested) --}}
                <div class="flex items-center gap-2">
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'chat' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Chat
                    </button>
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'threads' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Threads
                    </button>
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'settings' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Settings
                    </button>
                </div>
            </div>
        </x-slot>

        {{-- Match Playground body wrapper 1:1 (cancel modal padding) --}}
        <div class="-m-6 w-full h-full min-h-0 min-w-0 overflow-hidden" style="width:100%;">
            <div
                x-data="{ tab: 'chat' }"
                @comms:set-tab.window="tab = ($event.detail && $event.detail.tab) ? $event.detail.tab : tab"
                class="w-full h-full min-h-0 overflow-hidden flex flex-col"
                style="width:100%;"
            >
                <div class="w-full flex-1 min-h-0 overflow-hidden p-4 bg-[var(--ui-bg)]" style="width:100%;">
                    {{-- Chat Tab --}}
                    <div x-show="tab==='chat'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] flex-shrink-0">Kanäle</div>
                                    {{-- Kanäle (hart codiert, nur UI) --}}
                                    <div class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto">
                                        <div class="relative flex-shrink-0 flex items-center">
                                            <button
                                                type="button"
                                                class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1 bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]"
                                                title="Kanal: E-Mail"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-envelope', 'w-4 h-4 text-white/90')
                                                        <span class="font-semibold">E-Mail</span>
                                                        <span class="text-white/70 hidden sm:inline">· m.erren@bhgdigital.de</span>
                                                    </span>
                                                </span>
                                                <span class="hidden ml-1 w-2 h-2 rounded-full bg-white/90 animate-pulse"></span>
                                            </button>
                                        </div>
                                        <div class="relative flex-shrink-0 flex items-center">
                                            <button
                                                type="button"
                                                class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1 bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                                                title="Kanal: Telefon"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-phone', 'w-4 h-4 text-[var(--ui-muted)]')
                                                        <span class="font-semibold text-[var(--ui-secondary)]">Telefon</span>
                                                        <span class="text-[var(--ui-muted)] hidden sm:inline">· +49 172 123 12 14</span>
                                                    </span>
                                                </span>
                                                <span class="hidden ml-1 w-2 h-2 rounded-full bg-[var(--ui-primary)] animate-pulse"></span>
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            class="px-2 py-1 rounded text-[11px] border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] flex-shrink-0"
                                            title="Neuen Kanal anlegen"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                {{-- Keep header clean (like Playground) --}}
                                <div class="flex items-center gap-2 flex-shrink-0"></div>
                            </div>

                            <div class="flex-1 min-h-0 overflow-hidden w-full">
                                {{-- Same inner layout as Playground, but mirrored: left sidebar + right chat --}}
                                <div class="w-full h-full min-h-0 grid grid-cols-4 gap-5 px-4 py-4 overflow-hidden min-w-0" style="width:100%; max-width:100%;">
                                    {{-- Left: "Debug" box mirrored to left, renamed to Threads --}}
                                    <div class="col-span-1 min-h-0 min-w-0 bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm overflow-x-hidden">
                                        <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                                            <div class="text-xs font-semibold text-[var(--ui-secondary)]">Threads</div>
                                            <div class="flex items-center gap-3">
                                                <button type="button" class="text-xs text-[var(--ui-muted)] hover:underline" title="(UI) Neu">Neu</button>
                                                <button type="button" class="text-xs text-[var(--ui-muted)] hover:underline" title="(UI) Clear">Clear</button>
                                            </div>
                                        </div>

                                        <div class="p-4 space-y-3 flex-1 min-h-0 overflow-y-auto min-w-0">
                                            <div class="min-w-0">
                                                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Kanal: E-Mail (Demo)</div>
                                                <div class="space-y-2">
                                                    <div class="rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                        <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate">Re: Angebot – Q1</div>
                                                        <div class="text-[10px] text-[var(--ui-muted)] truncate">Letzte Nachricht: 10:41 · 2 ungelesen</div>
                                                    </div>
                                                    <div class="rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                        <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate">Follow-up · Termin</div>
                                                        <div class="text-[10px] text-[var(--ui-muted)] truncate">Letzte Nachricht: gestern · gelesen</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-[10px] text-[var(--ui-muted)]">
                                                (Später: echte Thread-Liste, Filter, Kontext)
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Right: Chat (3/4 width) --}}
                                    <div class="col-span-3 min-h-0 min-w-0 flex flex-col overflow-hidden">
                                        <div class="flex-1 min-h-0 bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
                                            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4" id="chatScroll">
                                                <div id="chatList" class="space-y-4 min-w-0">
                                                    <div class="flex justify-start">
                                                        <div class="max-w-4xl rounded-lg p-3 break-words overflow-hidden bg-[var(--ui-surface)] border border-[var(--ui-border)]">
                                                            <div class="text-sm font-semibold mb-1">Assistant</div>
                                                            <div class="whitespace-pre-wrap break-words">Platzhalter-Nachricht (Inbound) …</div>
                                                        </div>
                                                    </div>
                                                    <div class="flex justify-end">
                                                        <div class="max-w-4xl rounded-lg p-3 break-words overflow-hidden bg-[var(--ui-primary)] text-white">
                                                            <div class="text-sm font-semibold mb-1">Du</div>
                                                            <div class="whitespace-pre-wrap break-words">Platzhalter-Antwort (Outbound) …</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-t border-[var(--ui-border)]/60 p-3 flex-shrink-0 bg-[var(--ui-surface)]">
                                                <form class="flex gap-2 items-center" method="post" action="javascript:void(0)" onsubmit="return false;">
                                                    <div class="w-56">
                                                        <div class="w-full h-10 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] flex items-center px-3 text-xs text-[var(--ui-muted)]">
                                                            Kanal (später)
                                                        </div>
                                                    </div>
                                                    <input
                                                        type="text"
                                                        class="flex-1 px-4 h-10 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                                        placeholder="Nachricht eingeben…"
                                                        autocomplete="off"
                                                        disabled
                                                    />
                                                    <button type="submit" class="px-6 py-2 h-10 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 flex items-center gap-2 opacity-60 cursor-not-allowed" disabled>
                                                        <span>Senden</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Placeholder Tabs (UI only) --}}
                    <div x-show="tab==='threads'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex items-center justify-center shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="text-sm text-[var(--ui-muted)]">Threads (kommt später)</div>
                        </div>
                    </div>
                    <div x-show="tab==='settings'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex items-center justify-center shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="text-sm text-[var(--ui-muted)]">Settings (kommt später)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-modal>
</div>

