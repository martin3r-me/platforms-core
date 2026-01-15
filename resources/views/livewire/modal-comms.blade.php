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
                    activeEmailChannelId: @entangle('activeEmailChannelId').live,
                    activeThreadId: 1,
                    composeMode: false,
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
                    },
                    scrollToBottom(){
                        this.$nextTick(() => {
                            const el = this.$refs.chatScroll;
                            if (!el) return;
                            el.scrollTop = el.scrollHeight;
                        });
                    },
                    startNewThread(){
                        this.composeMode = true;
                        this.activeThreadId = null;
                        this.scrollToBottom();
                        this.$nextTick(() => {
                            // focus the most relevant textarea if present
                            const el = this.activeChannel === 'email'
                                ? this.$refs.emailBody
                                : (this.activeChannel === 'whatsapp' ? this.$refs.waBody : this.$refs.callNote);
                            try { el?.focus?.(); } catch (_) {}
                        });
                    },
                    selectThread(id){
                        this.composeMode = false;
                        this.activeThreadId = id;
                        this.scrollToBottom();
                        this.$nextTick(() => {
                            const el = this.activeChannel === 'email'
                                ? this.$refs.emailBody
                                : (this.activeChannel === 'whatsapp' ? this.$refs.waBody : this.$refs.callNote);
                            try { el?.focus?.(); } catch (_) {}
                        });
                    },
                    init(){
                        // Ensure scrolling works when switching threads/channels (even if content is swapped)
                        this.$watch('activeThreadId', () => this.scrollToBottom());
                        this.$watch('activeChannel', () => {
                            this.scrollToBottom();
                        });
                        this.$watch('tab', () => this.scrollToBottom());
                    }
                }"
                @comms:set-tab.window="tab = ($event.detail && $event.detail.tab) ? $event.detail.tab : tab"
                x-on:comms:scroll-bottom.window="scrollToBottom()"
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
                                    {{-- Kanäle (eine Zeile: alle E-Mail Absender + Rufnummer + WhatsApp) --}}
                                    <div class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto">
                                        @forelse($emailChannels as $c)
                                            <button
                                                type="button"
                                                @click="activeChannel = 'email'; activeEmailChannelId = {{ (int) $c['id'] }};"
                                                class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1"
                                                :class="(activeChannel === 'email' && activeEmailChannelId === {{ (int) $c['id'] }})
                                                    ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                                    : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]'"
                                                title="E‑Mail Absender"
                                            >
                                                @svg('heroicon-o-envelope', 'w-4 h-4')
                                                <span class="font-semibold">{{ (string) ($c['label'] ?? '') }}</span>
                                            </button>
                                        @empty
                                            <div class="text-xs text-[var(--ui-muted)] px-2">
                                                Kein E‑Mail Absender verfügbar.
                                            </div>
                                        @endforelse

                                        <div class="mx-1 h-4 w-px bg-[var(--ui-border)]/60 flex-shrink-0"></div>

                                        <button
                                            type="button"
                                            @click="activeChannel = 'phone'"
                                            class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1"
                                            :class="activeChannel === 'phone'
                                                ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                                : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]'"
                                            title="Kanal: Rufnummer"
                                        >
                                            @svg('heroicon-o-phone', 'w-4 h-4')
                                            <span class="font-semibold">+49 172 123 12 14</span>
                                            <span class="text-[10px]" :class="activeChannel === 'phone' ? 'text-white/70' : 'text-[var(--ui-muted)]'">(Demo)</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="activeChannel = 'whatsapp'"
                                            class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1"
                                            :class="activeChannel === 'whatsapp'
                                                ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                                : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]'"
                                            title="Kanal: WhatsApp"
                                        >
                                            @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
                                            <span class="font-semibold">WhatsApp · +49 172 123 12 14</span>
                                            <span class="text-[10px]" :class="activeChannel === 'whatsapp' ? 'text-white/70' : 'text-[var(--ui-muted)]'">(Demo)</span>
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
                                                <button type="button" @click="startNewThread()" class="text-xs text-[var(--ui-muted)] hover:underline" title="Neuen Thread starten" x-show="activeChannel !== 'email'">Neu</button>
                                                <button type="button" class="text-xs text-[var(--ui-muted)] hover:underline" wire:click="startNewEmailThread" x-show="activeChannel === 'email'" x-cloak>Neu</button>
                                            </div>
                                        </div>

                                        <div class="p-4 space-y-3 flex-1 min-h-0 overflow-y-auto min-w-0">
                                            <div class="min-w-0">
                                                <div class="space-y-2">
                                                    {{-- Email: echte Threads aus Core --}}
                                                    <div x-show="activeChannel === 'email'" x-cloak class="space-y-2">
                                                        @if(!$activeEmailChannelId)
                                                            <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                                                Bitte oben einen E‑Mail Absender wählen.
                                                            </div>
                                                        @endif

                                                        @forelse($emailThreads as $t)
                                                            <div
                                                                class="w-full rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2 hover:bg-[var(--ui-muted-5)] transition"
                                                                @if((int) $activeEmailThreadId === (int) $t['id']) style="outline: 1px solid rgba(var(--ui-primary-rgb), 0.4);" @endif
                                                            >
                                                                <button
                                                                    type="button"
                                                                    wire:click="setActiveEmailThread({{ (int) $t['id'] }})"
                                                                    class="w-full text-left"
                                                                >
                                                                    <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate">{{ $t['subject'] }}</div>
                                                                    <div class="mt-0.5 text-[10px] text-[var(--ui-muted)] truncate">
                                                                        {{ $t['counterpart'] ?: '—' }}
                                                                    </div>
                                                                    <div class="mt-0.5 text-[10px] text-[var(--ui-muted)] flex items-center justify-between gap-2">
                                                                        <span class="truncate">
                                                                            {{ (int) ($t['messages_count'] ?? 0) }} Nachrichten
                                                                            @if(!empty($t['last_direction']))
                                                                                · {{ $t['last_direction'] === 'inbound' ? 'Inbound' : 'Outbound' }}
                                                                            @endif
                                                                        </span>
                                                                        <span class="flex-shrink-0 whitespace-nowrap">
                                                                            {{ $t['last_at'] ?? '' }}
                                                                        </span>
                                                                    </div>
                                                                </button>
                                                                <div class="mt-2 flex items-center justify-end">
                                                                    <x-ui-confirm-button
                                                                        action="deleteEmailThread"
                                                                        :value="(int) $t['id']"
                                                                        text="Löschen"
                                                                        confirmText="Thread löschen?"
                                                                        variant="muted-outline"
                                                                        size="sm"
                                                                        class="!w-auto"
                                                                    />
                                                                </div>
                                                            </div>
                                                        @empty
                                                            <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-3">
                                                                <div class="text-xs font-semibold text-[var(--ui-secondary)]">Neuer Thread</div>
                                                                <div class="mt-1 text-xs text-[var(--ui-muted)]">
                                                                    Klick oben auf <span class="font-semibold">Neu</span> und sende die erste Nachricht.
                                                                </div>
                                                            </div>
                                                        @endforelse
                                                    </div>

                                                    <button type="button"
                                                        @click="selectThread(1)"
                                                        class="w-full text-left rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2 hover:bg-[var(--ui-muted-5)] transition"
                                                        :class="(!composeMode && activeThreadId === 1) ? 'ring-1 ring-[var(--ui-primary)]/40' : ''"
                                                        x-show="activeChannel !== 'email'"
                                                    >
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
                                                    </button>
                                                    <button type="button"
                                                        @click="selectThread(2)"
                                                        class="w-full text-left rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2 hover:bg-[var(--ui-muted-5)] transition"
                                                        :class="(!composeMode && activeThreadId === 2) ? 'ring-1 ring-[var(--ui-primary)]/40' : ''"
                                                        x-show="activeChannel !== 'email'"
                                                    >
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
                                                    </button>
                                                </div>
                                            </div>
                                            {{-- intentionally blank --}}
                                        </div>
                                    </div>

                                    {{-- Right: Chat (3/4 width) --}}
                                    <div class="col-span-3 min-h-0 min-w-0 flex flex-col overflow-hidden">
                                        <div class="flex-1 min-h-0 bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
                                            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4" id="chatScroll" x-ref="chatScroll">
                                                <div id="chatList" class="space-y-4 min-w-0">
                                                    {{-- E-Mail Verlauf (scrollbar wie Chat, aber mail-typisch) --}}
                                                    <div x-show="activeChannel==='email'" class="space-y-3" x-cloak>
                                                        @if(!$activeEmailChannelId)
                                                            <div class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                                                Kein E‑Mail Kanal ausgewählt/verfügbar.
                                                            </div>
                                                        @else
                                                            @forelse($emailTimeline as $m)
                                                                @php
                                                                    $isInbound = ($m['direction'] ?? '') === 'inbound';
                                                                    $from = (string) ($m['from'] ?? '');
                                                                    $to = (string) ($m['to'] ?? '');
                                                                    $subject = (string) ($m['subject'] ?? '');
                                                                    $body = (string) ($m['text'] ?? '');
                                                                    if ($body === '' && !empty($m['html'])) {
                                                                        $body = trim(strip_tags((string) $m['html']));
                                                                    }
                                                                @endphp
                                                                <div class="flex {{ $isInbound ? 'justify-start' : 'justify-end' }}">
                                                                    <div class="w-full max-w-4xl rounded-xl border {{ $isInbound ? 'border-[var(--ui-border)]/60' : 'border-[var(--ui-primary)]/20' }} bg-white overflow-hidden">
                                                                        <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 {{ $isInbound ? 'bg-[var(--ui-bg)]' : 'bg-[rgba(var(--ui-primary-rgb),0.06)]' }}">
                                                                            <div class="flex items-start justify-between gap-3">
                                                                                <div class="min-w-0">
                                                                                    <div class="flex items-center gap-2 min-w-0">
                                                                                        <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">
                                                                                            {{ $subject ?: 'Ohne Betreff' }}
                                                                                        </div>
                                                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {{ $isInbound ? 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/60' : 'bg-[rgba(var(--ui-primary-rgb),0.12)] text-[var(--ui-primary)] border border-[rgba(var(--ui-primary-rgb),0.18)]' }}">
                                                                                            {{ $isInbound ? 'Inbound' : 'Outbound' }}
                                                                                        </span>
                                                                                    </div>
                                                                                    <div class="mt-1 text-xs text-[var(--ui-muted)] truncate">
                                                                                        <span class="font-semibold">Von:</span> {{ $from ?: '—' }}
                                                                                        <span class="mx-1">·</span>
                                                                                        <span class="font-semibold">An:</span> {{ $to ?: '—' }}
                                                                                    </div>
                                                                                </div>
                                                                                <div class="text-xs text-[var(--ui-muted)] whitespace-nowrap">{{ $m['at'] ?? '' }}</div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="px-4 py-4 text-sm text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $body }}</div>
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <div class="text-sm text-[var(--ui-muted)]">
                                                                    Noch keine Nachrichten im Thread.
                                                                </div>
                                                            @endforelse
                                                        @endif
                                                    </div>

                                                    {{-- WhatsApp Verlauf (typische Bubbles) --}}
                                                    <div x-show="activeChannel==='whatsapp'" class="space-y-3" x-cloak>
                                                        <div class="text-xs text-[var(--ui-muted)] flex items-center gap-2">
                                                            <span class="px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                Kanal: WhatsApp
                                                            </span>
                                                            <span class="truncate">+49 172 123 12 14</span>
                                                            <span class="ml-auto text-[10px] text-[var(--ui-muted)]" x-show="composeMode">Neuer Thread</span>
                                                        </div>

                                                        <div class="space-y-2" x-show="!composeMode">
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

                                                        <div x-show="composeMode" x-cloak class="rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-4">
                                                            <div class="text-sm font-semibold text-[var(--ui-secondary)]">Neuer WhatsApp Thread</div>
                                                            <div class="mt-1 text-sm text-[var(--ui-muted)]">Nachricht verfassen (UI).</div>
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
                                                        <div class="w-full space-y-2">
                                                            {{-- New thread: show To + Subject above message field --}}
                                                            @if(!$activeEmailThreadId)
                                                                <div class="grid grid-cols-1 md:grid-cols-6 gap-2">
                                                                    <div class="md:col-span-3">
                                                                        <x-ui-input-text
                                                                            name="emailCompose.to"
                                                                            label="An"
                                                                            placeholder="empfaenger@firma.de"
                                                                            wire:model.live="emailCompose.to"
                                                                        />
                                                                    </div>
                                                                    <div class="md:col-span-3">
                                                                        <x-ui-input-text
                                                                            name="emailCompose.subject"
                                                                            label="Betreff"
                                                                            placeholder="Betreff…"
                                                                            wire:model.live="emailCompose.subject"
                                                                        />
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            <div class="flex gap-2 items-end w-full">
                                                                <textarea
                                                                    x-ref="emailBody"
                                                                    x-init="$nextTick(() => autoGrow($refs.emailBody))"
                                                                    @input="autoGrow($event.target)"
                                                                    @focus="autoGrow($event.target)"
                                                                    @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendEmail(); }"
                                                                    rows="1"
                                                                    wire:model="emailCompose.body"
                                                                    class="flex-1 w-full px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                    placeholder="Nachricht…"
                                                                ></textarea>
                                                                <x-ui-button
                                                                    variant="primary"
                                                                    size="md"
                                                                    wire:click="sendEmail"
                                                                    wire:loading.attr="disabled"
                                                                    wire:loading.class="animate-pulse"
                                                                    wire:target="sendEmail"
                                                                    class="h-10 self-end"
                                                                >
                                                                    <span wire:loading.remove wire:target="sendEmail">Senden</span>
                                                                    <span wire:loading wire:target="sendEmail">Sende…</span>
                                                                </x-ui-button>
                                                            </div>
                                                            @error('emailCompose.body')
                                                                <div class="mt-1 text-sm text-[color:var(--ui-danger)]">{{ $message }}</div>
                                                            @enderror
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
                                                                @focus="autoGrow($event.target)"
                                                                rows="1"
                                                                class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                placeholder="Nachricht…"
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
                                                                    @focus="autoGrow($event.target)"
                                                                    rows="1"
                                                                    class="w-full px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                    placeholder="Notiz zum Anruf…"
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
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">Kanäle verwalten</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">
                                        Kanäle werden am Root-Team gespeichert:
                                        <span class="font-medium text-[var(--ui-secondary)]">{{ $rootTeamName ?: '—' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <x-ui-button
                                        variant="muted-outline"
                                        size="sm"
                                        wire:click="loadChannels"
                                    >Aktualisieren</x-ui-button>
                                </div>
                            </div>

                            <div class="p-4 flex-1 min-h-0 overflow-y-auto space-y-4">
                                @if($channelsMessage)
                                    <div class="text-sm text-[var(--ui-secondary)]">
                                        {{ $channelsMessage }}
                                    </div>
                                @endif

                                <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm font-semibold text-[var(--ui-secondary)]">Neuer Kanal</div>
                                        <div class="text-xs text-[var(--ui-muted)]">aktuell: Email via Postmark</div>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <x-ui-input-select
                                            name="newChannel.type"
                                            label="Typ"
                                            :options="['email' => 'Email']"
                                            :nullable="false"
                                            displayMode="dropdown"
                                            wire:model.defer="newChannel.type"
                                            :disabled="true"
                                        />
                                        <x-ui-input-select
                                            name="newChannel.provider"
                                            label="Provider"
                                            :options="['postmark' => 'Postmark']"
                                            :nullable="false"
                                            displayMode="dropdown"
                                            wire:model.defer="newChannel.provider"
                                            :disabled="true"
                                        />
                                        <div>
                                            <x-ui-input-select
                                                name="newChannel.visibility"
                                                label="Sichtbarkeit"
                                                :options="['private' => 'privat (nur ich)', 'team' => 'teamweit']"
                                                :nullable="false"
                                                displayMode="dropdown"
                                                wire:model.defer="newChannel.visibility"
                                            />
                                            @if(!$this->canCreateTeamSharedChannel())
                                                <div class="mt-1 text-[11px] text-[var(--ui-muted)]">
                                                    Teamweit nur für Owner/Admin des Root-Teams.
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div class="md:col-span-2">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div class="md:col-span-2">
                                                    <x-ui-input-text
                                                        name="newChannel.sender_local_part"
                                                        label="Absender (Local-Part)"
                                                        placeholder="z.B. sales"
                                                        wire:model.defer="newChannel.sender_local_part"
                                                    />
                                                    <div class="mt-2 text-[11px] text-[var(--ui-muted)]">
                                                        Die Domain wird per Select gewählt (nur aus „Connections“).
                                                    </div>
                                                </div>
                                                <div>
                                                    <x-ui-input-select
                                                        name="newChannel.sender_domain"
                                                        label="Domain"
                                                        :options="$postmarkDomains"
                                                        optionValue="domain"
                                                        optionLabel="domain"
                                                        :nullable="true"
                                                        nullLabel="(Domain wählen)"
                                                        displayMode="dropdown"
                                                        wire:model.defer="newChannel.sender_domain"
                                                        :disabled="empty($postmarkDomains)"
                                                    />
                                                </div>
                                            </div>
                                            @if(empty($postmarkDomains))
                                                <div class="mt-2 text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                                    Keine Domains hinterlegt (bitte erst in „Connections“ anlegen)
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <x-ui-input-text
                                                name="newChannel.name"
                                                label="Name (optional)"
                                                placeholder="z.B. Sales"
                                                wire:model.defer="newChannel.name"
                                            />
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center justify-end">
                                        <x-ui-button
                                            variant="primary"
                                            size="sm"
                                            wire:click="createChannel"
                                            :disabled="empty($postmarkDomains)"
                                            wire:loading.attr="disabled"
                                        >Kanal anlegen</x-ui-button>
                                    </div>

                                    <div class="mt-2 text-xs text-[var(--ui-muted)]">
                                        Hinweis: Der Kanal wird immer der Postmark-Connection zugeordnet (FK) und am Root-Team gespeichert.
                                    </div>
                                </div>

                                <div class="rounded-lg border border-[var(--ui-border)]/60 bg-white overflow-hidden">
                                    <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                                        <div class="text-sm font-semibold text-[var(--ui-secondary)]">Vorhandene Kanäle</div>
                                        <div class="text-xs text-[var(--ui-muted)]">{{ count($channels) }} total</div>
                                    </div>

                                    <div class="divide-y divide-[var(--ui-border)]/60">
                                        @forelse($channels as $c)
                                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">
                                                            {{ $c['sender_identifier'] }}
                                                        </div>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/60">
                                                            {{ $c['provider'] }}
                                                        </span>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/60">
                                                            {{ $c['visibility'] === 'team' ? 'teamweit' : 'privat' }}
                                                        </span>
                                                        @if(!$c['is_active'])
                                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/60">
                                                                inaktiv
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if(!empty($c['name']))
                                                        <div class="mt-1 text-xs text-[var(--ui-muted)] truncate">
                                                            {{ $c['name'] }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2 flex-shrink-0">
                                                    <button
                                                        type="button"
                                                        wire:click="removeChannel({{ (int) $c['id'] }})"
                                                        class="text-xs px-2 py-1 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] text-[var(--ui-muted)] hover:text-red-600 hover:border-red-200"
                                                    >
                                                        Löschen
                                                    </button>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="px-4 py-6 text-sm text-[var(--ui-muted)]">
                                                Noch keine Kanäle angelegt.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div x-show="tab==='connections'" class="w-full h-full min-h-0" x-cloak>
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">Connections</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">
                                        Postmark wird am Root-Team gespeichert:
                                        <span class="font-medium text-[var(--ui-secondary)]">{{ $rootTeamName ?: '—' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <x-ui-button
                                        variant="muted-outline"
                                        size="sm"
                                        wire:click="loadPostmarkConnection"
                                    >Aktualisieren</x-ui-button>
                                    <x-ui-button
                                        variant="primary"
                                        size="sm"
                                        wire:click="savePostmarkConnection"
                                        :disabled="!$this->canManageProviderConnections()"
                                        wire:loading.attr="disabled"
                                    >Speichern</x-ui-button>
                                </div>
                            </div>

                            <div class="p-4 flex-1 min-h-0 overflow-y-auto">
                                @if($postmarkMessage)
                                    <div class="mb-3 text-sm text-[var(--ui-secondary)]">
                                        {{ $postmarkMessage }}
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2">
                                                @svg('heroicon-o-envelope', 'w-5 h-5 text-[var(--ui-primary)]')
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Postmark</div>
                                            </div>
                                            <div class="text-xs text-[var(--ui-muted)]">
                                                Status:
                                                <span class="font-medium text-[var(--ui-secondary)]">
                                                    {{ $postmarkConfigured ? 'konfiguriert' : 'nicht konfiguriert' }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <x-ui-input-text
                                                name="postmark.server_token"
                                                label="Server Token"
                                                type="password"
                                                :placeholder="$postmarkConfigured ? '•••••••• (neu setzen)' : 'postmark server token'"
                                                wire:model.defer="postmark.server_token"
                                                :disabled="!$this->canManageProviderConnections()"
                                            />

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <x-ui-input-text
                                                    name="postmark.inbound_user"
                                                    label="Inbound User (Basic Auth)"
                                                    placeholder="optional"
                                                    wire:model.defer="postmark.inbound_user"
                                                    :disabled="!$this->canManageProviderConnections()"
                                                />
                                                <x-ui-input-text
                                                    name="postmark.inbound_pass"
                                                    label="Inbound Pass (Basic Auth)"
                                                    type="password"
                                                    placeholder="optional"
                                                    wire:model.defer="postmark.inbound_pass"
                                                    :disabled="!$this->canManageProviderConnections()"
                                                />
                                            </div>

                                            <x-ui-input-text
                                                name="postmark.signing_secret"
                                                label="Signing Secret (optional)"
                                                type="password"
                                                placeholder="optional"
                                                wire:model.defer="postmark.signing_secret"
                                                :disabled="!$this->canManageProviderConnections()"
                                            />
                                        </div>

                                        <div class="mt-6 pt-5 border-t border-[var(--ui-border)]/60">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Absender-Domains</div>
                                                <div class="text-xs text-[var(--ui-muted)]">Gilt für Senden + Inbound</div>
                                            </div>

                                            @if($postmarkDomainMessage)
                                                <div class="mt-2 text-sm text-[var(--ui-secondary)]">
                                                    {{ $postmarkDomainMessage }}
                                                </div>
                                            @endif

                                            @if(!$postmarkConfigured)
                                                <div class="mt-2 text-xs text-[var(--ui-muted)]">
                                                    Bitte zuerst die Postmark Connection speichern, dann kannst du Domains anlegen.
                                                </div>
                                            @else
                                                <div class="mt-3 space-y-2">
                                                    @forelse($postmarkDomains as $d)
                                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-[var(--ui-border)]/60 bg-white px-3 py-2">
                                                            <div class="min-w-0">
                                                                <div class="flex items-center gap-2 min-w-0">
                                                                    <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $d['domain'] }}</div>
                                                                    @if($d['is_primary'])
                                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">
                                                                            Primary
                                                                        </span>
                                                                    @endif
                                                                    @if($d['is_verified'])
                                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                            Verified
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                                @if(!empty($d['last_error']))
                                                                    <div class="mt-1 text-[11px] text-[var(--ui-muted)] truncate">
                                                                        {{ $d['last_error'] }}
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                                <button
                                                                    type="button"
                                                                    wire:click="setPostmarkPrimaryDomain({{ (int) $d['id'] }})"
                                                                    class="text-xs px-2 py-1 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] disabled:opacity-60"
                                                                    @if(!$this->canManageProviderConnections() || $d['is_primary']) disabled @endif
                                                                >
                                                                    Primary
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    wire:click="removePostmarkDomain({{ (int) $d['id'] }})"
                                                                    class="text-xs px-2 py-1 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] text-[var(--ui-muted)] hover:text-red-600 hover:border-red-200 disabled:opacity-60"
                                                                    @if(!$this->canManageProviderConnections()) disabled @endif
                                                                >
                                                                    Löschen
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="text-xs text-[var(--ui-muted)]">
                                                            Noch keine Domains hinterlegt.
                                                        </div>
                                                    @endforelse
                                                </div>

                                                <div class="mt-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-3">
                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div class="md:col-span-2">
                                                            <x-ui-input-text
                                                                name="postmarkNewDomain.domain"
                                                                label="Domain"
                                                                placeholder="z.B. company.de"
                                                                wire:model.defer="postmarkNewDomain.domain"
                                                                :disabled="!$this->canManageProviderConnections()"
                                                            />
                                                        </div>
                                                    </div>

                                                    <div class="mt-3 flex items-center justify-between gap-3">
                                                        <label class="inline-flex items-center gap-2 text-sm text-[var(--ui-muted)]">
                                                            <input
                                                                type="checkbox"
                                                                wire:model.defer="postmarkNewDomain.is_primary"
                                                                class="rounded border-[var(--ui-border)]"
                                                                @if(!$this->canManageProviderConnections()) disabled @endif
                                                            />
                                                            <span>Als Primary setzen</span>
                                                        </label>
                                                        <button
                                                            type="button"
                                                            wire:click="addPostmarkDomain"
                                                            class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-primary)] text-white border-[var(--ui-primary)] disabled:opacity-60"
                                                            @if(!$this->canManageProviderConnections()) disabled @endif
                                                        >
                                                            Domain hinzufügen
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        @if(!$this->canManageProviderConnections())
                                            <div class="mt-4 text-xs text-[var(--ui-muted)]">
                                                Hinweis: Nur Owner/Admin des Root-Teams kann Postmark konfigurieren.
                                            </div>
                                        @endif
                                    </div>

                                    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-4">
                                        <div class="text-sm font-semibold text-[var(--ui-secondary)]">Hinweise</div>
                                        <ul class="mt-2 text-sm text-[var(--ui-muted)] space-y-2">
                                            <li><span class="font-semibold text-[var(--ui-secondary)]">Scope:</span> Speicherung erfolgt immer am Root-Team (Parent-Team).</li>
                                            <li><span class="font-semibold text-[var(--ui-secondary)]">Sicherheit:</span> Credentials werden verschlüsselt gespeichert. Nach dem Speichern werden Secret-Felder wieder geleert.</li>
                                            <li><span class="font-semibold text-[var(--ui-secondary)]">Nächster Schritt:</span> Email-Inbound/Outbound liest diese Connection statt `.env`.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
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

