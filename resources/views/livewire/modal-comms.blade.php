<div>
    {{-- Use a dedicated in-between size: big enough to work, but not full-screen. --}}
    <x-ui-modal size="wide" hideFooter="1" wire:model="open" :closeButton="true">
        <x-slot name="header">
            {{-- Match Playground header layout 1:1 --}}
            <div class="flex items-center justify-between gap-3 w-full">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                            @svg('heroicon-o-paper-airplane', 'w-6 h-6 text-[var(--ui-primary)]')
                        </div>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Kommunikation</h3>
                        <p class="text-sm text-[var(--ui-muted)]">Chat, Threads, Kontext (Demo) – im Modal.</p>
                    </div>
                </div>

                {{-- Tabs in the modal header (requested) --}}
                <div class="flex items-center gap-2 justify-end flex-shrink-0">
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'chat' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Kanäle
                    </button>
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'channels_manage' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Kanäle verwalten
                    </button>
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'connections' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Connections
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
                x-data="{
                    tab: 'chat',
                    activeChannel: 'email',
                    get activeChannelLabel(){
                        return this.activeChannel === 'email' ? 'E-Mail'
                            : (this.activeChannel === 'phone' ? 'Anrufen' : 'WhatsApp');
                    },
                    get activeChannelDetail(){
                        return this.activeChannel === 'email' ? 'm.erren@bhgdigital.de'
                            : '+49 172 123 12 14';
                    },
                    autoGrow(el, maxPx = 132){
                        if(!el) return;
                        el.style.height = 'auto';
                        const next = Math.min(el.scrollHeight || 0, maxPx);
                        el.style.height = (next > 0 ? next : 44) + 'px';
                        el.style.overflowY = (el.scrollHeight > maxPx) ? 'auto' : 'hidden';
                    }
                }"
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
                                                @click="activeChannel = 'email'"
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
                                                @click="activeChannel = 'phone'"
                                                class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1 bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                                                title="Kanal: Anrufen"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-phone', 'w-4 h-4 text-[var(--ui-muted)]')
                                                        <span class="font-semibold text-[var(--ui-secondary)]">Anrufen</span>
                                                        <span class="text-[var(--ui-muted)] hidden sm:inline">· +49 172 123 12 14</span>
                                                    </span>
                                                </span>
                                                <span class="hidden ml-1 w-2 h-2 rounded-full bg-[var(--ui-primary)] animate-pulse"></span>
                                            </button>
                                        </div>
                                        <div class="relative flex-shrink-0 flex items-center">
                                            <button
                                                type="button"
                                                @click="activeChannel = 'whatsapp'"
                                                class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1 bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                                                title="Kanal: WhatsApp"
                                            >
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center gap-1">
                                                        @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4 text-[var(--ui-muted)]')
                                                        <span class="font-semibold text-[var(--ui-secondary)]">WhatsApp</span>
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
                                                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">
                                                    Kanal: <span x-text="activeChannelLabel"></span> <span class="text-[var(--ui-muted)] font-normal">(Demo)</span>
                                                </div>
                                                <div class="space-y-2">
                                                    <div class="rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                        <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate" x-text="activeChannel === 'email' ? 'Re: Angebot – Q1' : (activeChannel === 'phone' ? 'Anrufnotiz · Termin' : 'WhatsApp · Follow-up')"></div>
                                                        <div class="mt-0.5 flex items-center justify-between gap-2">
                                                            <div class="text-[10px] text-[var(--ui-muted)] truncate"
                                                                 x-text="activeChannel === 'email' ? 'Letzte Nachricht: 10:41 · 2 ungelesen' : (activeChannel === 'phone' ? 'Letzte Nachricht: gestern · offen' : 'Letzte Nachricht: heute · 1 ungelesen')"></div>
                                                            {{-- Attachments hint (UI only) --}}
                                                            <div class="flex items-center gap-1 text-[10px] text-[var(--ui-muted)] flex-shrink-0"
                                                                 x-show="activeChannel === 'email'">
                                                                @svg('heroicon-o-paper-clip', 'w-3.5 h-3.5')
                                                                <span>2</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                        <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate" x-text="activeChannel === 'email' ? 'Follow-up · Termin' : (activeChannel === 'phone' ? 'Rückruf · Frage' : 'WhatsApp · Angebot')"></div>
                                                        <div class="mt-0.5 flex items-center justify-between gap-2">
                                                            <div class="text-[10px] text-[var(--ui-muted)] truncate"
                                                                 x-text="activeChannel === 'email' ? 'Letzte Nachricht: gestern · gelesen' : (activeChannel === 'phone' ? 'Letzte Nachricht: letzte Woche · erledigt' : 'Letzte Nachricht: gestern · gelesen')"></div>
                                                            {{-- Attachments hint (UI only) --}}
                                                            <div class="flex items-center gap-1 text-[10px] text-[var(--ui-muted)] flex-shrink-0"
                                                                 x-show="activeChannel === 'email'">
                                                                @svg('heroicon-o-paper-clip', 'w-3.5 h-3.5')
                                                                <span>1</span>
                                                            </div>
                                                        </div>
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
                                                    {{-- E-Mail Verlauf (scrollbar wie Chat, aber mail-typisch) --}}
                                                    <div x-show="activeChannel==='email'" class="space-y-3" x-cloak>
                                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                                                            <span class="px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                Kanal: E-Mail
                                                            </span>
                                                            <span class="truncate">m.erren@bhgdigital.de</span>
                                                        </div>

                                                        {{-- inbound mail --}}
                                                        <div class="flex justify-start">
                                                            <div class="w-full max-w-4xl rounded-xl border border-[var(--ui-border)]/60 bg-white overflow-hidden">
                                                                <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <div class="min-w-0">
                                                                            <div class="flex items-center gap-2 min-w-0">
                                                                                <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">Re: Angebot – Q1</div>
                                                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/60">
                                                                                    Inbound
                                                                                </span>
                                                                            </div>
                                                                            <div class="mt-1 text-xs text-[var(--ui-muted)] truncate">
                                                                                <span class="font-semibold">Von:</span> Marius Erren &lt;m.erren@bhgdigital.de&gt;
                                                                                <span class="mx-1">·</span>
                                                                                <span class="font-semibold">An:</span> Team &lt;sales@company.de&gt;
                                                                            </div>
                                                                            <div class="mt-1 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] font-semibold">ME</span>
                                                                                <span>Extern</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-xs text-[var(--ui-muted)] whitespace-nowrap">Heute 10:41</div>
                                                                    </div>
                                                                </div>
                                                                <div class="px-4 py-4 text-sm text-[var(--ui-secondary)] whitespace-pre-wrap">
