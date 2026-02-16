<div @if($open) wire:poll.5s="refreshTimelines" @endif>
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
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('comms:set-tab', { detail: { tab: 'debug-whatsapp' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border border-red-300 text-red-600 hover:bg-red-50"
                    >
                        Debug WA
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
                    activeWhatsAppChannelId: @entangle('activeWhatsAppChannelId').live,
                    activeThreadId: 1,
                    composeMode: false,
                    isAtBottom: true,
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
                    onScroll(el) {
                        // Bei flex-col-reverse: scrollTop nahe 0 = User ist bei neuesten Nachrichten
                        this.isAtBottom = el.scrollTop > -50;
                    },
                    scrollToBottom(force = false){
                        if (!force && !this.isAtBottom) return; // Nicht scrollen wenn User hochgescrollt hat
                        this.$nextTick(() => {
                            const el = this.$refs.chatScroll;
                            if (!el) return;
                            el.scrollTop = 0; // Bei flex-col-reverse: 0 = neueste Nachrichten
                            this.isAtBottom = true;
                        });
                    },
                    startNewThread(){
                        this.composeMode = true;
                        this.activeThreadId = null;
                        this.scrollToBottom(true);
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
                        this.scrollToBottom(true);
                        this.$nextTick(() => {
                            const el = this.activeChannel === 'email'
                                ? this.$refs.emailBody
                                : (this.activeChannel === 'whatsapp' ? this.$refs.waBody : this.$refs.callNote);
                            try { el?.focus?.(); } catch (_) {}
                        });
                    },
                    init(){
                        // Ensure scrolling works when switching threads/channels (even if content is swapped)
                        this.$watch('activeThreadId', () => this.scrollToBottom(true));
                        this.$watch('activeChannel', () => {
                            this.scrollToBottom(true);
                        });
                        this.$watch('tab', () => this.scrollToBottom(true));
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

                                        @forelse($whatsappChannels as $wc)
                                            <button
                                                type="button"
                                                @click="activeChannel = 'whatsapp'; activeWhatsAppChannelId = {{ (int) $wc['id'] }};"
                                                class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1"
                                                :class="(activeChannel === 'whatsapp' && activeWhatsAppChannelId === {{ (int) $wc['id'] }})
                                                    ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]'
                                                    : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]'"
                                                title="Kanal: WhatsApp"
                                            >
                                                @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
                                                <span class="font-semibold">{{ $wc['name'] ?: 'WhatsApp' }} · {{ (string) ($wc['label'] ?? '') }}</span>
                                            </button>
                                        @empty
                                            {{-- Kein WhatsApp Channel verfügbar --}}
                                        @endforelse
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
                                                <button type="button" @click="startNewThread()" class="text-xs text-[var(--ui-muted)] hover:underline" title="Neuen Thread starten" x-show="activeChannel === 'phone'">Neu</button>
                                                <button type="button" class="text-xs text-[var(--ui-muted)] hover:underline" wire:click="startNewEmailThread" x-show="activeChannel === 'email'" x-cloak>Neu</button>
                                                <button type="button" class="text-xs text-[var(--ui-muted)] hover:underline" wire:click="startNewWhatsAppThread" x-show="activeChannel === 'whatsapp'" x-cloak>Neu</button>
                                            </div>
                                        </div>

                                        <div class="p-4 space-y-3 flex-1 min-h-0 overflow-y-auto min-w-0">
                                            {{-- Kontext-Karte --}}
                                            @if($contextModel)
                                                <div class="rounded-lg border border-[rgba(var(--ui-primary-rgb),0.2)] bg-[rgba(var(--ui-primary-rgb),0.04)] px-3 py-2 mb-1">
                                                    <div class="flex items-center gap-1.5">
                                                        @svg('heroicon-o-link', 'w-3.5 h-3.5 text-[var(--ui-primary)] flex-shrink-0')
                                                        <span class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate">{{ class_basename($contextModel) }} #{{ $contextModelId }}</span>
                                                    </div>
                                                    @if($contextSubject)
                                                        <div class="mt-1 text-[10px] text-[var(--ui-muted)] truncate">{{ $contextSubject }}</div>
                                                    @endif
                                                    @if($contextSource)
                                                        <div class="mt-0.5 text-[10px] text-[var(--ui-primary)]/70">{{ $contextSource }}</div>
                                                    @endif
                                                </div>

                                                {{-- Toggle: Alle Threads anzeigen --}}
                                                <button
                                                    type="button"
                                                    wire:click="toggleShowAllThreads"
                                                    class="w-full text-left text-[10px] px-2 py-1 rounded border transition
                                                        {{ $showAllThreads
                                                            ? 'border-[var(--ui-primary)]/30 bg-[rgba(var(--ui-primary-rgb),0.06)] text-[var(--ui-primary)] font-semibold'
                                                            : 'border-[var(--ui-border)]/60 bg-[var(--ui-bg)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
                                                >
                                                    {{ $showAllThreads ? 'Nur Kontext-Threads' : 'Alle Threads anzeigen' }}
                                                </button>
                                            @else
                                                {{-- Kein Kontext - Hinweis anzeigen --}}
                                                <div class="rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] px-3 py-2 mb-1">
                                                    <div class="flex items-center gap-1.5">
                                                        @svg('heroicon-o-link-slash', 'w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0')
                                                        <span class="text-[11px] font-medium text-[var(--ui-muted)]">Kein Kontext</span>
                                                    </div>
                                                    <div class="mt-1 text-[10px] text-[var(--ui-muted)]/70">Alle Threads werden angezeigt</div>
                                                </div>
                                            @endif

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
                                                                    <div class="mt-1 flex items-center gap-2 min-w-0">
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] font-semibold flex-shrink-0">
                                                                            {{ strtoupper(substr((string) ($t['counterpart'] ?: '—'), 0, 1)) }}
                                                                        </span>
                                                                        <div class="text-[10px] text-[var(--ui-muted)] truncate">
                                                                            {{ $t['counterpart'] ?: '—' }}
                                                                        </div>
                                                                    </div>
                                                                </button>
                                                                <div class="mt-2 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-[var(--ui-border)]/60 bg-white">
                                                                        @svg('heroicon-o-chat-bubble-left-ellipsis', 'w-3.5 h-3.5')
                                                                        <span class="font-semibold">{{ (int) ($t['messages_count'] ?? 0) }}</span>
                                                                    </span>

                                                                    @if(!empty($t['last_direction']))
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-[10px] font-semibold
                                                                            {{ $t['last_direction'] === 'inbound'
                                                                                ? 'border-[rgba(var(--ui-primary-rgb),0.18)] bg-[rgba(var(--ui-primary-rgb),0.08)] text-[var(--ui-primary)]'
                                                                                : 'border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}
                                                                        ">
                                                                            {{ $t['last_direction'] === 'inbound' ? 'Inbound' : 'Outbound' }}
                                                                        </span>
                                                                    @endif

                                                                    <span class="ml-auto flex items-center gap-2">
                                                                        <span class="whitespace-nowrap">{{ $t['last_at'] ?? '' }}</span>
                                                                        <div x-data="{ confirmDelete: false }">
                                                                            <x-ui-button
                                                                                variant="muted-outline"
                                                                                size="sm"
                                                                                class="!w-auto !px-2 !py-1 h-6"
                                                                                x-on:click="
                                                                                    if (!confirmDelete) {
                                                                                        confirmDelete = true;
                                                                                        setTimeout(() => { confirmDelete = false; }, 2500);
                                                                                    } else {
                                                                                        $wire.call('deleteEmailThread', {{ (int) $t['id'] }});
                                                                                    }
                                                                                "
                                                                                title="Thread löschen"
                                                                            >
                                                                                <span x-show="!confirmDelete" class="inline-flex items-center">
                                                                                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                                                </span>
                                                                                <span x-show="confirmDelete" x-cloak class="text-[10px] font-semibold">Löschen?</span>
                                                                            </x-ui-button>
                                                                        </div>
                                                                    </span>
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

                                                    {{-- WhatsApp: echte Threads aus Core --}}
                                                    <div x-show="activeChannel === 'whatsapp'" x-cloak class="space-y-2">
                                                        @if(!$activeWhatsAppChannelId)
                                                            <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                                                Bitte oben einen WhatsApp Kanal wählen.
                                                            </div>
                                                        @endif

                                                        @forelse($whatsappThreads as $wt)
                                                            <div
                                                                class="w-full rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2 hover:bg-[var(--ui-muted-5)] transition"
                                                                @if((int) $activeWhatsAppThreadId === (int) $wt['id']) style="outline: 1px solid rgba(var(--ui-primary-rgb), 0.4);" @endif
                                                            >
                                                                <button
                                                                    type="button"
                                                                    wire:click="setActiveWhatsAppThread({{ (int) $wt['id'] }})"
                                                                    class="w-full text-left"
                                                                >
                                                                    <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate flex items-center gap-2">
                                                                        @svg('heroicon-o-chat-bubble-left-right', 'w-3.5 h-3.5 flex-shrink-0')
                                                                        {{ $wt['remote_phone'] }}
                                                                        @if($wt['is_unread'])
                                                                            <span class="inline-flex items-center justify-center w-2 h-2 rounded-full bg-[var(--ui-primary)]"></span>
                                                                        @endif
                                                                    </div>
                                                                    @if(!empty($wt['last_message_preview']))
                                                                        <div class="mt-1 text-[10px] text-[var(--ui-muted)] truncate">
                                                                            {{ \Illuminate\Support\Str::limit($wt['last_message_preview'], 50) }}
                                                                        </div>
                                                                    @endif
                                                                </button>
                                                                <div class="mt-2 flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full border border-[var(--ui-border)]/60 bg-white">
                                                                        @svg('heroicon-o-chat-bubble-left-ellipsis', 'w-3.5 h-3.5')
                                                                        <span class="font-semibold">{{ (int) ($wt['messages_count'] ?? 0) }}</span>
                                                                    </span>

                                                                    @if(!empty($wt['last_direction']))
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full border text-[10px] font-semibold
                                                                            {{ $wt['last_direction'] === 'inbound'
                                                                                ? 'border-[rgba(var(--ui-primary-rgb),0.18)] bg-[rgba(var(--ui-primary-rgb),0.08)] text-[var(--ui-primary)]'
                                                                                : 'border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}
                                                                        ">
                                                                            {{ $wt['last_direction'] === 'inbound' ? 'Inbound' : 'Outbound' }}
                                                                        </span>
                                                                    @endif

                                                                    <span class="ml-auto flex items-center gap-2">
                                                                        <span class="whitespace-nowrap">{{ $wt['last_at'] ?? '' }}</span>
                                                                        <div x-data="{ confirmDelete: false }">
                                                                            <x-ui-button
                                                                                variant="muted-outline"
                                                                                size="sm"
                                                                                class="!w-auto !px-2 !py-1 h-6"
                                                                                x-on:click="
                                                                                    if (!confirmDelete) {
                                                                                        confirmDelete = true;
                                                                                        setTimeout(() => { confirmDelete = false; }, 2500);
                                                                                    } else {
                                                                                        $wire.call('deleteWhatsAppThread', {{ (int) $wt['id'] }});
                                                                                    }
                                                                                "
                                                                                title="Thread löschen"
                                                                            >
                                                                                <span x-show="!confirmDelete" class="inline-flex items-center">
                                                                                    @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                                                                </span>
                                                                                <span x-show="confirmDelete" x-cloak class="text-[10px] font-semibold">Löschen?</span>
                                                                            </x-ui-button>
                                                                        </div>
                                                                    </span>
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

                                                    {{-- Phone: Demo Threads (UI only) --}}
                                                    <button type="button"
                                                        @click="selectThread(1)"
                                                        class="w-full text-left rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2 hover:bg-[var(--ui-muted-5)] transition"
                                                        :class="(!composeMode && activeThreadId === 1) ? 'ring-1 ring-[var(--ui-primary)]/40' : ''"
                                                        x-show="activeChannel === 'phone'"
                                                    >
                                                        <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate">Anrufnotiz · Termin</div>
                                                        <div class="mt-0.5 flex items-center justify-between gap-2">
                                                            <div class="text-[10px] text-[var(--ui-muted)] truncate">Letzte Nachricht: gestern · offen</div>
                                                        </div>
                                                    </button>
                                                    <button type="button"
                                                        @click="selectThread(2)"
                                                        class="w-full text-left rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2 hover:bg-[var(--ui-muted-5)] transition"
                                                        :class="(!composeMode && activeThreadId === 2) ? 'ring-1 ring-[var(--ui-primary)]/40' : ''"
                                                        x-show="activeChannel === 'phone'"
                                                    >
                                                        <div class="text-[11px] font-semibold text-[var(--ui-secondary)] truncate">Rückruf · Frage</div>
                                                        <div class="mt-0.5 flex items-center justify-between gap-2">
                                                            <div class="text-[10px] text-[var(--ui-muted)] truncate">Letzte Nachricht: letzte Woche · erledigt</div>
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
                                            <div class="flex-1 min-h-0 overflow-y-auto p-4 flex flex-col-reverse" id="chatScroll" x-ref="chatScroll" @scroll="onScroll($el)">
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
                                                                    $body = trim((string) ($m['text'] ?? ''));
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
                                                            <span class="truncate">{{ $activeWhatsAppChannelPhone ?? '' }}</span>
                                                            @if($activeWhatsAppThreadId || !$whatsappWindowOpen)
                                                                @if($whatsappWindowOpen)
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                        @svg('heroicon-o-check-circle', 'w-3 h-3')
                                                                        Fenster offen
                                                                    </span>
                                                                @else
                                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                                        @svg('heroicon-o-clock', 'w-3 h-3')
                                                                        Nur Templates
                                                                    </span>
                                                                @endif
                                                            @endif
                                                            @if(!$activeWhatsAppThreadId)
                                                                <span class="ml-auto text-[10px] text-[var(--ui-muted)]">Neuer Thread</span>
                                                            @endif
                                                        </div>

                                                        @if(!$activeWhatsAppChannelId)
                                                            <div class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                                                Kein WhatsApp Kanal ausgewählt/verfügbar.
                                                            </div>
                                                        @else
                                                            @if($activeWhatsAppThreadId)
                                                                {{-- Show messages for selected thread --}}
                                                                <div class="space-y-2">
                                                                    @forelse($whatsappTimeline as $wm)
                                                                        @php
                                                                            $isInbound = ($wm['direction'] ?? '') === 'inbound';
                                                                            $body = (string) ($wm['body'] ?? '');
                                                                            $at = (string) ($wm['at'] ?? '');
                                                                            $fullAt = (string) ($wm['full_at'] ?? '');
                                                                            $sentBy = (string) ($wm['sent_by'] ?? '');
                                                                            $status = (string) ($wm['status'] ?? '');
                                                                            $messageType = (string) ($wm['message_type'] ?? 'text');
                                                                            $mediaDisplayType = (string) ($wm['media_display_type'] ?? $messageType);
                                                                            $hasMedia = (bool) ($wm['has_media'] ?? false);
                                                                            $attachments = $wm['attachments'] ?? [];
                                                                        @endphp

                                                                        @if($isInbound)
                                                                            {{-- Inbound bubble (left, white) --}}
                                                                            <div class="flex justify-start">
                                                                                <div class="max-w-[85%] rounded-2xl bg-white border border-[var(--ui-border)]/60 px-4 py-2">
                                                                                    <div class="flex items-center gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[10px] font-semibold">
                                                                                            @svg('heroicon-o-user', 'w-3 h-3')
                                                                                        </span>
                                                                                        <span>Extern</span>
                                                                                    </div>
                                                                                    @if($hasMedia && !empty($attachments))
                                                                                        @foreach($attachments as $att)
                                                                                            @php
                                                                                                $attUrl = $att['url'] ?? null;
                                                                                                $attThumb = $att['thumbnail'] ?? $attUrl;
                                                                                                $attTitle = $att['title'] ?? 'Datei';
                                                                                                $attCaption = $att['meta']['caption'] ?? null;
                                                                                            @endphp
                                                                                            @if($mediaDisplayType === 'image' && $attUrl)
                                                                                                {{-- Image: inline preview --}}
                                                                                                <a href="{{ $attUrl }}" target="_blank" class="block my-2">
                                                                                                    <img src="{{ $attThumb }}" alt="{{ $attTitle }}" class="rounded-xl max-w-full max-h-64 object-cover" loading="lazy" />
                                                                                                </a>
                                                                                            @elseif($mediaDisplayType === 'sticker' && $attUrl)
                                                                                                {{-- Sticker: smaller inline image --}}
                                                                                                <div class="my-2">
                                                                                                    <img src="{{ $attUrl }}" alt="Sticker" class="w-32 h-32 object-contain" loading="lazy" />
                                                                                                </div>
                                                                                            @elseif($mediaDisplayType === 'video' && $attUrl)
                                                                                                {{-- Video: HTML5 player --}}
                                                                                                <div class="my-2">
                                                                                                    <video controls preload="metadata" class="rounded-xl max-w-full max-h-64">
                                                                                                        <source src="{{ $attUrl }}" />
                                                                                                    </video>
                                                                                                </div>
                                                                                            @elseif(($mediaDisplayType === 'voice' || $mediaDisplayType === 'audio') && $attUrl)
                                                                                                {{-- Voice/Audio: HTML5 audio player --}}
                                                                                                <div class="my-2 flex items-center gap-2">
                                                                                                    @svg('heroicon-o-microphone', 'w-5 h-5 text-[var(--ui-muted)] shrink-0')
                                                                                                    <audio controls preload="metadata" class="h-8 w-full min-w-[180px]">
                                                                                                        <source src="{{ $attUrl }}" />
                                                                                                    </audio>
                                                                                                </div>
                                                                                            @elseif($mediaDisplayType === 'document' && $attUrl)
                                                                                                {{-- Document: download link --}}
                                                                                                <a href="{{ $attUrl }}" target="_blank" class="flex items-center gap-3 my-2 px-3 py-2 rounded-xl bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-10)] transition-colors">
                                                                                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-[var(--ui-border)]/60 text-[var(--ui-secondary)] shrink-0">
                                                                                                        @svg('heroicon-o-document-text', 'w-5 h-5')
                                                                                                    </div>
                                                                                                    <div class="min-w-0 flex-1">
                                                                                                        <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $attTitle }}</div>
                                                                                                        <div class="text-xs text-[var(--ui-muted)]">Dokument</div>
                                                                                                    </div>
                                                                                                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-muted)] shrink-0')
                                                                                                </a>
                                                                                            @else
                                                                                                {{-- Fallback: icon with type --}}
                                                                                                <div class="flex items-center gap-3 my-2">
                                                                                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[var(--ui-secondary)]">
                                                                                                        @svg('heroicon-o-paper-clip', 'w-5 h-5')
                                                                                                    </div>
                                                                                                    <div class="min-w-0">
                                                                                                        <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $attTitle }}</div>
                                                                                                        <div class="text-xs text-[var(--ui-muted)]">{{ ucfirst($mediaDisplayType) }}</div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    @elseif($hasMedia && empty($attachments))
                                                                                        {{-- Media without file (processing or failed) --}}
                                                                                        <div class="flex items-center gap-3 my-2">
                                                                                            <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 text-[var(--ui-muted)]">
                                                                                                @if($mediaDisplayType === 'image' || $mediaDisplayType === 'sticker')
                                                                                                    @svg('heroicon-o-photo', 'w-5 h-5')
                                                                                                @elseif($mediaDisplayType === 'video')
                                                                                                    @svg('heroicon-o-video-camera', 'w-5 h-5')
                                                                                                @elseif($mediaDisplayType === 'voice' || $mediaDisplayType === 'audio')
                                                                                                    @svg('heroicon-o-microphone', 'w-5 h-5')
                                                                                                @else
                                                                                                    @svg('heroicon-o-document-text', 'w-5 h-5')
                                                                                                @endif
                                                                                            </div>
                                                                                            <div class="min-w-0">
                                                                                                <div class="text-sm text-[var(--ui-muted)] truncate">{{ ucfirst($mediaDisplayType) }}</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                    @if($body)
                                                                                        <div class="text-sm text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $body }}</div>
                                                                                    @endif
                                                                                    <div class="mt-1 text-[10px] text-[var(--ui-muted)] text-right" title="{{ $fullAt }}">{{ $at }}</div>
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            {{-- Outbound bubble (right, green) --}}
                                                                            <div class="flex justify-end">
                                                                                <div class="max-w-[85%] rounded-2xl bg-[#dcf8c6] border border-[var(--ui-border)]/60 px-4 py-2">
                                                                                    <div class="flex items-center justify-end gap-2 text-[10px] text-[var(--ui-muted)]">
                                                                                        @if($messageType === 'template')
                                                                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/40 bg-white/50 text-[10px]">
                                                                                                @svg('heroicon-o-document-text', 'w-3 h-3')
                                                                                                Template
                                                                                            </span>
                                                                                        @endif
                                                                                        <span>{{ $sentBy ?: 'Ich' }}</span>
                                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/60 border border-[var(--ui-border)]/60 text-[10px] font-semibold">
                                                                                            {{ strtoupper(substr($sentBy ?: 'I', 0, 1)) }}{{ strtoupper(substr($sentBy ?: '', -1, 1) ?: '') }}
                                                                                        </span>
                                                                                    </div>
                                                                                    @if($hasMedia && !empty($attachments))
                                                                                        @foreach($attachments as $att)
                                                                                            @php
                                                                                                $attUrl = $att['url'] ?? null;
                                                                                                $attThumb = $att['thumbnail'] ?? $attUrl;
                                                                                                $attTitle = $att['title'] ?? 'Datei';
                                                                                            @endphp
                                                                                            @if($mediaDisplayType === 'image' && $attUrl)
                                                                                                <a href="{{ $attUrl }}" target="_blank" class="block my-2">
                                                                                                    <img src="{{ $attThumb }}" alt="{{ $attTitle }}" class="rounded-xl max-w-full max-h-64 object-cover" loading="lazy" />
                                                                                                </a>
                                                                                            @elseif($mediaDisplayType === 'video' && $attUrl)
                                                                                                <div class="my-2">
                                                                                                    <video controls preload="metadata" class="rounded-xl max-w-full max-h-64">
                                                                                                        <source src="{{ $attUrl }}" />
                                                                                                    </video>
                                                                                                </div>
                                                                                            @elseif(($mediaDisplayType === 'voice' || $mediaDisplayType === 'audio') && $attUrl)
                                                                                                <div class="my-2 flex items-center gap-2">
                                                                                                    @svg('heroicon-o-microphone', 'w-5 h-5 text-[var(--ui-muted)] shrink-0')
                                                                                                    <audio controls preload="metadata" class="h-8 w-full min-w-[180px]">
                                                                                                        <source src="{{ $attUrl }}" />
                                                                                                    </audio>
                                                                                                </div>
                                                                                            @elseif($mediaDisplayType === 'document' && $attUrl)
                                                                                                <a href="{{ $attUrl }}" target="_blank" class="flex items-center gap-3 my-2 px-3 py-2 rounded-xl bg-white/60 border border-[var(--ui-border)]/60 hover:bg-white transition-colors">
                                                                                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-[var(--ui-border)]/60 text-[var(--ui-secondary)] shrink-0">
                                                                                                        @svg('heroicon-o-document-text', 'w-5 h-5')
                                                                                                    </div>
                                                                                                    <div class="min-w-0 flex-1">
                                                                                                        <div class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $attTitle }}</div>
                                                                                                        <div class="text-xs text-[var(--ui-muted)]">Dokument</div>
                                                                                                    </div>
                                                                                                    @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-muted)] shrink-0')
                                                                                                </a>
                                                                                            @else
                                                                                                <div class="flex items-center gap-3 my-2">
                                                                                                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white/60 border border-[var(--ui-border)]/60 text-[var(--ui-secondary)]">
                                                                                                        @svg('heroicon-o-paper-clip', 'w-5 h-5')
                                                                                                    </div>
                                                                                                    <div class="min-w-0">
                                                                                                        <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $attTitle }}</div>
                                                                                                        <div class="text-xs text-[var(--ui-muted)]">{{ ucfirst($mediaDisplayType) }}</div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endif
                                                                                        @endforeach
                                                                                    @endif
                                                                                    @if($body)
                                                                                        <div class="text-sm text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $body }}</div>
                                                                                    @endif
                                                                                    <div class="mt-1 flex items-center justify-end gap-1 text-[10px] text-[var(--ui-muted)]">
                                                                                        <span title="{{ $fullAt }}">{{ $at }}</span>
                                                                                        @if($status === 'read')
                                                                                            <span class="text-blue-500">✓✓</span>
                                                                                        @elseif($status === 'delivered')
                                                                                            <span class="text-[var(--ui-muted)]">✓✓</span>
                                                                                        @elseif($status === 'sent')
                                                                                            <span class="text-[var(--ui-muted)]">✓</span>
                                                                                        @elseif($status === 'failed')
                                                                                            <span class="text-red-500">✕</span>
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    @empty
                                                                        <div class="text-sm text-[var(--ui-muted)]">
                                                                            Noch keine Nachrichten im Thread.
                                                                        </div>
                                                                    @endforelse
                                                                </div>
                                                            @else
                                                                {{-- New thread mode --}}
                                                                <div class="rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] p-4">
                                                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">Neuer WhatsApp Thread</div>
                                                                    <div class="mt-1 text-sm text-[var(--ui-muted)]">
                                                                        Gib unten eine Telefonnummer und Nachricht ein und klicke auf Senden.
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @endif
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
                                                            @if($emailMessage)
                                                                <div class="mt-1 text-sm text-[var(--ui-secondary)]">{{ $emailMessage }}</div>
                                                            @endif
                                                        </div>
                                                    </template>
                                                    <template x-if="activeChannel==='whatsapp'">
                                                        <div class="w-full space-y-2">
                                                            {{-- New thread: show To field --}}
                                                            @if(!$activeWhatsAppThreadId)
                                                                <div class="grid grid-cols-1">
                                                                    <x-ui-input-text
                                                                        name="whatsappCompose.to"
                                                                        label="An (Telefonnummer)"
                                                                        placeholder="+49 172 123 45 67"
                                                                        wire:model.live="whatsappCompose.to"
                                                                    />
                                                                </div>
                                                            @endif

                                                            @if($whatsappWindowOpen)
                                                                {{-- OPEN WINDOW: Normal freetext compose --}}
                                                                <div class="flex gap-2 items-end w-full">
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-muted)] opacity-60 cursor-not-allowed"
                                                                        title="Anhang (bald verfügbar)"
                                                                        disabled
                                                                    >
                                                                        @svg('heroicon-o-paper-clip', 'w-5 h-5')
                                                                    </button>
                                                                    <textarea
                                                                        x-ref="waBody"
                                                                        x-init="$nextTick(() => autoGrow($refs.waBody))"
                                                                        @input="autoGrow($event.target)"
                                                                        @focus="autoGrow($event.target)"
                                                                        @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendWhatsApp(); }"
                                                                        rows="1"
                                                                        wire:model="whatsappCompose.body"
                                                                        class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                                                        placeholder="Nachricht…"
                                                                    ></textarea>
                                                                    <x-ui-button
                                                                        variant="primary"
                                                                        size="md"
                                                                        wire:click="sendWhatsApp"
                                                                        wire:loading.attr="disabled"
                                                                        wire:loading.class="animate-pulse"
                                                                        wire:target="sendWhatsApp"
                                                                        class="h-10 self-end"
                                                                    >
                                                                        <span wire:loading.remove wire:target="sendWhatsApp">Senden</span>
                                                                        <span wire:loading wire:target="sendWhatsApp">Sende…</span>
                                                                    </x-ui-button>
                                                                </div>
                                                            @else
                                                                {{-- CLOSED WINDOW: Template selection mode --}}
                                                                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2">
                                                                    <div class="flex items-start gap-2">
                                                                        @svg('heroicon-o-clock', 'w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5')
                                                                        <div class="text-xs text-amber-800">
                                                                            <span class="font-semibold">24-Stunden-Fenster geschlossen.</span>
                                                                            Seit der letzten eingehenden Nachricht sind mehr als 24 Stunden vergangen.
                                                                            Gemäß Meta-Richtlinien können nur noch vorab genehmigte Templates versendet werden.
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                {{-- Template selection --}}
                                                                @if(!empty($whatsappTemplates))
                                                                    <div class="space-y-2">
                                                                        <label class="block text-xs font-semibold text-[var(--ui-secondary)]">Template auswählen</label>
                                                                        <select
                                                                            x-on:change="$wire.selectWhatsAppTemplate($event.target.value ? Number($event.target.value) : null)"
                                                                            class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] bg-white"
                                                                        >
                                                                            <option value="">-- Template wählen --</option>
                                                                            @foreach($whatsappTemplates as $tpl)
                                                                                <option value="{{ $tpl['id'] }}" @if($whatsappSelectedTemplateId === $tpl['id']) selected @endif>
                                                                                    {{ $tpl['name'] }} ({{ $tpl['language'] }})
                                                                                    @if($tpl['category']) — {{ $tpl['category'] }} @endif
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>

                                                                    {{-- Template preview & variable inputs --}}
                                                                    @if(!empty($whatsappTemplatePreview))
                                                                        <div class="rounded-lg border border-[var(--ui-border)]/60 bg-white p-3 space-y-3">
                                                                            <div class="flex items-center gap-2">
                                                                                @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-primary)]')
                                                                                <span class="text-xs font-semibold text-[var(--ui-secondary)]">
                                                                                    {{ $whatsappTemplatePreview['name'] ?? '' }}
                                                                                </span>
                                                                                <span class="text-[10px] text-[var(--ui-muted)] px-1.5 py-0.5 rounded-full border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                                    {{ $whatsappTemplatePreview['language'] ?? '' }}
                                                                                </span>
                                                                                @if(!empty($whatsappTemplatePreview['category']))
                                                                                    <span class="text-[10px] text-[var(--ui-muted)] px-1.5 py-0.5 rounded-full border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                                                                                        {{ $whatsappTemplatePreview['category'] }}
                                                                                    </span>
                                                                                @endif
                                                                            </div>

                                                                            {{-- Template body preview --}}
                                                                            <div class="rounded-lg bg-[#dcf8c6] border border-[var(--ui-border)]/30 px-3 py-2">
                                                                                <div class="text-sm text-[var(--ui-secondary)] whitespace-pre-wrap">{{ $this->getTemplatePreviewText() }}</div>
                                                                            </div>

                                                                            {{-- Variable inputs --}}
                                                                            @if(($whatsappTemplatePreview['variables_count'] ?? 0) > 0)
                                                                                <div class="space-y-2">
                                                                                    <div class="text-xs font-semibold text-[var(--ui-secondary)]">Platzhalter ausfüllen</div>
                                                                                    @for($i = 1; $i <= $whatsappTemplatePreview['variables_count']; $i++)
                                                                                        <div class="flex items-center gap-2">
                                                                                            <span class="text-xs text-[var(--ui-muted)] font-mono w-10 flex-shrink-0">{{"{{" . $i . "}}"}}</span>
                                                                                            <input
                                                                                                type="text"
                                                                                                wire:model.live="whatsappTemplateVariables.{{ $i }}"
                                                                                                class="flex-1 px-3 py-1.5 border border-[var(--ui-border)] rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                                                                                                placeholder="Wert für Variable {{ $i }}…"
                                                                                            />
                                                                                        </div>
                                                                                    @endfor
                                                                                </div>
                                                                            @endif
                                                                        </div>

                                                                        {{-- Send template button --}}
                                                                        <div class="flex justify-end">
                                                                            <x-ui-button
                                                                                variant="primary"
                                                                                size="md"
                                                                                wire:click="sendWhatsAppTemplate"
                                                                                wire:loading.attr="disabled"
                                                                                wire:loading.class="animate-pulse"
                                                                                wire:target="sendWhatsAppTemplate"
                                                                                class="h-10"
                                                                            >
                                                                                <span wire:loading.remove wire:target="sendWhatsAppTemplate">Template senden</span>
                                                                                <span wire:loading wire:target="sendWhatsAppTemplate">Sende…</span>
                                                                            </x-ui-button>
                                                                        </div>
                                                                    @endif
                                                                @else
                                                                    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] px-3 py-2">
                                                                        <div class="text-xs text-[var(--ui-muted)]">
                                                                            Keine genehmigten Templates für diesen WhatsApp-Kanal verfügbar.
                                                                            Bitte erstelle und genehmige Templates in der Meta Business Suite.
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            @endif

                                                            @error('whatsappCompose.body')
                                                                <div class="mt-1 text-sm text-[color:var(--ui-danger)]">{{ $message }}</div>
                                                            @enderror
                                                            @if($whatsappMessage)
                                                                <div class="mt-1 text-sm text-[var(--ui-secondary)]">{{ $whatsappMessage }}</div>
                                                            @endif
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
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <x-ui-input-select
                                            name="newChannel.type"
                                            label="Typ"
                                            :options="['email' => 'E-Mail', 'whatsapp' => 'WhatsApp']"
                                            :nullable="false"
                                            displayMode="dropdown"
                                            wire:model.live="newChannel.type"
                                        />
                                        @if($newChannel['type'] === 'email')
                                            <x-ui-input-select
                                                name="newChannel.provider"
                                                label="Provider"
                                                :options="['postmark' => 'Postmark']"
                                                :nullable="false"
                                                displayMode="dropdown"
                                                wire:model.defer="newChannel.provider"
                                                :disabled="true"
                                            />
                                        @else
                                            <x-ui-input-select
                                                name="newChannel.provider_wa"
                                                label="Provider"
                                                :options="['whatsapp_meta' => 'Meta (WhatsApp Business)']"
                                                :nullable="false"
                                                displayMode="dropdown"
                                                :disabled="true"
                                            />
                                        @endif
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

                                    {{-- Email-specific fields --}}
                                    @if($newChannel['type'] === 'email')
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
                                                            Die Domain wird per Select gewählt (nur aus „Connections").
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
                                                        Keine Domains hinterlegt (bitte erst in „Connections" anlegen)
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
                                            >E-Mail Kanal anlegen</x-ui-button>
                                        </div>

                                        <div class="mt-2 text-xs text-[var(--ui-muted)]">
                                            Hinweis: Der Kanal wird der Postmark-Connection zugeordnet und am Root-Team gespeichert.
                                        </div>
                                    @endif

                                    {{-- WhatsApp-specific fields --}}
                                    @if($newChannel['type'] === 'whatsapp')
                                        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div>
                                                <x-ui-input-select
                                                    name="newChannel.whatsapp_account_id"
                                                    label="WhatsApp Account"
                                                    :options="$availableWhatsAppAccounts"
                                                    optionValue="id"
                                                    optionLabel="label"
                                                    :nullable="true"
                                                    nullLabel="(Account wählen)"
                                                    displayMode="dropdown"
                                                    wire:model.defer="newChannel.whatsapp_account_id"
                                                    :disabled="empty($availableWhatsAppAccounts)"
                                                />
                                                @if(empty($availableWhatsAppAccounts))
                                                    <div class="mt-2 text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                                        Keine WhatsApp Accounts verfügbar. Bitte verbinde zuerst dein Meta-Konto unter Integrationen.
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <x-ui-input-text
                                                    name="newChannel.name"
                                                    label="Name (optional)"
                                                    placeholder="z.B. Support WhatsApp"
                                                    wire:model.defer="newChannel.name"
                                                />
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-end">
                                            <x-ui-button
                                                variant="primary"
                                                size="sm"
                                                wire:click="createChannel"
                                                :disabled="empty($availableWhatsAppAccounts)"
                                                wire:loading.attr="disabled"
                                            >WhatsApp Kanal anlegen</x-ui-button>
                                        </div>

                                        <div class="mt-2 text-xs text-[var(--ui-muted)]">
                                            Hinweis: Du kannst nur WhatsApp Accounts verwenden, für die du Inhaber bist oder eine Freigabe hast (Integrationen → Freigaben).
                                        </div>
                                    @endif
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
                                                        @if($c['type'] === 'whatsapp')
                                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-green-50 text-green-700 border border-green-200">
                                                                WhatsApp
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                                                E-Mail
                                                            </span>
                                                        @endif
                                                        <div class="text-sm font-semibold text-[var(--ui-secondary)] truncate">
                                                            {{ $c['sender_identifier'] }}
                                                        </div>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/60">
                                                            {{ $c['visibility'] === 'team' ? 'teamweit' : 'privat' }}
                                                        </span>
                                                        @if(!$c['is_active'])
                                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
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

                    {{-- Debug WhatsApp Tab --}}
                    <div x-show="tab==='debug-whatsapp'" x-cloak class="w-full h-full min-h-0">
                        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm ring-1 ring-[var(--ui-border)]/30">
                            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-red-600">Debug WhatsApp</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">
                                        Rohdaten aus der Datenbank (ohne Filter, ohne Berechtigungsprüfung)
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <x-ui-button
                                        variant="muted-outline"
                                        size="sm"
                                        wire:click="loadDebugWhatsApp"
                                    >Aktualisieren</x-ui-button>
                                </div>
                            </div>

                            <div class="p-4 flex-1 min-h-0 overflow-y-auto space-y-6">
                                {{-- Debug Info --}}
                                <div class="bg-gray-100 p-3 rounded text-xs font-mono">
                                    <div class="font-bold mb-1">Context:</div>
                                    @foreach($debugInfo as $key => $val)
                                        <div>{{ $key }}: {{ $val ?? 'NULL' }}</div>
                                    @endforeach
                                </div>

                                {{-- IntegrationsWhatsAppAccount --}}
                                <div>
                                    <h4 class="font-semibold text-sm mb-2">IntegrationsWhatsAppAccount ({{ count($debugWhatsAppAccounts) }})</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs border border-[var(--ui-border)]/60">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">ID</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Phone</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Title</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Active</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Connection</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Owner User</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Owner Team</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($debugWhatsAppAccounts as $a)
                                                    <tr>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['phone_number'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['title'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['active'] ? 'Yes' : 'No' }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['connection_id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['owner_user_id'] ?? '-' }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $a['owner_team_id'] ?? '-' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="7" class="border border-[var(--ui-border)]/60 px-2 py-1 text-gray-500">Keine Accounts</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- CommsChannel --}}
                                <div>
                                    <h4 class="font-semibold text-sm mb-2">CommsChannel type=whatsapp ({{ count($debugWhatsAppChannels) }})</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs border border-[var(--ui-border)]/60">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">ID</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Team</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Sender</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Name</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Visibility</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Active</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Meta</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($debugWhatsAppChannels as $c)
                                                    <tr>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $c['id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $c['team_id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $c['sender_identifier'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $c['name'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $c['visibility'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $c['is_active'] ? 'Yes' : 'No' }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1 max-w-xs truncate">{{ json_encode($c['meta']) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="7" class="border border-[var(--ui-border)]/60 px-2 py-1 text-gray-500">Keine Channels</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- CommsWhatsAppThread --}}
                                <div>
                                    <h4 class="font-semibold text-sm mb-2">CommsWhatsAppThread ({{ count($debugWhatsAppThreads) }})</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-xs border border-[var(--ui-border)]/60">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">ID</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Channel</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Team</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Remote Phone</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Messages</th>
                                                    <th class="border border-[var(--ui-border)]/60 px-2 py-1 text-left">Updated</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($debugWhatsAppThreads as $t)
                                                    <tr>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $t['id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $t['channel_id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $t['team_id'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $t['remote_phone'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $t['messages_count'] }}</td>
                                                        <td class="border border-[var(--ui-border)]/60 px-2 py-1">{{ $t['updated_at'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="6" class="border border-[var(--ui-border)]/60 px-2 py-1 text-gray-500">Keine Threads</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
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