Hallo,

habt ihr schon Feedback zum Angebot? Ich hätte gern nächste Woche einen Termin.

Viele Grüße
Marius
                                                                </div>
                                                                {{-- Attachments (UI only) --}}
                                                                <div class="px-4 pb-4">
                                                                    <div class="mt-1 flex flex-wrap gap-2">
                                                                        <div class="inline-flex items-center gap-2 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                                            @svg('heroicon-o-paper-clip', 'w-4 h-4 text-[var(--ui-muted)]')
                                                                            <div class="min-w-0">
                                                                                <div class="text-xs font-semibold text-[var(--ui-secondary)] truncate">Angebot_Q1.pdf</div>
                                                                                <div class="text-[10px] text-[var(--ui-muted)]">PDF · 312 KB</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="inline-flex items-center gap-2 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                                            @svg('heroicon-o-paper-clip', 'w-4 h-4 text-[var(--ui-muted)]')
                                                                            <div class="min-w-0">
                                                                                <div class="text-xs font-semibold text-[var(--ui-secondary)] truncate">Screenshots.zip</div>
                                                                                <div class="text-[10px] text-[var(--ui-muted)]">ZIP · 1.2 MB</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="px-4 py-3 border-t border-[var(--ui-border)]/60 bg-[var(--ui-bg)] flex items-center justify-between">
                                                                    <div class="text-xs text-[var(--ui-muted)]">Inbound</div>
                                                                    <div class="flex items-center gap-2">
                                                                        <button type="button" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:underline" disabled title="(UI)">Antworten</button>
                                                                        <button type="button" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:underline" disabled title="(UI)">Weiterleiten</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {{-- outbound mail --}}
                                                        <div class="flex justify-end">
                                                            <div class="w-full max-w-4xl rounded-xl border border-[var(--ui-border)]/60 bg-[#dcf8c6] overflow-hidden">
                                                                <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 bg-[var(--ui-bg)]/70">
                                                                    <div class="flex items-start justify-between gap-3">
                                                                        <div class="min-w-0">
                                                                            <div class="flex items-center gap-2 min-w-0">
                                                                                <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">Re: Angebot – Q1</div>
                                                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-white/60 text-[var(--ui-secondary)] border border-[var(--ui-border)]/60">
                                                                                    Outbound
                                                                                </span>
                                                                            </div>
                                                                            <div class="mt-1 text-xs text-[var(--ui-muted)] truncate">
                                                                                <span class="font-semibold">Von:</span> Sales Team &lt;sales@company.de&gt;
                                                                                <span class="mx-1">·</span>
                                                                                <span class="font-semibold">An:</span> m.erren@bhgdigital.de
                                                                            </div>
                                                                            <div class="mt-1 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/60 border border-[var(--ui-border)]/60 text-[10px] font-semibold">MR</span>
                                                                                <span>Gesendet von: Martin</span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-xs text-[var(--ui-muted)] whitespace-nowrap">Heute 11:02</div>
                                                                    </div>
                                                                </div>
                                                                <div class="px-4 py-4 text-sm text-[var(--ui-secondary)] whitespace-pre-wrap">
Hi Marius,

ja — lass uns nächste Woche Dienstag 10:00 vorschlagen. Passt das?

Viele Grüße
                                                                </div>
                                                                {{-- Attachments (UI only) --}}
                                                                <div class="px-4 pb-4">
                                                                    <div class="mt-1 flex flex-wrap gap-2">
                                                                        <div class="inline-flex items-center gap-2 rounded-lg border border-[var(--ui-border)]/60 bg-white/60 px-3 py-2">
                                                                            @svg('heroicon-o-paper-clip', 'w-4 h-4 text-[var(--ui-muted)]')
                                                                            <div class="min-w-0">
                                                                                <div class="text-xs font-semibold text-[var(--ui-secondary)] truncate">Terminvorschlag.ics</div>
                                                                                <div class="text-[10px] text-[var(--ui-muted)]">Kalender · 4 KB</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="px-4 py-3 border-t border-[var(--ui-border)]/60 bg-[var(--ui-bg)]/70 flex items-center justify-between">
                                                                    <div class="text-xs text-[var(--ui-muted)]">Outbound</div>
                                                                    <div class="flex items-center gap-2">
                                                                        <button type="button" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:underline" disabled title="(UI)">Erneut senden</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- WhatsApp Verlauf (typische Bubbles) --}}
                                                    <div x-show="activeChannel==='whatsapp'" class="space-y-3" x-cloak>
                                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                                                            <span class="px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                Kanal: WhatsApp
                                                            </span>
                                                            <span class="truncate">+49 172 123 12 14</span>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <div class="flex justify-start">
                                                                <div class="max-w-[85%] rounded-2xl bg-white border border-[var(--ui-border)]/60 px-4 py-2">
                                                                    <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] font-semibold">ME</span>
                                                                        <span>Extern</span>
                                                                    </div>
                                                                    <div class="text-sm text-[var(--ui-secondary)]">
                                                                        Hi, könnt ihr mir kurz den Status geben?
                                                                    </div>
                                                                    <div class="mt-1 text-[10px] text-[var(--ui-muted)] text-right">10:41</div>
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-start">
                                                                <div class="max-w-[85%] rounded-2xl bg-white border border-[var(--ui-border)]/60 px-4 py-2">
                                                                    <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] font-semibold">ME</span>
                                                                        <span>Extern</span>
                                                                    </div>
                                                                    <div class="text-sm text-[var(--ui-secondary)]">
                                                                        Ich schicke euch mal das Dokument.
                                                                    </div>
                                                                    <div class="mt-1 text-[10px] text-[var(--ui-muted)] text-right">10:41</div>
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-end">
                                                                <div class="max-w-[85%] rounded-2xl bg-[#dcf8c6] border border-[var(--ui-border)]/60 px-4 py-2">
                                                                    <div class="flex items-center justify-end gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                        <span>Martin</span>
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/60 border border-[var(--ui-border)]/60 text-[10px] font-semibold">MR</span>
                                                                    </div>
                                                                    <div class="text-sm text-[var(--ui-secondary)]">
                                                                        Klar — ich schaue rein und melde mich gleich.
                                                                    </div>
                                                                    <div class="mt-1 flex items-center justify-end gap-1 text-[10px] text-[var(--ui-muted)]">
                                                                        <span>10:42</span>
                                                                        <span class="text-[var(--ui-muted)]">✓✓</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            {{-- WhatsApp "Dokument"-Bubble --}}
                                                            <div class="flex justify-start">
                                                                <div class="max-w-[85%] rounded-2xl bg-white border border-[var(--ui-border)]/60 px-3 py-2">
                                                                    <div class="flex items-center gap-3">
                                                                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[var(--ui-secondary)]">
                                                                            @svg('heroicon-o-document-text', 'w-5 h-5')
                                                                        </div>
                                                                        <div class="min-w-0">
                                                                            <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">Angebot_Q1.pdf</div>
                                                                            <div class="text-xs text-[var(--ui-muted)]">312 KB · PDF</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-1 text-[10px] text-[var(--ui-muted)] text-right">10:43</div>
                                                                </div>
                                                            </div>
                                                            {{-- WhatsApp "Bild"-Bubble --}}
                                                            <div class="flex justify-end">
                                                                <div class="max-w-[85%] rounded-2xl bg-[#dcf8c6] border border-[var(--ui-border)]/60 p-2">
                                                                    <div class="flex items-center justify-end gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                        <span>Kollege: Anna</span>
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/60 border border-[var(--ui-border)]/60 text-[10px] font-semibold">AK</span>
                                                                    </div>
                                                                    <div class="w-64 h-36 rounded-xl bg-black/5 border border-[var(--ui-border)]/60 flex items-center justify-center">
                                                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                                                                            @svg('heroicon-o-photo', 'w-4 h-4')
                                                                            <span>Bild (UI)</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mt-1 flex items-center justify-end gap-1 text-[10px] text-[var(--ui-muted)]">
                                                                        <span>10:44</span>
                                                                        <span class="text-[var(--ui-muted)]">✓✓</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-end">
                                                                <div class="max-w-[85%] rounded-2xl bg-[#dcf8c6] border border-[var(--ui-border)]/60 px-4 py-2">
                                                                    <div class="flex items-center justify-end gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                        <span>Martin</span>
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/60 border border-[var(--ui-border)]/60 text-[10px] font-semibold">MR</span>
                                                                    </div>
                                                                    <div class="text-sm text-[var(--ui-secondary)]">
                                                                        Dienstag 10:00 passt?
                                                                    </div>
                                                                    <div class="mt-1 flex items-center justify-end gap-1 text-[10px] text-[var(--ui-muted)]">
                                                                        <span>11:02</span>
                                                                        <span class="text-[var(--ui-muted)]">✓</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="flex justify-start">
                                                                <div class="max-w-[85%] rounded-2xl bg-white border border-[var(--ui-border)]/60 px-4 py-2">
                                                                    <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] font-semibold">ME</span>
                                                                        <span>Extern</span>
                                                                    </div>
                                                                    <div class="text-sm text-[var(--ui-secondary)]">
                                                                        Perfekt, danke!
                                                                    </div>
                                                                    <div class="mt-1 text-[10px] text-[var(--ui-muted)] text-right">11:05</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {{-- Anrufen Verlauf (Call Timeline) --}}
                                                    <div x-show="activeChannel==='phone'" class="space-y-3" x-cloak>
                                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                                                            <span class="px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                Kanal: Anrufen
                                                            </span>
                                                            <span class="truncate">+49 172 123 12 14</span>
                                                        </div>

                                                        <div class="space-y-2">
                                                            <div class="rounded-xl border border-[var(--ui-border)]/60 bg-white px-4 py-3">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="min-w-0">
                                                                        <div class="flex items-center gap-2">
                                                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]">
                                                                                @svg('heroicon-o-phone-arrow-down-left', 'w-4 h-4')
                                                                            </span>
                                                                            <div class="min-w-0">
                                                                                <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">Eingehender Anruf</div>
                                                                                <div class="text-xs text-[var(--ui-muted)] truncate">Marius Erren · +49 172 123 12 14</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mt-2 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white border border-[var(--ui-border)]/60 text-[10px] font-semibold">AK</span>
                                                                            <span>Entgegengenommen von: Anna</span>
                                                                        </div>
                                                                        <div class="mt-2 text-sm text-[var(--ui-secondary)]">
                                                                            Notiz: Terminwunsch nächste Woche, Angebot Q1.
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-xs text-[var(--ui-muted)] whitespace-nowrap">
                                                                        Heute 10:41<br>
                                                                        <span class="text-[10px]">Dauer: 02:18</span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] px-4 py-3">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="min-w-0">
                                                                        <div class="flex items-center gap-2">
                                                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/60">
                                                                                @svg('heroicon-o-phone-arrow-up-right', 'w-4 h-4')
                                                                            </span>
                                                                            <div class="min-w-0">
                                                                                <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">Ausgehender Anruf</div>
                                                                                <div class="text-xs text-[var(--ui-muted)] truncate">Keine Antwort</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mt-2 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-primary)]/10 border border-[var(--ui-border)]/60 text-[10px] font-semibold">MR</span>
                                                                            <span>Angerufen von: Martin</span>
                                                                        </div>
                                                                        <div class="mt-2 text-sm text-[var(--ui-secondary)]">
                                                                            Notiz: Rückruf geplant.
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-xs text-[var(--ui-muted)] whitespace-nowrap">
                                                                        Heute 11:15<br>
                                                                        <span class="text-[10px]">Dauer: 00:17</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-t border-[var(--ui-border)]/60 p-3 flex-shrink-0 bg-[var(--ui-surface)]">
                                                <form class="flex gap-2 items-center" method="post" action="javascript:void(0)" onsubmit="return false;">
                                                    {{-- Footer UI je Kanal (nur Optik) --}}
                                                    <template x-if="activeChannel==='email'">
                                                        <div class="flex gap-2 items-end w-full">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-muted)] opacity-60 cursor-not-allowed"
                                                                title="Anhang hinzufügen (UI)"
                                                                disabled
                                                            >
                                                                @svg('heroicon-o-paper-clip', 'w-5 h-5')
                                                            </button>
                                                            <input
                                                                type="text"
                                                                class="w-64 px-4 h-10 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                                                placeholder="Betreff…"
                                                                disabled
                                                            />
                                                            <textarea
                                                                x-ref="emailBody"
                                                                x-init="$nextTick(() => autoGrow($refs.emailBody))"
                                                                @input="autoGrow($event.target)"
                                                                rows="1"
                                                                class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                placeholder="Antwort (Text)…"
                                                                disabled
                                                            ></textarea>
                                                            <button type="button" class="px-6 py-2 h-10 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 flex items-center gap-2 opacity-60 cursor-not-allowed" disabled>
                                                                <span>Senden</span>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <template x-if="activeChannel==='whatsapp'">
                                                        <div class="flex gap-2 items-end w-full">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-muted)] opacity-60 cursor-not-allowed"
                                                                title="Anhang hinzufügen (UI)"
                                                                disabled
                                                            >
                                                                @svg('heroicon-o-paper-clip', 'w-5 h-5')
                                                            </button>
                                                            <textarea
                                                                x-ref="waBody"
                                                                x-init="$nextTick(() => autoGrow($refs.waBody))"
                                                                @input="autoGrow($event.target)"
                                                                rows="1"
                                                                class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                placeholder="Nachricht…"
                                                                disabled
                                                            ></textarea>
                                                            <button type="button" class="px-6 py-2 h-10 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 flex items-center gap-2 opacity-60 cursor-not-allowed" disabled>
                                                                <span>Senden</span>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <template x-if="activeChannel==='phone'">
                                                        <div class="flex gap-2 items-end w-full">
                                                            <div class="flex-1">
                                                                <textarea
                                                                    x-ref="callNote"
                                                                    x-init="$nextTick(() => autoGrow($refs.callNote))"
                                                                    @input="autoGrow($event.target)"
                                                                    rows="1"
                                                                    class="w-full px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                    placeholder="Notiz zum Anruf…"
                                                                    disabled
                                                                ></textarea>
                                                            </div>
                                                            <button type="button" class="px-4 py-2 h-10 border border-[var(--ui-border)] rounded-lg text-[var(--ui-muted)] bg-[var(--ui-bg)] opacity-60 cursor-not-allowed" disabled>
                                                                Anrufen
                                                            </button>
                                                            <button type="button" class="px-6 py-2 h-10 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 flex items-center gap-2 opacity-60 cursor-not-allowed" disabled>
                                                                <span>Speichern</span>
                                                            </button>
                                                        </div>
                                                    </template>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Placeholder Tabs (UI only) --}}
                    <div x-show="tab==='channels_manage'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex items-center justify-center shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="text-sm text-[var(--ui-muted)]">Kanäle verwalten (kommt später)</div>
                        </div>
                    </div>
                    <div x-show="tab==='connections'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex items-center justify-center shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="text-sm text-[var(--ui-muted)]">Connections (kommt später)</div>
                        </div>
                    </div>
                    <div x-show="tab==='settings'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">Settings</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">
                                        Kanal: <span class="font-medium text-[var(--ui-secondary)]" x-text="activeChannelLabel"></span>
                                        <span class="text-[var(--ui-muted)]">·</span>
                                        <span x-text="activeChannelDetail"></span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 justify-end">
                                    <button type="button"
                                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                                        title="(UI) Test"
                                        disabled
                                    >
                                        Test
                                    </button>
                                    <button type="button"
                                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-primary)] text-white border-[var(--ui-primary)] opacity-60 cursor-not-allowed"
                                        title="(UI) Speichern"
                                        disabled
                                    >
                                        Speichern
                                    </button>
                                </div>
                            </div>
                            <div class="flex-1 min-h-0 overflow-y-auto p-4">
                                <div class="text-sm text-[var(--ui-muted)]">
                                    Settings-Inhalt ist kanal-abhängig (UI only).
                                </div>
                                <div class="mt-3 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-3">
                                    <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Beispiel</div>
                                    <div class="text-xs text-[var(--ui-muted)]" x-show="activeChannel==='email'">
                                        SMTP/Postmark, Reply-To, Signatur, Inbound Webhook …
                                    </div>
                                    <div class="text-xs text-[var(--ui-muted)]" x-show="activeChannel==='phone'" x-cloak>
                                        Routing, Call notes, Zuständigkeit, SLA …
                                    </div>
                                    <div class="text-xs text-[var(--ui-muted)]" x-show="activeChannel==='whatsapp'" x-cloak>
                                        Meta OAuth, Templates, Webhook, Business-Nummer …
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-modal>
</div>

