<div x-data="{ tab: 'chat' }"
     @simple-playground:set-tab.window="tab = ($event.detail && $event.detail.tab) ? $event.detail.tab : tab"
     class="w-full h-full min-h-0 overflow-hidden flex flex-col" style="width:100%;">
    {{-- keep ids for JS compatibility (hidden) --}}
    <div id="rtTokensTotal" class="hidden">—</div>
    <div id="rtCostIn" class="hidden">—</div>
    <div id="rtCostCached" class="hidden">—</div>
    <div id="rtCostOut" class="hidden">—</div>
    <div id="rtUsageModel" class="hidden"></div>
    <div id="rtCostNote" class="hidden"></div>
    <div id="pgContextLabel" class="hidden">—</div>
    <div id="rtStatus" class="hidden">idle</div>
    <input id="pgActiveThreadId" type="hidden" value="{{ $activeThreadId ?? '' }}" />

    <div class="w-full flex-1 min-h-0 overflow-hidden p-4 bg-[var(--ui-bg)]" style="width:100%;">
    {{-- Chat Tab --}}
    <div x-show="tab==='chat'" class="w-full h-full min-h-0" x-cloak>
        <div class="h-full min-h-0 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm ring-1 ring-[var(--ui-border)]/30">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] flex-shrink-0">Chat</div>
                    {{-- Thread Tabs --}}
                    <div class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto">
                        @if(isset($threads) && $threads->count() > 0)
                            @foreach($threads as $t)
                                <div
                                    wire:key="thread-tab-{{ $t->id }}"
                                    class="relative flex-shrink-0 flex items-center"
                                    x-data="{ editing: false, title: '{{ addslashes($t->title) }}' }"
                                >
                                    <button
                                        type="button"
                                        wire:click="$set('activeThreadId', {{ $t->id }})"
                                        x-show="!editing"
                                        class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1 {{ ($activeThreadId ?? null) == $t->id ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]' : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]' }}"
                                    >
                                        <span class="inline-flex items-center gap-1" x-text="title"></span>
                                        <span data-thread-busy="{{ $t->id }}" class="hidden ml-1 w-2 h-2 rounded-full bg-[var(--ui-primary)] animate-pulse"></span>
                                        <svg 
                                            @click.stop="editing = true; $nextTick(() => $refs.input?.focus())"
                                            class="w-3 h-3 {{ ($activeThreadId ?? null) == $t->id ? 'text-white/70 hover:text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }} cursor-pointer"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <input
                                        x-ref="input"
                                        x-show="editing"
                                        x-model="title"
                                        @keydown.enter.stop="$wire.updateThreadTitle({{ $t->id }}, title); editing = false"
                                        @keydown.escape.stop="editing = false; title = '{{ addslashes($t->title) }}'"
                                        @blur="$wire.updateThreadTitle({{ $t->id }}, title); editing = false"
                                        class="px-2 py-1 rounded text-[11px] border border-[var(--ui-primary)] bg-[var(--ui-bg)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                        style="display: none; min-width: 80px; max-width: 200px;"
                                    />
                                </div>
                            @endforeach
                        @endif
                        <button
                            type="button"
                            wire:click="createThread"
                            class="px-2 py-1 rounded text-[11px] border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] flex-shrink-0"
                            title="Neuen Thread anlegen"
                        >
                            +
                        </button>
                    </div>
                </div>
                {{-- Usage moved to footer (Total + Request). Keep header clean. --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button
                        type="button"
                        x-data
                        @click.prevent="if (confirm('Aktiven Thread wirklich löschen?')) { $wire.deleteActiveThread() }"
                        class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:underline"
                        title="Aktiven Thread löschen"
                    >
                        Thread löschen
                    </button>
                </div>
            </div>
            <div class="flex-1 min-h-0 overflow-hidden w-full">
                <div class="w-full h-full min-h-0 grid grid-cols-4 gap-5 px-4 py-4 overflow-hidden min-w-0" style="width:100%; max-width:100%;">

                    {{-- Left: Chat (3/4 width) --}}
                    <div class="col-span-3 min-h-0 min-w-0 flex flex-col overflow-hidden">
                        <div class="flex-1 min-h-0 bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
                            <div class="flex-1 min-h-0 overflow-y-auto p-4 flex flex-col-reverse" id="simpleChatScroll">
                                {{-- Single wrapper for column-reverse auto-scroll trick --}}
                                <div class="space-y-4">
                                @php
                                    $msgs = collect($activeThreadMessages ?? [])
                                        ->filter(fn($m) => in_array($m->role, ['user', 'assistant'], true))
                                        ->values();
                                    $initialMessages = $msgs
                                        ->map(fn($m) => ['role' => $m->role, 'content' => $m->content, 'attachments' => $m->meta['attachments'] ?? []])
                                        ->values();
                                @endphp
                                <script>
                                  window.__simpleInitialMessages = @json($initialMessages);
                                </script>
                                <div id="chatList" class="space-y-4 min-w-0" wire:key="chat-list-{{ $activeThreadId ?? 'none' }}">
                                    @foreach($msgs as $m)
                                        @php
                                            $attachments = collect([]);
                                            if (!empty($m->meta['attachments'])) {
                                                $attachments = \Platform\Core\Models\ContextFile::whereIn('id', $m->meta['attachments'])
                                                    ->with('variants')
                                                    ->get();
                                            }
                                        @endphp
                                        <div wire:key="chat-msg-{{ $m->id }}" class="flex {{ $m->role === 'user' ? 'justify-end' : 'justify-start' }}">
                                            <div class="max-w-4xl rounded-lg p-3 break-words overflow-hidden {{ $m->role === 'user' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-surface)] border border-[var(--ui-border)]' }}">
                                                <div class="text-sm font-semibold mb-1">{{ $m->role === 'user' ? 'Du' : 'Assistant' }}</div>
                                                {{-- Show attachments if present --}}
                                                @if($attachments->count() > 0)
                                                    <div class="mb-2 flex flex-wrap gap-2">
                                                        @foreach($attachments as $attachment)
                                                            @if($attachment->isImage())
                                                                <a href="{{ $attachment->url }}" target="_blank" class="block group relative">
                                                                    <img
                                                                        src="{{ $attachment->thumbnail?->url ?? $attachment->url }}"
                                                                        alt="{{ $attachment->original_name }}"
                                                                        class="w-20 h-20 object-cover rounded border {{ $m->role === 'user' ? 'border-white/30' : 'border-[var(--ui-border)]' }} hover:opacity-80 transition-opacity"
                                                                        title="{{ $attachment->original_name }}"
                                                                    />
                                                                </a>
                                                            @else
                                                                <a href="{{ $attachment->url }}" target="_blank" class="flex items-center gap-2 px-2 py-1 rounded {{ $m->role === 'user' ? 'bg-white/20 hover:bg-white/30' : 'bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted-10)]' }} transition-colors">
                                                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                    </svg>
                                                                    <span class="text-xs truncate max-w-[100px]" title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</span>
                                                                </a>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="whitespace-pre-wrap break-words">{{ $m->content }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Streaming output lives outside Livewire diffing to prevent wipes. --}}
                                <div id="pgStreamingSlot" class="space-y-4 min-w-0" wire:ignore></div>

                                {{-- Ephemeral (UI-only) notes + streaming meta (JS renders here; not persisted, not sent as chat_history). --}}
                                <div id="pgEphemeralNotes" class="space-y-3"></div>

                                {{-- Streaming happens as live chat messages (JS). --}}
                                <div id="chatEmpty" class="text-sm text-[var(--ui-muted)]" style="{{ $msgs->count() ? 'display:none;' : '' }}">
                                    <div class="font-semibold text-[var(--ui-secondary)]">Start</div>
                                    <div class="mt-1">Schreib eine Nachricht (z. B. „Liste meine Teams“ oder „Welche Tools kann ich im OKR-Modul nutzen?“).</div>
                                    <div class="mt-3 text-xs">
                                        Tipp: Links kannst du Models per Drag&Drop wählen, und direkt neben dem Input pro Request wechseln.
                                    </div>
                                </div>

                                </div>{{-- end wrapper for column-reverse --}}
                            </div>
                            <div class="border-t border-[var(--ui-border)]/60 p-3 flex-shrink-0 bg-[var(--ui-surface)]">
                                {{-- Uploaded attachments preview --}}
                                <div id="pgAttachmentsPreview" class="mb-2 flex flex-wrap gap-2" style="display: none;">
                                    {{-- Attachments will be rendered by JavaScript --}}
                                </div>

                                <form id="chatForm" class="flex gap-2 items-center" method="post" action="javascript:void(0)" onsubmit="return false;">
                                    <div class="w-56">
                                        <x-ui-input-select
                                            name="modelSelect"
                                            id="modelSelect"
                                            :options="$modelOptions ?? []"
                                            :nullable="false"
                                            size="md"
                                            :value="$activeThreadModel ?? $defaultModelId ?? 'gpt-5.2'"
                                            class="w-full h-10"
                                        />
                                    </div>
                                    {{-- File upload button --}}
                                    <div class="relative flex-shrink-0">
                                        <input
                                            type="file"
                                            id="pgFileInput"
                                            multiple
                                            accept="image/*,.pdf,.doc,.docx,.txt,.md,.json,.xml,.csv"
                                            class="hidden"
                                        />
                                        <button
                                            type="button"
                                            id="pgFileUploadBtn"
                                            class="p-2 h-10 w-10 border border-[var(--ui-border)] rounded-lg hover:bg-[var(--ui-muted-5)] flex items-center justify-center text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                                            title="Dateien anhängen"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                        </button>
                                        {{-- Upload progress indicator --}}
                                        <div id="pgUploadProgress" class="hidden absolute -top-1 -right-1 w-4 h-4">
                                            <svg class="animate-spin w-4 h-4 text-[var(--ui-primary)]" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </div>
                                        {{-- Attachment count badge --}}
                                        <div id="pgAttachmentCount" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-[var(--ui-primary)] text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                                            0
                                        </div>
                                    </div>
                                    <textarea
                                        id="chatInput"
                                        rows="1"
                                        class="flex-1 w-full px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] resize-none"
                                        placeholder="Nachricht eingeben…"
                                        autocomplete="off"
                                        style="min-height: 44px; max-height: 132px; overflow-y: auto;"
                                    ></textarea>
                                    <button id="chatSend" type="submit" class="px-6 py-2 h-10 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 flex items-center gap-2">
                                        <span>Senden</span>
                                    </button>
                                </form>
                                {{-- Context-Info unterhalb des Input-Bereichs (Livewire-gesteuert) --}}
                                @if($this->hasContext)
                                    <div class="flex items-center gap-2 px-2 py-1.5 text-xs text-[var(--ui-muted)]">
                                        <label class="flex items-center gap-1.5 cursor-pointer select-none">
                                            <input type="checkbox" wire:model.live="sendContext" class="w-3.5 h-3.5 rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)] cursor-pointer" />
                                            <span>Kontext mitsenden:</span>
                                        </label>
                                        <span class="opacity-70">{{ $this->contextType }}:</span>
                                        <span class="font-medium text-[var(--ui-secondary)] truncate max-w-[300px]">{{ $this->contextTitle }}</span>
                                    </div>
                                    {{-- Context-Files werden jetzt per Tool abgerufen (core.context.files.GET) --}}
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Right: Realtime / Debug (1/4 width) --}}
                    <div class="col-span-1 min-h-0 min-w-0 bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm overflow-x-hidden">
                        <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                            <div class="text-xs font-semibold text-[var(--ui-secondary)]">Tools</div>
                            <div class="flex items-center gap-3">
                                <button id="rtCopyDebug" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Copy Debug</button>
                                <button id="realtimeClear" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Clear</button>
                            </div>
                        </div>

                        <div class="p-4 space-y-4 flex-1 min-h-0 overflow-y-auto min-w-0" wire:ignore>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Tool Calls (alle im Request)</div>
                                <div id="rtToolCalls" class="space-y-2 max-h-[60vh] overflow-y-auto"></div>
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Stream (raw deltas)</div>
                                <pre id="rtStreamLog" class="text-[10px] whitespace-pre-wrap text-[var(--ui-muted)] max-h-[40vh] overflow-y-auto bg-[var(--ui-bg)] border border-[var(--ui-border)]/60 rounded p-2"></pre>
                            </div>
                            <div id="rtCopyStatus" class="text-[10px] text-[var(--ui-muted)]"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer: full width (spans Chat + Debug) --}}
            <div class="px-4 py-3 border-t border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0 gap-3">
                {{-- Left: Tokens + Prices (left-aligned) --}}
                <div class="flex flex-wrap items-center justify-start gap-x-3 gap-y-2 min-w-0">
                    {{-- Hidden token nodes (used by JS updates) --}}
                    <div class="hidden" id="rtTokensInTotal">—</div>
                    <div class="hidden" id="rtTokensOutTotal">—</div>
                    <div class="hidden" id="rtTokensExtraTotal">—</div>
                    <div class="hidden" id="rtTokensIn">—</div>
                    <div class="hidden" id="rtTokensOut">—</div>
                    <div class="hidden" id="rtTokensExtra">—</div>

                    <div class="flex items-center gap-1 px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[10px] text-[var(--ui-muted)]">Total tok:</span>
                        <span id="rtTokensGrand" class="text-[10px] font-semibold text-[var(--ui-secondary)]">
                            @if(isset($activeThread) && $activeThread)
                                {{ number_format((int)($activeThread->total_tokens_in ?? 0) + (int)($activeThread->total_tokens_out ?? 0)) }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[10px] text-[var(--ui-muted)]">Total €:</span>
                        <span id="rtCostTotal" class="text-[10px] font-semibold text-[var(--ui-secondary)]">
                            @if(isset($activeThread) && $activeThread)
                                {{ number_format((float)($activeThread->total_cost ?? 0), 4) }} {{ $activeThread->pricing_currency ?? 'USD' }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-bg)]/50">
                        <span class="text-[10px] text-[var(--ui-muted)]">Req tok:</span>
                        <span id="rtTokensReq" class="text-[10px] font-semibold text-[var(--ui-secondary)]">—</span>
                    </div>
                    <div class="flex items-center gap-1 px-2 py-1 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-bg)]/50">
                        <span class="text-[10px] text-[var(--ui-muted)]">Req €:</span>
                        <span id="rtCostRequest" class="text-[10px] font-semibold text-[var(--ui-secondary)]">—</span>
                    </div>
                </div>

                {{-- Right: Event, Laufzeit, Stop (right-aligned, in that order) --}}
                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2 min-w-0">
                    <div class="flex items-center gap-1 px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)] min-w-0 max-w-64">
                        <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0">Event:</span>
                        <span id="pgFooterEventText" class="text-[10px] font-mono text-[var(--ui-secondary)] truncate">—</span>
                    </div>
                    <div id="pgFooterSecondsWrap" class="hidden flex items-center gap-1 px-2 py-1 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[10px] text-[var(--ui-muted)]">t:</span>
                        <span id="pgFooterSeconds" class="text-[10px] font-mono text-[var(--ui-secondary)]">0s</span>
                    </div>
                    <div id="pgFooterBusy" class="hidden flex items-center flex-shrink-0">
                        <button
                            type="button"
                            id="pgStopBtn"
                            class="px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-muted)] text-[10px] font-semibold hover:text-red-600 hover:border-red-200 hover:bg-red-50 disabled:opacity-60 disabled:cursor-not-allowed"
                            title="Stop"
                            disabled
                        >
                            Stop
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Settings Tab --}}
    <div x-show="tab==='settings'" class="w-full h-full min-h-0" x-cloak>
        <div class="h-full min-h-0 border border-[var(--ui-border)] rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Settings</div>
            </div>
            <div class="p-4 flex-1 min-h-0 overflow-y-auto text-sm text-[var(--ui-muted)] space-y-3">
                <div><span class="font-semibold text-[var(--ui-secondary)]">Model:</span> links (Drag&Drop) oder direkt neben dem Chat-Input auswählen.</div>
                <div><span class="font-semibold text-[var(--ui-secondary)]">Live:</span> Streaming-Ausgabe + Thinking/Reasoning siehst du direkt im Chat.</div>
                <div><span class="font-semibold text-[var(--ui-secondary)]">Tools:</span> rechts siehst du den Verlauf der Tool Calls; Events werden nur im Footer angezeigt.</div>
            </div>
        </div>
    </div>

    {{-- Models Tab --}}
    <div x-show="tab==='models'" class="w-full h-full min-h-0" x-cloak>
        <div class="h-full min-h-0 border border-[var(--ui-border)] rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Model settings</div>
                <div class="text-xs text-[var(--ui-muted)]">
                    Quelle: <span class="text-[var(--ui-secondary)]">core_ai_models</span>
                </div>
            </div>
            <div class="p-4 flex-1 min-h-0 overflow-auto">
                @if(!empty($pricingSaveMessage))
                    <div class="mb-3 text-xs px-3 py-2 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-[var(--ui-secondary)]">
                        {{ $pricingSaveMessage }}
                    </div>
                @endif

                <div class="mb-4 text-xs text-[var(--ui-muted)] flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        Hier pflegst du Limits, Pricing und Param-Support (DB ist die Source of Truth).
                        @if(!($canManageAiModels ?? false))
                            <span class="ml-2 text-[var(--ui-danger)] font-semibold">Read-only (nur Root/Eltern-Team Owner darf speichern).</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($hasTeamModelRecords ?? false)
                            <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-warning-10)] text-[var(--ui-warning)]">Team-Filter aktiv</span>
                            @if($canManageAiModels ?? false)
                                <button
                                    type="button"
                                    class="text-[10px] px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] hover:bg-[var(--ui-muted-5)] text-[var(--ui-muted)]"
                                    wire:click="resetTeamModels"
                                    title="Team-Filter zurücksetzen: alle Modelle wieder verfügbar"
                                >Reset Team-Filter</button>
                            @endif
                        @else
                            <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">Alle Modelle verfügbar</span>
                        @endif
                        <button id="modelsReload" type="button" class="text-xs text-[var(--ui-muted)] hover:underline flex-shrink-0">Reload</button>
                    </div>
                </div>

                @if(($coreAiModels ?? collect())->count() === 0)
                    <div class="text-sm text-[var(--ui-muted)]">
                        Noch keine Modelle in <code class="px-1 py-0.5 rounded bg-[var(--ui-muted-5)]">core_ai_models</code>.
                        <div class="mt-2 text-xs">
                            Nächster Schritt: <code class="px-1 py-0.5 rounded bg-[var(--ui-muted-5)]">php artisan core:ai-models:sync</code>
                        </div>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="text-[var(--ui-muted)]">
                                <tr class="border-b border-[var(--ui-border)]/60">
                                    <th class="text-left py-2 pr-4 font-semibold">Provider</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Model</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Limits</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Pricing</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Param Support</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Default</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Status</th>
                                    @if($canManageAiModels ?? false)
                                        <th class="text-left py-2 pr-0 font-semibold">Team</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="text-[var(--ui-secondary)]">
                                @foreach(($coreAiModels ?? collect()) as $m)
                                    @php
                                        $p = $m->provider?->key ?? '—';
                                        $isDefault = (int)($m->provider?->default_model_id ?? 0) === (int)$m->id;
                                        $canEdit = (bool)($canManageAiModels ?? false);
                                    @endphp
                                    <tr class="border-b border-[var(--ui-border)]/40">
                                        <td class="py-2 pr-4">{{ $p }}</td>
                                        <td class="py-2 pr-4">
                                            <div class="font-semibold">{{ $m->name }}</div>
                                            <div class="text-[10px] text-[var(--ui-muted)]">{{ $m->model_id }}</div>
                                        </td>
                                        <td class="py-2 pr-4 align-top">
                                            <div class="grid grid-cols-2 gap-2 min-w-[210px]">
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Context</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50"
                                                        wire:model.defer="modelEdits.{{ $m->id }}.context_window"
                                                        inputmode="numeric"
                                                        @disabled(!$canEdit)
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Max out</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50"
                                                        wire:model.defer="modelEdits.{{ $m->id }}.max_output_tokens"
                                                        inputmode="numeric"
                                                        @disabled(!$canEdit)
                                                    />
                                                </div>
                                                <div class="col-span-2">
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Cutoff</div>
                                                    <div class="text-[11px] text-[var(--ui-secondary)]">
                                                        {{ $m->knowledge_cutoff_date ? $m->knowledge_cutoff_date->format('Y-m-d') : '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-4 align-top">
                                            <div class="grid grid-cols-4 gap-2 items-end min-w-[420px]">
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Währung</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50"
                                                        wire:model.defer="modelEdits.{{ $m->id }}.pricing_currency"
                                                        maxlength="3"
                                                        @disabled(!$canEdit)
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Input / 1M</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50"
                                                        wire:model.defer="modelEdits.{{ $m->id }}.price_input_per_1m"
                                                        inputmode="decimal"
                                                        @disabled(!$canEdit)
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Cached / 1M</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50"
                                                        wire:model.defer="modelEdits.{{ $m->id }}.price_cached_input_per_1m"
                                                        inputmode="decimal"
                                                        @disabled(!$canEdit)
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Output / 1M</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50"
                                                        wire:model.defer="modelEdits.{{ $m->id }}.price_output_per_1m"
                                                        inputmode="decimal"
                                                        @disabled(!$canEdit)
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-4 align-top">
                                            <div class="grid grid-cols-2 gap-2 min-w-[240px]">
                                                @php
                                                    $tri = ['' => '—', '1' => 'ja', '0' => 'nein'];
                                                @endphp
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">temperature</div>
                                                    <select class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50" wire:model.defer="modelEdits.{{ $m->id }}.supports_temperature" @disabled(!$canEdit)>
                                                        @foreach($tri as $k => $lbl)
                                                            <option value="{{ $k }}">{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">top_p</div>
                                                    <select class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50" wire:model.defer="modelEdits.{{ $m->id }}.supports_top_p" @disabled(!$canEdit)>
                                                        @foreach($tri as $k => $lbl)
                                                            <option value="{{ $k }}">{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">presence_penalty</div>
                                                    <select class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50" wire:model.defer="modelEdits.{{ $m->id }}.supports_presence_penalty" @disabled(!$canEdit)>
                                                        @foreach($tri as $k => $lbl)
                                                            <option value="{{ $k }}">{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">frequency_penalty</div>
                                                    <select class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs disabled:opacity-50" wire:model.defer="modelEdits.{{ $m->id }}.supports_frequency_penalty" @disabled(!$canEdit)>
                                                        @foreach($tri as $k => $lbl)
                                                            <option value="{{ $k }}">{{ $lbl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-span-2 flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-md bg-[var(--ui-primary)] text-white text-xs hover:bg-opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
                                                        wire:click="saveModelSettings({{ $m->id }})"
                                                        @disabled(!$canEdit)
                                                    >
                                                        Speichern
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <button
                                                type="button"
                                                class="text-[10px] px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] hover:bg-[var(--ui-muted-5)]"
                                                wire:click="setDefaultModel({{ $m->id }})"
                                                title="Als Default setzen"
                                            >
                                                {{ $isDefault ? '✅ default' : 'set default' }}
                                            </button>
                                        </td>
                                        <td class="py-2 pr-4">
                                            @if($m->is_deprecated)
                                                <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-danger-5)] text-[var(--ui-danger)]">deprecated</span>
                                            @elseif(!$m->is_active)
                                                <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">inactive</span>
                                            @else
                                                <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-primary-10)] text-[var(--ui-primary)]">active</span>
                                            @endif
                                        </td>
                                        @if($canManageAiModels ?? false)
                                            <td class="py-2 pr-0">
                                                @php
                                                    $teamEnabled = $this->teamModelToggles[(int)$m->id] ?? true;
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="text-[10px] px-2 py-1 rounded border border-[var(--ui-border)] {{ $teamEnabled ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'bg-[var(--ui-danger-5)] text-[var(--ui-danger)]' }}"
                                                    wire:click="toggleTeamModel({{ $m->id }})"
                                                    title="{{ $teamEnabled ? 'Modell für Team deaktivieren' : 'Modell für Team aktivieren' }}"
                                                >
                                                    {{ $teamEnabled ? 'aktiv' : 'gesperrt' }}
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
    </div>

    <script>
      (() => {
        // Blade variables (set before script execution)
        const defaultModelId = @json($defaultModelId ?? 'gpt-5.2');
        const activeThreadId = @json($activeThreadId ?? null);
        const activeThreadModel = @json($activeThreadModel ?? null);
        const livewireComponentId = '{{ $this->getId() }}';
        
        const boot = () => {
        const url = window.__simpleStreamUrl;
        const modelsUrl = window.__simpleModelsUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const serverDefaultModel = 'gpt-5.2';

        // File upload URLs
        window.__simplePlaygroundUploadUrl = '{{ route("core.playground.upload") }}';
        window.__simplePlaygroundDeleteUrl = '{{ route("core.playground.attachment.delete", ["id" => "__ID__"]) }}'.replace('/__ID__', '');

        const ctx = window.__simplePlaygroundContext || null;
        const ctxLabel = document.getElementById('pgContextLabel');
        if (ctxLabel) {
          const bits = [];
          if (ctx?.source_module) bits.push(String(ctx.source_module));
          if (ctx?.source_route) bits.push(String(ctx.source_route));
          ctxLabel.textContent = bits.length ? bits.join(' · ') : '—';
        }

        const modelsList = document.getElementById('modelsList');
        let modelSelect = document.getElementById('modelSelect');
        const modelDropZone = document.getElementById('modelDropZone');
        const selectedModelLabel = document.getElementById('selectedModelLabel');
        const modelsReload = document.getElementById('modelsReload');

        // NOTE: Livewire re-renders these nodes. Keep them as "let" and refresh on demand,
        // otherwise we end up with stale references after a thread switch.
        let chatList = document.getElementById('chatList');
        let chatScroll = document.getElementById('simpleChatScroll');
        let form = document.getElementById('chatForm');
        let input = document.getElementById('chatInput');
        let sendBtn = document.getElementById('chatSend');

        const refreshDomRefs = () => {
          chatList = document.getElementById('chatList');
          chatScroll = document.getElementById('simpleChatScroll');
          form = document.getElementById('chatForm');
          input = document.getElementById('chatInput');
          sendBtn = document.getElementById('chatSend');
        };

        // Idempotent binding (modal exists globally; avoid duplicate listeners).
        // IMPORTANT: even when already bound, we still refresh models so the UI is usable right after opening.
        refreshDomRefs();
        const alreadyBound = (form?.dataset?.simplePlaygroundBound === '1');
        if (!form) return;
        if (!alreadyBound) form.dataset.simplePlaygroundBound = '1';

        const realtimeClear = document.getElementById('realtimeClear');
        // Streaming is rendered as ephemeral chat messages; these legacy nodes may not exist anymore.
        const rtAssistant = document.getElementById('rtAssistant');
        const rtReasoning = document.getElementById('rtReasoning');
        const rtThinking = document.getElementById('rtThinking');
        const rtEvents = document.getElementById('rtEvents'); // optional (we don't render it in the modal anymore)
        const rtStatus = document.getElementById('rtStatus');
        const rtVerboseEl = document.getElementById('rtVerbose'); // optional
        const rtDebugDump = document.getElementById('rtDebugDump'); // optional (hidden/removed in modal)
        const rtCopyDebug = document.getElementById('rtCopyDebug');
        const rtCopyStatus = document.getElementById('rtCopyStatus');
        const rtTokensIn = document.getElementById('rtTokensIn');
        const rtTokensOut = document.getElementById('rtTokensOut');
        const rtTokensTotal = document.getElementById('rtTokensTotal');
        const rtTokensExtra = document.getElementById('rtTokensExtra');
        const rtTokensInTotal = document.getElementById('rtTokensInTotal');
        const rtTokensOutTotal = document.getElementById('rtTokensOutTotal');
        const rtTokensExtraTotal = document.getElementById('rtTokensExtraTotal');
        const rtUsageModel = document.getElementById('rtUsageModel');
        const rtCostIn = document.getElementById('rtCostIn');
        const rtCostCached = document.getElementById('rtCostCached');
        const rtCostOut = document.getElementById('rtCostOut');
        const rtCostTotal = document.getElementById('rtCostTotal');
        const rtStreamLog = document.getElementById('rtStreamLog');
        const rtCostRequest = document.getElementById('rtCostRequest');
        const rtCostNote = document.getElementById('rtCostNote');
        const rtToolCalls = document.getElementById('rtToolCalls');
        const rtEventCountEl = document.getElementById('rtEventCount');
        const rtEventReasonCountEl = document.getElementById('rtEventReasonCount');
        const rtEventThinkingCountEl = document.getElementById('rtEventThinkingCount');

        // Global per-thread state so modal close/open and thread switching does not reset anything.
        window.__simplePlaygroundThreadStore = window.__simplePlaygroundThreadStore || {};
        const getThreadState = (threadId) => {
          const key = String(threadId || 'none');
          if (!window.__simplePlaygroundThreadStore[key]) {
            window.__simplePlaygroundThreadStore[key] = {
              messages: [],
              continuation: null,
              inFlight: false,
              abortController: null,
              userAborted: false,
              live: { assistant: '', reasoning: '', thinking: '', status: 'idle' },
            };
          }
          return window.__simplePlaygroundThreadStore[key];
        };

        /** active thread id (from DOM) */
        let currentThreadId = null;
        /** active thread state (from global store) */
        let threadState = getThreadState('none');
        
        // Initialize with default model or thread model
        let selectedModel = activeThreadModel || defaultModelId;
        if (!selectedModel) {
          selectedModel = localStorage.getItem('simple.selectedModel') || '';
        }
        if (!selectedModel) {
          selectedModel = defaultModelId;
        }
        localStorage.setItem('simple.selectedModel', selectedModel);

        // Auto-grow textarea (adapted from comms modal)
        const autoGrow = (el, maxPx = 132) => {
          if (!el) return;
          el.style.height = 'auto';
          const next = Math.min(el.scrollHeight || 0, maxPx);
          el.style.height = (next > 0 ? next : 44) + 'px';
          el.style.overflowY = (el.scrollHeight > maxPx) ? 'auto' : 'hidden';
        };

        // Scroll to bottom helper
        const scrollToBottom = () => {
          const scroller = document.getElementById('simpleChatScroll');
          if (scroller) scroller.scrollTop = scroller.scrollHeight;
        };

        // MutationObserver for auto-scroll: watches for new content and scrolls
        let scrollObserver = null;
        const startScrollObserver = () => {
          if (scrollObserver) return;
          const scroller = document.getElementById('simpleChatScroll');
          if (!scroller) return;
          scrollObserver = new MutationObserver(() => {
            scroller.scrollTop = scroller.scrollHeight;
          });
          scrollObserver.observe(scroller, { childList: true, subtree: true, characterData: true });
        };
        const stopScrollObserver = () => {
          if (scrollObserver) {
            scrollObserver.disconnect();
            scrollObserver = null;
          }
        };

        // Backwards compatibility aliases
        const startScrollInterval = startScrollObserver;
        const stopScrollInterval = stopScrollObserver;
        const setupAutoScroll = () => {};
        
        // Helper: format numbers
        const formatNumber = (n) => {
          if (n == null || n === '' || Number.isNaN(n)) return '—';
          return new Intl.NumberFormat('de-DE').format(Number(n));
        };

        // Helper: format elapsed seconds as "Xm Ys" (e.g. 2m 05s) once >= 60s.
        const formatElapsed = (seconds) => {
          const s = Math.max(0, Number(seconds) || 0);
          if (s < 60) return `${Math.floor(s)}s`;
          const m = Math.floor(s / 60);
          const rs = Math.floor(s % 60);
          return `${m}m ${String(rs).padStart(2, '0')}s`;
        };

        // Helper: Trigger Livewire to reload messages from DB (for complete, error, etc.)
        const refreshLivewireMessages = () => {
          try {
            if (window.Livewire && typeof livewireComponentId !== 'undefined') {
              window.__simplePlaygroundShouldScrollAfterUpdate = true;
              window.Livewire.find(livewireComponentId).call('refreshMessages').catch(() => {});
            }
          } catch (_) {}
        };


        // Ephemeral UI notes (not stored, not sent to the model).
        const ensureEphemeralNotesRoot = () => {
          refreshDomRefs();
          return document.getElementById('pgEphemeralNotes') || null;
        };
        const renderEphemeralNote = (title, content, kind = 'info') => {
          const root = ensureEphemeralNotesRoot();
          if (!root) return null;
          const wrap = document.createElement('div');
          const border = (kind === 'warn') ? 'border-yellow-200' : (kind === 'error' ? 'border-red-200' : 'border-[var(--ui-border)]/60');
          const bg = (kind === 'warn') ? 'bg-yellow-50' : (kind === 'error' ? 'bg-red-50' : 'bg-[var(--ui-bg)]');
          wrap.className = `rounded-lg border ${border} ${bg} p-3`;
          wrap.innerHTML = `
            <div class="text-[11px] font-semibold text-[var(--ui-secondary)] flex items-center justify-between gap-2">
              <span>${String(title || 'Hinweis')}</span>
              <button type="button" class="text-[10px] text-[var(--ui-muted)] hover:underline" data-ephemeral-close>schließen</button>
            </div>
            <div class="mt-1 text-[11px] text-[var(--ui-secondary)] whitespace-pre-wrap" data-ephemeral-content></div>
          `;
          const c = wrap.querySelector('[data-ephemeral-content]');
          if (c) c.textContent = String(content || '');
          const close = wrap.querySelector('[data-ephemeral-close]');
          if (close) {
            close.addEventListener('click', (e) => {
              e.preventDefault();
              if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
            });
          }
          root.appendChild(wrap);
          // track per-thread so we can clean up on reset/complete
          try {
            threadState.ephemeralNoteEls = threadState.ephemeralNoteEls || [];
            threadState.ephemeralNoteEls.push(wrap);
          } catch (_) {}
          // Auto-scroll is handled by MutationObserver
          return wrap;
        };
        // Convenience for debugging from console
        window.simplePlaygroundNote = (text, title = 'Debug', kind = 'info') => renderEphemeralNote(title, text, kind);

        window.__simplePlaygroundStreamLog = window.__simplePlaygroundStreamLog || '';
        const appendStreamLog = (label, chunk) => {
          if (!rtStreamLog) return;
          const safeLabel = label ? `[${label}] ` : '';
          const text = `${safeLabel}${String(chunk || '')}`;
          const next = (window.__simplePlaygroundStreamLog || '') + text;
          const trimmed = next.length > 12000 ? next.slice(-12000) : next;
          window.__simplePlaygroundStreamLog = trimmed;
          rtStreamLog.textContent = trimmed;
          rtStreamLog.scrollTop = rtStreamLog.scrollHeight;
        };

        // Streaming assistant message: NOT shown in chat (only debug panel)
        // Final result is rendered by Livewire after complete
        const ensureStreamingAssistantMessage = () => null;
        const updateStreamingAssistantMessage = (text) => {};
        const removeStreamingAssistantMessage = () => {};

        // Render a message in the chat UI (used for temporary messages before Livewire re-renders)
        const renderMessage = (role, content, options = {}) => {
          refreshDomRefs();
          // Prefer rendering into pgStreamingSlot (wire:ignore) so Livewire diffing doesn't touch it.
          const targetEl = document.getElementById('pgStreamingSlot') || chatList;
          if (!targetEl) return null;

          const wrap = document.createElement('div');
          wrap.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'}`;
          wrap.dataset.temporary = '1'; // Mark as temporary so it can be cleaned up after Livewire re-render

          const bubbleClasses = role === 'user'
            ? 'bg-[var(--ui-primary)] text-white'
            : 'bg-[var(--ui-surface)] border border-[var(--ui-border)]';

          wrap.innerHTML = `
            <div class="max-w-4xl rounded-lg p-3 break-words overflow-hidden ${bubbleClasses}">
              <div class="text-sm font-semibold mb-1">${role === 'user' ? 'Du' : 'Assistant'}</div>
              <div class="whitespace-pre-wrap break-words" data-msg-content></div>
            </div>
          `;

          const contentEl = wrap.querySelector('[data-msg-content]');
          if (contentEl) contentEl.textContent = content || '';

          targetEl.appendChild(wrap);

          // Hide empty state
          const empty = document.getElementById('chatEmpty');
          if (empty) empty.style.display = 'none';
          // Note: overflow-anchor CSS handles auto-scroll
          return wrap;
        };

        // Turn the current streaming assistant bubble into a "final" bubble that will not be removed
        // by future streaming resets (we drop the streaming id).
        const finalizeStreamingAssistantMessage = (finalText) => {
          const el = document.getElementById('pgStreamingAssistantMsg');
          if (el) {
            try {
              const c = el.querySelector('[data-stream-content]');
              if (c) c.textContent = String(finalText || '');
              const s = el.querySelector('[data-stream-status]');
              if (s) s.textContent = '(fertig)';
              // Remove streaming id so removeStreamingAssistantMessage() won't delete it.
              el.removeAttribute('id');
              el.dataset.final = '1';
            } catch (_) {}
            return;
          }
          // Fallback: if no streaming bubble exists, render a final one into the streaming slot.
          try {
            const root = document.getElementById('pgStreamingSlot') || chatList;
            if (!root) return;
            const wrap = document.createElement('div');
            wrap.className = 'flex justify-start';
            wrap.dataset.final = '1';
            wrap.innerHTML = `
              <div class="max-w-4xl rounded-lg p-3 break-words overflow-hidden bg-[var(--ui-surface)] border border-[var(--ui-border)]">
                <div class="text-sm font-semibold mb-1 flex items-center gap-2">
                  <span>Assistant</span>
                  <span class="text-[10px] text-[var(--ui-muted)]">(fertig)</span>
                </div>
                <div class="whitespace-pre-wrap break-words" data-stream-content></div>
              </div>
            `;
            const c = wrap.querySelector('[data-stream-content]');
            if (c) c.textContent = String(finalText || '');
            root.appendChild(wrap);
            // Auto-scroll is handled by MutationObserver
          } catch (_) {}
        };

        // Streaming reasoning/thinking as ephemeral chat messages (not persisted, not sent as chat_history).
        const ensureStreamingMetaMessage = (kind) => {
          refreshDomRefs();
          const root = document.getElementById('pgStreamingSlot') || chatList;
          if (!root) return null;
          const id = kind === 'reasoning' ? 'pgStreamingReasoningMsg' : 'pgStreamingThinkingMsg';
          const label = kind === 'reasoning' ? 'Reasoning' : 'Thinking';
          let el = document.getElementById(id);
          if (el) return el;
          const wrap = document.createElement('div');
          wrap.id = id;
          wrap.className = 'flex justify-start';
          wrap.dataset.streaming = '1';
          wrap.innerHTML = `
            <div class="max-w-4xl rounded-lg p-3 break-words overflow-hidden bg-[var(--ui-bg)] border border-[var(--ui-border)]/60">
              <div class="text-[11px] font-semibold mb-1 flex items-center gap-2 text-[var(--ui-secondary)]">
                <span>${label}</span>
                <span class="text-[10px] text-[var(--ui-muted)] animate-pulse">(live)</span>
              </div>
              <div class="whitespace-pre-wrap break-words text-[11px] text-[var(--ui-secondary)]" data-stream-content></div>
            </div>
          `;
          root.appendChild(wrap);
          const empty = document.getElementById('chatEmpty');
          if (empty) empty.style.display = 'none';
          // Note: overflow-anchor CSS handles auto-scroll
          return wrap;
        };
        const updateStreamingMetaMessage = (kind, text) => {
          const el = ensureStreamingMetaMessage(kind);
          if (!el) return;
          const c = el.querySelector('[data-stream-content]');
          if (c) c.textContent = text || '';
          // Note: overflow-anchor CSS handles auto-scroll
        };
        const removeStreamingMetaMessages = () => {
          const a = document.getElementById('pgStreamingReasoningMsg');
          const b = document.getElementById('pgStreamingThinkingMsg');
          if (a && a.parentNode) a.parentNode.removeChild(a);
          if (b && b.parentNode) b.parentNode.removeChild(b);
        };

        const refreshThreadIdFromDom = () => {
          const el = document.getElementById('pgActiveThreadId');
          const raw = (el && typeof el.value === 'string') ? el.value : '';
          const n = parseInt(raw || '', 10);
          // Don't clobber currentThreadId if the DOM temporarily doesn't contain a valid value
          // (Livewire re-renders can momentarily yield empty values).
          if (Number.isFinite(n)) {
            currentThreadId = n;
            threadState = getThreadState(currentThreadId || 'none');
          }
          return currentThreadId;
        };

        const updateThreadBusyIndicators = () => {
          const dots = document.querySelectorAll('[data-thread-busy]');
          dots.forEach((el) => {
            const tid = el.getAttribute('data-thread-busy');
            const st = getThreadState(tid);
            if (st && st.inFlight) el.classList.remove('hidden');
            else el.classList.add('hidden');
          });
        };

        const updateFooterBusy = () => {
          // Der Footer soll sich am *aktuellen Thread* orientieren. threadState kann kurzfristig "stale" sein,
          // daher lesen wir den aktiven Thread-ID aus dem DOM und holen den State aus dem globalen Store.
          const el = document.getElementById('pgFooterBusy');
          const stopBtn = document.getElementById('pgStopBtn');
          const secondsWrap = document.getElementById('pgFooterSecondsWrap');
          const tid = refreshThreadIdFromDom();
          const st = getThreadState(tid || 'none');
          // Only consider inFlight. abortController can linger briefly and should not keep Stop enabled.
          const busy = !!(st && st.inFlight);
          // Footer stop button wrapper is always visible; we only toggle styling.
          if (el) {
            if (busy) el.classList.remove('hidden');
            else el.classList.add('hidden');
          }
          if (secondsWrap) {
            if (busy) secondsWrap.classList.remove('hidden');
            else secondsWrap.classList.add('hidden');
          }
          if (stopBtn) {
            stopBtn.disabled = !busy;
            if (busy) {
              stopBtn.classList.add('animate-pulse', 'text-red-600', 'border-red-200');
              stopBtn.classList.remove('text-[var(--ui-muted)]', 'border-[var(--ui-border)]');
            } else {
              stopBtn.classList.remove('animate-pulse', 'text-red-600', 'border-red-200');
              stopBtn.classList.add('text-[var(--ui-muted)]', 'border-[var(--ui-border)]');
            }
          }
        };

        // Live boxes in chat: show each stream section only when something is actually there.
        const updateLiveVisibility = () => {
          try {
            const liveBox = document.getElementById('pgLiveBox');
            const aWrap = document.getElementById('pgLiveAssistantWrap');
            const rWrap = document.getElementById('pgLiveReasoningWrap');
            const tWrap = document.getElementById('pgLiveThinkingWrap');

            const a = (threadState?.live?.assistant || '').trim();
            const r = (threadState?.live?.reasoning || '').trim();
            const t = (threadState?.live?.thinking || '').trim();
            const any = !!(a || r || t);

            if (liveBox) {
              if (any) liveBox.classList.remove('hidden');
              else liveBox.classList.add('hidden');
            }
            if (aWrap) { if (a) aWrap.classList.remove('hidden'); else aWrap.classList.add('hidden'); }
            if (rWrap) { if (r) rWrap.classList.remove('hidden'); else rWrap.classList.add('hidden'); }
            if (tWrap) { if (t) tWrap.classList.remove('hidden'); else tWrap.classList.add('hidden'); }
          } catch (_) {}
        };

        // Event log (same logic, but keep it lightweight)
        let rtVerbose = localStorage.getItem('simple.rtVerbose') === 'true';
        if (rtVerboseEl) {
          rtVerboseEl.checked = rtVerbose;
          rtVerboseEl.addEventListener('change', () => {
            rtVerbose = !!rtVerboseEl.checked;
            localStorage.setItem('simple.rtVerbose', rtVerbose ? 'true' : 'false');
          });
        }

        let lastEventKey = null;
        let lastEventCount = 0;
        let lastEventSummaryEl = null;
        const maxEventItems = 120;
        const debugState = {
          startedAt: null,
          payload: null,
          usage: null,
          partialUsage: null,  // Akkumuliert Usage während Stream
          model: null,
          events: [],
          sseEvents: [],
          lastAssistant: '',
          toolCalls: [],
          toolsVisible: null,
          streamCompleted: false,  // Wurde 'complete' Event empfangen?
          streamError: null,  // Stream-Fehler Info
          bytesReceived: 0,  // Bytes empfangen vor Fehler
          lastEventType: null,  // Letzter Event-Typ
        };
        const eventCounters = { all: 0, reasoning: 0, thinking: 0 };

        // Keep a copyable debug blob, even if we don't render it in the UI.
        // FIX: Fokussiert auf Tool-Diagnose - nur das Relevante, weniger Rauschen.
        window.__simplePlaygroundDebugDumpText = window.__simplePlaygroundDebugDumpText || '';
        const updateDebugDump = () => {
          // Payload ohne chat_history (zu groß), aber mit message + model
          const cleanPayload = debugState.payload ? {
            message: debugState.payload.message,
            model: debugState.payload.model,
            thread_id: debugState.payload.thread_id,
            max_iterations: debugState.payload.max_iterations,
            history_length: debugState.payload.chat_history?.length || 0,
          } : null;

          // SSE Events: Nur "complete" Events, keine Deltas
          const filteredSseEvents = debugState.sseEvents
            .filter(e => {
              const evt = e.event || '';

              // Immer behalten: Wichtige Lifecycle-Events
              if (['debug.tools', 'tool.start', 'tool.executed', 'done', 'error'].includes(evt)) return true;

              // openai.event: Nur ".done" und ".completed" Events, keine ".delta"
              if (evt === 'openai.event' && e.raw) {
                const r = e.raw;
                // Keine Deltas
                if (r.includes('.delta')) return false;
                // Nur complete Events + function_call
                if (r.includes('.done') || r.includes('.completed') || r.includes('function_call')) return true;
              }

              return false;
            })
            .slice(-30);

          // Assistant gekürzt (max 500 chars)
          const assistantPreview = debugState.lastAssistant
            ? (debugState.lastAssistant.length > 500
                ? debugState.lastAssistant.slice(0, 500) + '...[' + debugState.lastAssistant.length + ' chars]'
                : debugState.lastAssistant)
            : '';

          const out = {
            _info: 'Debug-Dump für Tool-Diagnose. Kopieren und teilen.',
            session: {
              startedAt: debugState.startedAt,
              model: debugState.model,
            },
            request: cleanPayload,
            tools: debugState.toolsVisible,  // WICHTIG: Welche Tools wurden registriert?
            toolCalls: debugState.toolCalls.slice(-20),  // Was wurde aufgerufen?
            usage: debugState.usage || debugState.partialUsage || null,  // Final oder Partial Usage
            assistantPreview: assistantPreview,
            relevantEvents: filteredSseEvents,
            // Stream-Status für Debugging
            streamCompleted: debugState.streamCompleted,
            streamIncomplete: !debugState.streamCompleted && debugState.sseEvents.length > 0,
            streamError: debugState.streamError,
            bytesReceived: debugState.bytesReceived,
            lastEventType: debugState.lastEventType,
            // Fallback: Alle Events (falls nötig)
            _allEventsCount: debugState.sseEvents.length,
          };
          const text = JSON.stringify(out, null, 2);
          window.__simplePlaygroundDebugDumpText = text;
          if (rtDebugDump) rtDebugDump.value = text;
        };

        if (rtCopyDebug) {
          rtCopyDebug.addEventListener('click', async () => {
            try {
              const text = rtDebugDump?.value || window.__simplePlaygroundDebugDumpText || '';
              await navigator.clipboard.writeText(text);
              if (rtCopyStatus) rtCopyStatus.textContent = 'kopiert';
              setTimeout(() => { if (rtCopyStatus) rtCopyStatus.textContent = ''; }, 1500);
            } catch (e) {
              if (rtCopyStatus) rtCopyStatus.textContent = 'copy fehlgeschlagen';
            }
          });
        }

        const rtEventLabel = (key, preview) => {
          try {
            const bits = [String(key || '')];
            if (preview && typeof preview === 'object') {
              if (preview.sequence_number != null) bits.push(`seq:${preview.sequence_number}`);
              if (preview.output_index != null) bits.push(`out:${preview.output_index}`);
              if (preview.call_id) bits.push(`call:${String(preview.call_id).slice(0, 18)}…`);
              if (preview.id && !preview.call_id) bits.push(`id:${String(preview.id).slice(0, 18)}…`);
              if (preview.name) bits.push(`name:${String(preview.name)}`);
              if (preview.status) bits.push(`status:${String(preview.status)}`);
            }
            return bits.filter(Boolean).join(' · ').slice(0, 220);
          } catch (_) {
            return String(key || '');
          }
        };

        const updateEventCountersUi = () => {
          if (rtEventCountEl) rtEventCountEl.textContent = String(eventCounters.all || 0);
          if (rtEventReasonCountEl) rtEventReasonCountEl.textContent = String(eventCounters.reasoning || 0);
          if (rtEventThinkingCountEl) rtEventThinkingCountEl.textContent = String(eventCounters.thinking || 0);
        };

        // Persist last event label across Livewire re-renders (otherwise the footer often resets to "—").
        window.__simplePlaygroundLastEvent = window.__simplePlaygroundLastEvent || { key: null, text: null };
        const setEventLabel = (key, payload = null) => {
          try {
            const label = String(key || '—');
            window.__simplePlaygroundLastEvent.key = label;
            window.__simplePlaygroundLastEvent.text = label;

            const footerEl = document.getElementById('pgFooterEventText');
            if (footerEl) {
              // Footer should stay compact: show only the event name (no payload).
              footerEl.textContent = label.slice(0, 160);
            }
          } catch (_) {}

          // Mirror next to the input too (if present in this app version)
          try {
            const inlineEl = document.getElementById('pgInlineEventText');
            if (inlineEl) inlineEl.textContent = String(key || '—').slice(0, 80);
          } catch (_) {}
        };

        const rtEvent = ({ key, preview = null, raw = null }) => {
          if (!key) return;
          // Also show last event in the chat footer + persist it.
          // For OpenAI events we pass both preview+raw so the footer can show a small raw snippet.
          setEventLabel(key, raw || preview || null);

          const eventKey = `${key}:${preview?.type || ''}:${preview?.id || ''}:${preview?.call_id || ''}:${preview?.name || ''}`;
          if (eventKey === lastEventKey && lastEventSummaryEl) {
            lastEventCount++;
            lastEventSummaryEl.textContent = `${rtEventLabel(key, preview)} ×${lastEventCount}`;
          } else {
            lastEventKey = eventKey;
            lastEventCount = 1;
            const row = document.createElement('div');
            row.className = 'border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)]';
            const summary = document.createElement('div');
            summary.className = 'font-mono text-[10px] text-[var(--ui-secondary)]';
            summary.textContent = rtEventLabel(key, preview);
            row.appendChild(summary);
            if (rtVerbose && raw) {
              const pre = document.createElement('pre');
              pre.className = 'mt-1 text-[10px] whitespace-pre-wrap text-[var(--ui-muted)]';
              pre.textContent = String(raw);
              row.appendChild(pre);
            }
            if (rtEvents) rtEvents.appendChild(row);
            lastEventSummaryEl = summary;
          }
          if (rtEvents) {
            while (rtEvents.children.length > maxEventItems) rtEvents.removeChild(rtEvents.firstChild);
            rtEvents.scrollTop = rtEvents.scrollHeight;
          }
        };

        // Tool calls: render newest first (top = latest), used by both tool.executed and OpenAI built-in tools.
        const renderToolCalls = () => {
          try {
            if (!rtToolCalls) return;
            const items = (debugState.toolCalls || []).slice(0);
            rtToolCalls.innerHTML = '';
            for (let i = items.length - 1; i >= 0; i--) {
              const it = items[i] || {};
              const row = document.createElement('div');
              row.className = 'border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)]';
              const isRunning = (it.success === null) || (it.status === 'running');
              const statusClass = isRunning
                ? 'text-yellow-600'
                : (it.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]');
              const statusIcon = isRunning ? '⏳' : (it.success ? '✅' : '❌');
              const statusText = isRunning ? 'Läuft' : (it.success ? 'Erfolgreich' : 'Fehlgeschlagen');
              const cachedBadge = it.cached ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] ml-2">cached</span>' : '';
              const argsRaw = (typeof it.args_json === 'string' && it.args_json.trim() !== '')
                ? it.args_json
                : (it.args != null ? JSON.stringify(it.args) : (it.args_preview || ''));
              const argsPretty = (() => {
                try {
                  if (typeof argsRaw === 'string' && argsRaw.trim().startsWith('{')) {
                    return JSON.stringify(JSON.parse(argsRaw), null, 2);
                  }
                } catch (_) {}
                return String(argsRaw || '');
              })();
              const argsPreview = argsPretty
                ? (argsPretty.length > 140 ? (argsPretty.substring(0, 140) + '…') : argsPretty)
                : '—';
              const errorInfo = it.error ? `<div class="mt-1 text-[10px] text-[var(--ui-danger)]">Error: ${it.error_code || 'UNKNOWN'}: ${typeof it.error === 'string' ? it.error.substring(0, 150) : JSON.stringify(it.error).substring(0, 150)}</div>` : '';
              row.innerHTML = `
                <div class="flex items-start justify-between gap-2 mb-1">
                  <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-mono font-semibold text-[var(--ui-secondary)] truncate">${it.tool || '—'}</div>
                    ${it.call_id ? `<div class="text-[10px] text-[var(--ui-muted)] mt-0.5">Call-ID: ${it.call_id}</div>` : ''}
                  </div>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-[10px] ${statusClass}">${statusIcon} ${statusText}</span>
                    ${it.ms != null ? `<span class="text-[10px] text-[var(--ui-muted)]">⏱️ ${it.ms}ms</span>` : ''}
                    ${cachedBadge}
                  </div>
                </div>
                ${argsPreview !== '—' ? `<div class="mt-1 text-[10px] text-[var(--ui-muted)]"><span class="font-semibold">Args:</span> <code class="block px-1 py-0.5 rounded bg-[var(--ui-muted-5)] font-mono whitespace-pre-wrap break-words">${argsPreview}</code></div>` : ''}
                ${errorInfo}
              `;
              rtToolCalls.appendChild(row);
            }
          } catch (_) {}
        };

        const resetRealtime = () => {
          removeStreamingAssistantMessage();
          removeStreamingMetaMessages();
          if (rtAssistant) rtAssistant.textContent = '';
          if (rtReasoning) rtReasoning.textContent = '';
          if (rtThinking) rtThinking.textContent = '';
          if (rtEvents) rtEvents.innerHTML = '';
          if (rtToolCalls) rtToolCalls.innerHTML = '';
          if (rtStreamLog) rtStreamLog.textContent = '';
          window.__simplePlaygroundStreamLog = '';
          if (rtTokensIn) rtTokensIn.textContent = '—';
          if (rtTokensOut) rtTokensOut.textContent = '—';
          if (rtTokensTotal) rtTokensTotal.textContent = '—';
          if (rtTokensExtra) rtTokensExtra.textContent = '—';
          if (rtCostIn) rtCostIn.textContent = '—';
          if (rtCostCached) rtCostCached.textContent = '—';
          if (rtCostOut) rtCostOut.textContent = '—';
          if (rtCostTotal) rtCostTotal.textContent = '—';
          if (rtStatus) rtStatus.textContent = 'idle';
          eventCounters.all = 0;
          eventCounters.reasoning = 0;
          eventCounters.thinking = 0;
          updateEventCountersUi();
          debugState.startedAt = null;
          debugState.payload = null;
          debugState.usage = null;
          debugState.partialUsage = null;
          debugState.model = null;
          debugState.events = [];
          debugState.sseEvents = [];
          debugState.lastAssistant = '';
          debugState.toolCalls = [];
          debugState.toolsVisible = null;
          debugState.streamCompleted = false;
          debugState.streamError = null;
          debugState.bytesReceived = 0;
          debugState.lastEventType = null;
          updateDebugDump();

          try {
            threadState.live.assistant = '';
            threadState.live.reasoning = '';
            threadState.live.thinking = '';
            if (threadState.ephemeralNoteEls && Array.isArray(threadState.ephemeralNoteEls)) {
              for (const el of threadState.ephemeralNoteEls) {
                try { if (el && el.parentNode) el.parentNode.removeChild(el); } catch (_) {}
              }
              threadState.ephemeralNoteEls = [];
            }
          } catch (_) {}
        };

        if (realtimeClear) realtimeClear.addEventListener('click', resetRealtime);

        // Models: keep existing behavior (safe fallback)
        const setSelectedModel = (m) => {
          selectedModel = m || '';
          localStorage.setItem('simple.selectedModel', selectedModel);
          if (selectedModelLabel) selectedModelLabel.textContent = selectedModel || '—';
          if (modelSelect) {
            modelSelect.value = selectedModel || '';
            // Also save to thread if thread exists
            if (currentThreadId && selectedModel) {
              // Save model to thread via Livewire
              if (window.Livewire) {
                window.Livewire.find(livewireComponentId).call('updateThreadModel', currentThreadId, selectedModel).catch(() => {});
              }
            }
          }
        };

        // Ensure modelSelect is available and has change listener
        const initModelSelect = () => {
          modelSelect = document.getElementById('modelSelect');
          if (modelSelect && !modelSelect.dataset.listenerAttached) {
            modelSelect.addEventListener('change', (e) => {
              const newModel = e.target.value;
              if (newModel) {
                setSelectedModel(newModel);
              }
            });
            modelSelect.dataset.listenerAttached = 'true';
            // Set initial value if available
            if (selectedModel) {
              modelSelect.value = selectedModel;
            }
          }
        };
        
        // Initialize immediately and also after Livewire updates
        initModelSelect();
        document.addEventListener('livewire:update', () => {
          setTimeout(initModelSelect, 50);
        });
        
        // Load model from active thread on page load or after Livewire update
        const loadModelFromThread = () => {
          if (activeThreadModel && activeThreadModel !== selectedModel) {
            setSelectedModel(activeThreadModel);
            // Update dropdown after models are loaded
            setTimeout(() => {
              if (modelSelect) modelSelect.value = activeThreadModel;
            }, 100);
          }
        };
        
        // Load on initial page load
        loadModelFromThread();
        
        // Also listen for Livewire updates (when thread is switched)
        document.addEventListener('livewire:update', () => {
          setTimeout(loadModelFromThread, 50);
        });

        // DropZone: accept drag&drop model selection (same behavior as the page playground)
        if (modelDropZone) {
          modelDropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            try { e.dataTransfer.dropEffect = 'copy'; } catch (_) {}
          });
          modelDropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            const id = (e.dataTransfer && e.dataTransfer.getData) ? e.dataTransfer.getData('text/plain') : '';
            if (id) setSelectedModel(id);
          });
        }

        const renderModels = (models, defaultFromServer = null) => {
          if (!Array.isArray(models)) models = [];
          
          // Fill modelSelect dropdown (Chat tab) - now using x-ui-input-select component
          // The select is server-rendered, so we just need to update the value if needed
          if (modelSelect) {
            // The select is already populated server-side, just set the value
            if (selectedModel) {
              modelSelect.value = selectedModel;
            }
            // Ensure change listener is attached
            if (!modelSelect.dataset.listenerAttached) {
              modelSelect.addEventListener('change', () => setSelectedModel(modelSelect.value));
              modelSelect.dataset.listenerAttached = 'true';
            }
          }
          if (modelsList) {
            modelsList.innerHTML = '';
            for (const m of models) {
              const row = document.createElement('div');
              row.className = 'flex items-center justify-between gap-2 px-3 py-2 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-sm';
              row.draggable = true;
              row.innerHTML = `<span class="truncate">${m}</span><button type="button" class="text-xs text-[var(--ui-muted)] hover:underline">select</button>`;
              row.addEventListener('dblclick', () => setSelectedModel(m));
              row.querySelector('button')?.addEventListener('click', () => setSelectedModel(m));
              row.addEventListener('dragstart', (e) => {
                try {
                  e.dataTransfer.setData('text/plain', m);
                  e.dataTransfer.effectAllowed = 'copy';
                } catch (_) {}
              });
              modelsList.appendChild(row);
            }
          }
          if (!selectedModel) {
            const pick = (defaultFromServer && models.includes(defaultFromServer))
              ? defaultFromServer
              : (models[0] || serverDefaultModel);
            setSelectedModel(pick);
          }
        };

        const loadModels = async () => {
          try {
            if (modelsList) modelsList.innerHTML = '<div class="text-xs text-[var(--ui-muted)]">Lade Models…</div>';
            const res = await fetch(modelsUrl, {
              method: 'GET',
              credentials: 'same-origin',
              headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json().catch(() => ({}));
            const ids = Array.isArray(data?.models) ? data.models : [];
            const def = (typeof data?.default_model === 'string') ? data.default_model : null;
            if (ids.length > 0) {
              renderModels(ids, def);
              return;
            }
          } catch (_) {}
          // Fallback
          if (modelsList) modelsList.innerHTML = '<div class="text-xs text-[var(--ui-muted)]">Fallback: gpt-5.2</div>';
          renderModels([serverDefaultModel], serverDefaultModel);
        };
        // Always refresh models (so gpt-5.2 is visible immediately after opening).
        if (!alreadyBound && modelsReload) modelsReload.addEventListener('click', () => loadModels());
        loadModels();

        // Livewire drives thread switching; the chat history is server-rendered.
        // JS only needs the current thread id + an in-memory copy of the server-rendered history
        // to send as chat_history for the next request.
        const refreshMessagesFromServerRender = () => {
          const initial = Array.isArray(window.__simpleInitialMessages) ? window.__simpleInitialMessages : [];
          // Initialize state if empty (first time we see this thread)
          if (!threadState.messages || threadState.messages.length === 0) {
            threadState.messages = initial;
          }
          const empty = document.getElementById('chatEmpty');
          if (empty) {
            empty.style.display = (threadState.messages.length > 0) ? 'none' : '';
          }
        };

        const renderChatFromState = () => {
          refreshDomRefs();
          if (!chatList) return;
          chatList.innerHTML = '';
          (threadState.messages || []).forEach((m) => {
            if (!m || !m.role) return;
            renderMessage(m.role, m.content || '');
          });
        };

        refreshThreadIdFromDom();
        refreshMessagesFromServerRender();
        updateThreadBusyIndicators();
        updateFooterBusy();
        // Setup auto-scroll observer (smart: only scrolls if user is near bottom)
        setupAutoScroll();
        // Setup auto-grow for textarea (adapted from comms modal)
        if (input && input.tagName === 'TEXTAREA' && !input.dataset.autoGrowBound) {
          // Initial auto-grow
          requestAnimationFrame(() => autoGrow(input));
          // Auto-grow on input and focus
          input.addEventListener('input', (e) => autoGrow(e.target));
          input.addEventListener('focus', (e) => autoGrow(e.target));
          input.dataset.autoGrowBound = '1';
        }
        // Initial smooth scroll after setup
        setTimeout(() => scrollToBottom(), 200);
        let lastThreadId = currentThreadId;
        document.addEventListener('livewire:update', () => {
          refreshThreadIdFromDom();
          refreshMessagesFromServerRender();
          // After Livewire renders server history, remove temporary messages from pgStreamingSlot.
          // Server history in chatList is the source of truth; pgStreamingSlot is only for streaming.
          try {
            const slot = document.getElementById('pgStreamingSlot');
            const st = getThreadState(currentThreadId || 'none');
            if (slot) {
              // ALWAYS remove temporary USER messages (they're now in chatList from DB)
              // This fixes the duplicate sender issue during streaming
              const temporary = slot.querySelectorAll('[data-temporary="1"]');
              temporary.forEach((el) => {
                if (el.parentNode) el.parentNode.removeChild(el);
              });
              // Remove finalized assistant messages (they're now in chatList)
              const finalized = slot.querySelectorAll('[data-final="1"]');
              finalized.forEach((el) => {
                if (el.parentNode) el.parentNode.removeChild(el);
              });
              // If stream is NOT in flight, also remove streaming messages
              if (!st || !st.inFlight) {
                const streaming = slot.querySelectorAll('[data-streaming="1"]');
                streaming.forEach((el) => {
                  if (el.parentNode) el.parentNode.removeChild(el);
                });
              }
            }
          } catch (_) {}
          updateThreadBusyIndicators();
          updateFooterBusy();
          // Restore last event label after DOM re-render (prevents "Event: —" during active stream).
          try {
            const last = window.__simplePlaygroundLastEvent?.key;
            if (last) setEventLabel(last);
          } catch (_) {}
          // Re-setup auto-scroll if needed (after Livewire re-renders DOM)
          setupAutoScroll();
          // Re-setup auto-grow for textarea (in case it was re-rendered)
          if (input && input.tagName === 'TEXTAREA' && !input.dataset.autoGrowBound) {
            requestAnimationFrame(() => autoGrow(input));
            input.addEventListener('input', (e) => autoGrow(e.target));
            input.addEventListener('focus', (e) => autoGrow(e.target));
            input.dataset.autoGrowBound = '1';
          }
          if (currentThreadId !== lastThreadId) {
            // When switching threads, clear the wire:ignore slot so we don't show stale client-rendered messages.
            try {
              const slot = document.getElementById('pgStreamingSlot');
              if (slot) slot.innerHTML = '';
            } catch (_) {}
            lastThreadId = currentThreadId;
            // Smooth scroll to bottom after thread switch
            setTimeout(() => scrollToBottom(), 150);
          }
          // Note: overflow-anchor CSS handles auto-scroll now
        });

        // Iterations: keep high defaults; allow user override via localStorage.
        const getMaxIterations = () => {
          const raw = (localStorage.getItem('simple.playground.maxIterations') || '').trim();
          const n = parseInt(raw || '200', 10);
          if (!Number.isFinite(n) || n < 1) return 200;
          return Math.min(200, n);
        };

        const setSendButtonBusy = (busy) => {
          refreshDomRefs();
          if (!sendBtn) return;
          // Keep the send button stable (aesthetics): Stop lives in the footer.
          sendBtn.disabled = !!busy;
          if (busy) sendBtn.classList.add('opacity-60', 'cursor-not-allowed');
          else sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');

          // Inline busy chip next to the input (more visible than the footer alone)
          const chip = document.getElementById('pgInlineBusy');
          if (chip) {
            if (busy) chip.classList.remove('hidden');
            else chip.classList.add('hidden');
          }
        };

        const stopCurrentRequest = () => {
          try {
            // Ensure we target the latest active thread (threadState may be stale after DOM updates).
            const tid = refreshThreadIdFromDom();
            const st = getThreadState(tid || 'none');

            // If nothing is running in the active thread, try any in-flight thread (rare edge case).
            let target = st;
            if (!(target && (target.inFlight || target.abortController))) {
              const store = window.__simplePlaygroundThreadStore || {};
              const any = Object.values(store).find((x) => x && (x.inFlight || x.abortController));
              if (any) target = any;
            }

            if (!target || !(target.inFlight || target.abortController)) return;
            target.userAborted = true;
            if (rtStatus) rtStatus.textContent = 'abgebrochen';
            if (target.abortController) {
              try { target.abortController.abort(); } catch (_) {}
            }
            // Immediately reflect "stopped" in UI (abort will finish the fetch loop shortly).
            target.inFlight = false;
            target.abortController = null;
            // Optimistically update footer state immediately
            updateThreadBusyIndicators();
            updateFooterBusy();
          } catch (_) {}
        };

        const bindFooterHandlers = () => {
          const stopBtn = document.getElementById('pgStopBtn');
          if (stopBtn && !stopBtn.dataset.clickBound) {
            stopBtn.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              stopCurrentRequest();
            });
            stopBtn.dataset.clickBound = '1';
          }
        };

        // Send (parity with page playground: chat updates only on complete)
        const send = async () => {
          refreshDomRefs();
          const text = (input?.value || '').trim();
          if (threadState.inFlight) return;

          const canContinue = !!(threadState.continuation && threadState.continuation.pending);
          const isContinue = (!text && canContinue);
          if (!text && !isContinue) return;
          if (text && canContinue) {
            threadState.messages.push({ role: 'assistant', content: '⚠️ Es gibt noch einen laufenden Prozess. Bitte zuerst einmal mit leerer Eingabe fortsetzen (Enter), danach kannst du die nächste Frage senden.' });
            renderMessage('assistant', '⚠️ Es gibt noch einen laufenden Prozess. Bitte zuerst einmal mit leerer Eingabe fortsetzen (Enter), danach kannst du die nächste Frage senden.');
            return;
          }

          // Resolve active thread *before* mutating threadState/inFlight.
          // Otherwise we may accidentally set inFlight on the "none" thread, breaking Stop/seconds/event.
          refreshThreadIdFromDom();
          if (!currentThreadId) {
            renderMessage('assistant', '⚠️ Kein aktiver Thread gefunden. Bitte einmal einen Thread anlegen oder neu auswählen.');
            return;
          }
          // Ensure threadState points to the real thread now.
          threadState = getThreadState(currentThreadId);

          // Get current attachments
          const attachmentIds = typeof window.__simplePlaygroundGetAttachmentIds === 'function'
            ? window.__simplePlaygroundGetAttachmentIds()
            : [];

          if (!isContinue) {
            const userMessage = { role: 'user', content: text };
            if (attachmentIds.length > 0) {
              userMessage.attachments = attachmentIds;
            }
            threadState.messages.push(userMessage);
            // Render user message immediately in the UI
            renderMessage('user', text);
            try {
              threadState._lastSentUserContent = text;
              threadState._lastSentUserIndex = (threadState.messages?.length || 1) - 1;
            } catch (_) {}
            input.value = '';
            // Reset textarea height after sending
            if (input && input.tagName === 'TEXTAREA') {
              requestAnimationFrame(() => autoGrow(input));
            }
            // Clear attachments after adding to message
            if (typeof window.__simplePlaygroundClearAttachments === 'function') {
              window.__simplePlaygroundClearAttachments();
            }
          }

          threadState.inFlight = true;
          threadState.startedAtMs = Date.now();
          setSendButtonBusy(true);
          input.disabled = true;
          // Start auto-scroll during streaming
          startScrollInterval();
          // Disable model switching during an active stream (reduces confusion)
          const ms = document.getElementById('modelSelect');
          if (ms) ms.disabled = true;
          resetRealtime();
          if (rtStatus) rtStatus.textContent = 'streaming…';
          // realtimeModel UI element no longer exists in the modal (kept from older layout)
          // Make start visible in the footer event tile immediately.
          try { setEventLabel('request.start'); } catch (_) {}
          // Ephemeral note: show request start + current model, without persisting.
          try {
            renderEphemeralNote('Request', `Start · Model: ${String(modelToUse || selectedModel || defaultModelId || '—')}`, 'info');
          } catch (_) {}
          debugState.startedAt = new Date().toISOString();
          updateThreadBusyIndicators();
          updateFooterBusy();
          // Prime inline event label (deprecated; kept as no-op if element doesn't exist)
          try {
            const inlineEv = document.getElementById('pgInlineEventText');
            if (inlineEv) inlineEv.textContent = 'request.start';
          } catch (_) {}

          // Get model from select field (in case it was changed)
          // Re-fetch modelSelect in case it was re-rendered by Livewire
          const currentModelSelect = document.getElementById('modelSelect');
          const currentModel = currentModelSelect ? currentModelSelect.value : (modelSelect ? modelSelect.value : selectedModel);
          if (currentModel) {
            selectedModel = currentModel;
            localStorage.setItem('simple.selectedModel', selectedModel);
            
            // Save model to thread if thread exists
            if (currentThreadId && selectedModel) {
              if (window.Livewire) {
                window.Livewire.find(livewireComponentId).call('updateThreadModel', currentThreadId, selectedModel).catch(() => {});
              }
            }
          }

          // Fallback to default if no model selected
          const modelToUse = selectedModel || defaultModelId;

          // Context vom Livewire-Component holen (inkl. Auto-Deaktivierung)
          let contextToSend = null;
          try {
            contextToSend = await window.Livewire.find(livewireComponentId).call('getContextForRequest');
          } catch (e) {
            console.warn('[Playground] Could not get context from Livewire:', e);
          }

          const payload = {
            message: (isContinue ? '' : text),
            chat_history: threadState.messages,
            thread_id: currentThreadId,
            model: modelToUse,
            continuation: (isContinue ? threadState.continuation : null),
            context: contextToSend,
            max_iterations: getMaxIterations(),
            attachments: attachmentIds.length > 0 ? attachmentIds : null,
          };
          debugState.payload = payload;
          updateDebugDump();

          // Best practice: route the whole SSE stream to the thread that initiated the request.
          // Livewire can temporarily desync the hidden input during re-renders; the request thread id is stable.
          const requestThreadId = currentThreadId;

          const abortController = new AbortController();
          threadState.abortController = abortController;
          threadState.userAborted = false;
          updateFooterBusy();

          try {
            const res = await fetch(url, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrf },
              body: JSON.stringify(payload),
              signal: abortController.signal,
            });

            if (!res.ok || !res.body) {
              const ct = res.headers?.get?.('content-type') || '';
              let bodyText = '';
              try { bodyText = await res.text(); } catch { bodyText = ''; }
              const snippet = (bodyText || '').slice(0, 800);
              throw new Error(`HTTP ${res.status} ${res.statusText || ''} ${ct ? `(${ct})` : ''}${snippet ? `\n\n${snippet}` : ''}`.trim());
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let currentEvent = null;
          // Ensure we show at least the upstream start event types, even if no data arrives yet.
          try { setEventLabel('response.stream.open'); } catch (_) {}
            let deltaDumpCounter = 0;

            while (true) {
              const { done, value } = await reader.read();
              if (done) break;
              // Track bytes received for debug
              if (value) debugState.bytesReceived += value.length;
              buffer += decoder.decode(value, { stream: true });
              const lines = buffer.split('\n');
              buffer = lines.pop() || '';

              for (const line of lines) {
                if (!line.trim()) continue;
                if (line.startsWith('event:')) { currentEvent = line.slice(6).trim(); continue; }
                if (!line.startsWith('data:')) continue;
                const raw = line.slice(5).trim();
                let data;
                try { data = JSON.parse(raw); } catch { data = { raw }; }

                // Best practice: route all SSE UI updates by explicit thread_id (server-provided),
                // never by DOM timing (Livewire can temporarily desync hidden inputs).
                let eventThreadId = null;
                try {
                  const n = parseInt(String(data?.thread_id ?? ''), 10);
                  if (Number.isFinite(n)) eventThreadId = n;
                } catch (_) {}
                // Fallback to the request thread id if the server doesn't include it (legacy).
                if (eventThreadId == null && requestThreadId) eventThreadId = requestThreadId;

                const st = (eventThreadId != null) ? getThreadState(eventThreadId) : threadState;
                const visibleTid = refreshThreadIdFromDom();
                const isVisible = (eventThreadId == null) ? true : (eventThreadId === visibleTid);

                // Always surface *every* SSE event in the footer (even if we don't have a special handler).
                // For openai.event we still set the label inside its handler to show the upstream event name.
                // Always reflect the raw incoming SSE event in the footer (no mapping/overwrites).
                try { if (currentEvent) setEventLabel(currentEvent, data); } catch (_) {}

                // Track last event type for debugging stream interruptions
                if (currentEvent) debugState.lastEventType = currentEvent;

                // Also persist every SSE event into the copyable debug dump (bounded + truncated).
                try {
                  const evt = String(currentEvent || '—');
                  let rawStr = (typeof raw === 'string') ? raw : '';
                  if (rawStr.length > 4000) rawStr = rawStr.slice(0, 4000) + '…';
                  debugState.sseEvents.push({ t: Date.now(), event: evt, raw: rawStr });
                  if (debugState.sseEvents.length > 350) {
                    debugState.sseEvents.splice(0, debugState.sseEvents.length - 350);
                  }
                  // Avoid heavy stringify on every delta; update periodically.
                  if (evt === 'assistant.delta' || evt === 'reasoning.delta' || evt === 'thinking.delta') {
                    deltaDumpCounter++;
                    if (deltaDumpCounter % 25 === 0) updateDebugDump();
                  } else {
                    updateDebugDump();
                  }
                } catch (_) {}

                switch (currentEvent) {
                  case 'assistant.delta':
                    {
                      const delta = (typeof data?.delta === 'string') ? data.delta : '';
                      const full = (typeof data?.content === 'string') ? data.content : '';
                      if (delta !== '' || full !== '') {
                        st.live.assistant = delta !== ''
                          ? (st.live.assistant + delta)
                          : full;
                        // Stream only in debug panel (final result rendered by Livewire)
                        appendStreamLog('assistant.delta', delta !== '' ? delta : full);
                      }
                      debugState.lastAssistant = st.live.assistant;
                    }
                    break;
                  case 'assistant.reset':
                    st.live.assistant = '';
                    appendStreamLog('assistant.reset', '\n');
                    break;
                  case 'reasoning.delta':
                    if (data?.delta) {
                      st.live.reasoning += data.delta;
                      if (isVisible) updateStreamingMetaMessage('reasoning', st.live.reasoning);
                      appendStreamLog('reasoning.delta', data.delta);
                    }
                    break;
                  case 'reasoning.reset':
                    st.live.reasoning = '';
                    const reasoningEl = document.getElementById('pgStreamingReasoningMsg');
                    if (isVisible && reasoningEl && reasoningEl.parentNode) reasoningEl.parentNode.removeChild(reasoningEl);
                    break;
                  case 'thinking.delta':
                    if (data?.delta) {
                      st.live.thinking += data.delta;
                      if (isVisible) updateStreamingMetaMessage('thinking', st.live.thinking);
                      appendStreamLog('thinking.delta', data.delta);
                    }
                    break;
                  case 'thinking.reset':
                    st.live.thinking = '';
                    const thinkingEl = document.getElementById('pgStreamingThinkingMsg');
                    if (isVisible && thinkingEl && thinkingEl.parentNode) thinkingEl.parentNode.removeChild(thinkingEl);
                    break;
                  case 'debug.tools':
                    debugState.toolsVisible = data || null;
                    updateDebugDump();
                    break;
                  case 'tool.executed':
                    debugState.toolCalls.push(data);
                    // Render newest first (top = latest)
                    renderToolCalls();
                    updateDebugDump();
                    break;
                  case 'openai.event': {
                    const ev = data?.event || 'openai.event';
                    // Keep counters so we can quickly verify whether reasoning/thinking streams exist.
                    eventCounters.all++;
                    if (typeof ev === 'string') {
                      if (ev.includes('reasoning_summary_text') || ev.includes('reasoning_text')) {
                        // OpenAI Responses events: response.reasoning_* (we display as Reasoning/Thinking)
                        if (ev.includes('reasoning_summary_text')) eventCounters.reasoning++;
                        if (ev.includes('reasoning_text')) eventCounters.thinking++;
                      }
                    }
                    updateEventCountersUi();
                    rtEvent({ key: ev, preview: data?.preview || null, raw: data?.raw || null });

                    // Surface OpenAI built-in tool calls (e.g. web_search) in the Tool Calls panel (start + done).
                    try {
                      const p = data?.preview || {};
                      const type = (p.type || '').toString();
                      const callId = (p.call_id || p.id || '').toString();
                      // Prefer parsing the upstream event name (ev), because for Responses streaming the "type"
                      // field is not always a stable "*_call" string.
                      const evStr = (ev || '').toString();
                      // Examples:
                      // - response.web_search_call.in_progress
                      // - response.file_search_call.completed
                      // - response.image_generation_call.failed
                      const responsePrefix = 'response.';
                      const callMarker = '_call.';
                      let parsedToolName = null;
                      let phase = null;
                      if (evStr.startsWith(responsePrefix)) {
                        const callPos = evStr.indexOf(callMarker);
                        if (callPos > responsePrefix.length) {
                          parsedToolName = evStr.slice(responsePrefix.length, callPos) || null;
                          phase = evStr.slice(callPos + callMarker.length) || null;
                        }
                      }
                      const isStartLike = phase === 'in_progress';
                      const isDone = phase === 'completed' || phase === 'done';
                      const isFailed = phase === 'failed';
                      const toolName = parsedToolName || (type.includes('web_search') ? 'web_search' : (type.endsWith('_call') ? type.replace(/_call$/, '') : null));
                      if (toolName && callId) {
                        window.__simpleBuiltinToolByCallId = window.__simpleBuiltinToolByCallId || {};
                        const idx = window.__simpleBuiltinToolByCallId[callId];
                        if (idx == null && isStartLike) {
                          // Create "running" entry
                          window.__simpleBuiltinToolByCallId[callId] = debugState.toolCalls.length;
                          debugState.toolCalls.push({
                            tool: `openai:${toolName}`,
                            call_id: callId,
                            success: null,
                            status: 'running',
                            ms: null,
                            error_code: null,
                            error: null,
                            cached: false,
                            retries: 0,
                            args_preview: p.query ? JSON.stringify({ query: p.query }) : null,
                            args_json: p.query ? JSON.stringify({ query: p.query }) : null,
                            args: p.query ? { query: p.query } : null,
                          });
                          renderToolCalls();
                          updateDebugDump();
                        } else if (idx != null && (isDone || isFailed)) {
                          // Mark as completed/failed and re-render
                          try {
                            debugState.toolCalls[idx].success = isFailed ? false : true;
                            debugState.toolCalls[idx].status = isFailed ? 'failed' : 'completed';
                            renderToolCalls();
                          } catch (_) {}
                          updateDebugDump();
                        }
                      }
                    } catch (_) {}

                    debugState.events.push({ t: Date.now(), event: ev, preview: data?.preview || null, raw: data?.raw || null });
                    updateDebugDump();
                    break;
                  }
                  case 'usage': {
                    const usage = data?.usage || {};
                    const inTok = usage?.input_tokens ?? null;
                    const outTok = usage?.output_tokens ?? null;
                    const totalTok = usage?.total_tokens ?? null;
                    const cached = usage?.input_tokens_details?.cached_tokens ?? null;
                    const reasoning = usage?.output_tokens_details?.reasoning_tokens ?? null;

                    // Show both thread totals (Gesamt) and request values (aktueller Request)
                    const threadTotals = data?.thread_totals;
                    
                    // Update thread totals (Gesamt) - left side (always update if available)
                    if (threadTotals) {
                      if (rtTokensInTotal) rtTokensInTotal.textContent = formatNumber(threadTotals.total_tokens_in || 0);
                      if (rtTokensOutTotal) rtTokensOutTotal.textContent = formatNumber(threadTotals.total_tokens_out || 0);
                      const cachedTotal = (threadTotals.total_tokens_cached || 0) + (threadTotals.total_tokens_reasoning || 0);
                      if (rtTokensExtraTotal) rtTokensExtraTotal.textContent = formatNumber(cachedTotal);
                      if (rtCostTotal) {
                        const cost = parseFloat(threadTotals.total_cost || 0);
                        const currency = threadTotals.pricing_currency || 'USD';
                        rtCostTotal.textContent = `${cost.toFixed(4)} ${currency}`;
                      }
                      const grandTokEl = document.getElementById('rtTokensGrand');
                      if (grandTokEl) {
                        const grand = (threadTotals.total_tokens_in || 0) + (threadTotals.total_tokens_out || 0);
                        grandTokEl.textContent = formatNumber(grand);
                      }
                    }
                    
                    // Update request values (aktueller Request) - right side (always update during stream)
                    if (rtTokensIn) rtTokensIn.textContent = (inTok != null ? formatNumber(inTok) : '—');
                    if (rtTokensOut) rtTokensOut.textContent = (outTok != null ? formatNumber(outTok) : '—');
                    const cachedReq = (cached != null ? cached : 0) + (reasoning != null ? reasoning : 0);
                    if (rtTokensExtra) rtTokensExtra.textContent = cachedReq > 0 ? formatNumber(cachedReq) : '—';
                    const reqTokEl = document.getElementById('rtTokensReq');
                    if (reqTokEl) {
                      const reqTok = (totalTok != null) ? totalTok : ((inTok || 0) + (outTok || 0));
                      reqTokEl.textContent = formatNumber(reqTok);
                    }
                    
                    // Calculate and display request cost
                    // Use cost from last_increment if available (server-calculated), otherwise calculate client-side
                    const lastIncrement = data?.last_increment || {};
                    if (rtCostRequest) {
                      let requestCost = null;
                      let requestCurrency = 'USD';
                      
                      // Prefer server-calculated cost from last_increment
                      if (lastIncrement.cost != null) {
                        requestCost = parseFloat(lastIncrement.cost);
                        requestCurrency = lastIncrement.currency || 'USD';
                      } else if (inTok != null && outTok != null) {
                        // Fallback: calculate client-side with default rates
                        const RATE_IN = 1.75;
                        const RATE_CACHED = 0.175;
                        const RATE_OUT = 14.00;
                        const inputTokens = typeof inTok === 'number' ? inTok : 0;
                        const outputTokens = typeof outTok === 'number' ? outTok : 0;
                        const cachedTokens = typeof cached === 'number' ? cached : 0;
                        const nonCachedInput = Math.max(0, inputTokens - cachedTokens);
                        const costIn = (nonCachedInput / 1_000_000) * RATE_IN;
                        const costCached = (cachedTokens / 1_000_000) * RATE_CACHED;
                        const costOut = (outputTokens / 1_000_000) * RATE_OUT;
                        requestCost = costIn + costCached + costOut;
                      }
                      
                      if (requestCost != null) {
                        rtCostRequest.textContent = `$${requestCost.toFixed(4)}`;
                      } else {
                        rtCostRequest.textContent = '—';
                      }
                    }

                    debugState.model = data?.model || debugState.model;
                    debugState.usage = usage;
                    // Akkumuliere Partial Usage (für den Fall dass der Stream abbricht)
                    debugState.partialUsage = {
                      input_tokens: (debugState.partialUsage?.input_tokens || 0) + (usage.input_tokens || 0),
                      output_tokens: (debugState.partialUsage?.output_tokens || 0) + (usage.output_tokens || 0),
                      cached_tokens: usage?.input_tokens_details?.cached_tokens ?? debugState.partialUsage?.cached_tokens ?? null,
                      reasoning_tokens: usage?.output_tokens_details?.reasoning_tokens ?? debugState.partialUsage?.reasoning_tokens ?? null,
                    };
                    updateDebugDump();
                    break;
                  }
                  case 'complete': {
                    debugState.streamCompleted = true;  // Markiere Stream als erfolgreich abgeschlossen
                    const assistant = data?.assistant || st.live.assistant || '';
                    const serverHistory = Array.isArray(data?.chat_history) ? data.chat_history : null;
                    if (serverHistory) {
                      const normalized = serverHistory
                        .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
                        .map((m) => ({ role: m.role, content: m.content }));
                      const hasAssistant = normalized.some((m) => m.role === 'assistant' && m.content === assistant);
                      if (!hasAssistant && assistant) {
                        normalized.push({ role: 'assistant', content: assistant });
                      }
                      st.messages = normalized;
                    } else {
                      st.messages = Array.isArray(st.messages) ? st.messages : [];
                      st.messages.push({ role: 'assistant', content: assistant });
                    }
                    // Mark stream as finished BEFORE triggering Livewire refresh
                    // This ensures the livewire:update handler knows to clean up temporary messages
                    st.inFlight = false;
                    if (isVisible) {
                      // After complete: clear streaming slot completely
                      try {
                        const slot = document.getElementById('pgStreamingSlot');
                        if (slot) slot.innerHTML = '';
                      } catch (_) {}
                      removeStreamingMetaMessages();
                      // Note: Debug window is NOT cleared here - it will be cleared on next send
                      // Trigger Livewire to reload messages from DB and show the new assistant message
                      refreshLivewireMessages();
                      // Note: overflow-anchor CSS handles auto-scroll now
                    }
                    st.continuation = data?.continuation || null;
                    if (rtStatus) rtStatus.textContent = 'done';
                    updateDebugDump();
                    try { st.live.assistant = ''; } catch (_) {}
                    try {
                      appendStreamLog('complete', `\n[complete] tid=${eventThreadId || '—'} assistant_len=${(assistant || '').length} msgs=${Array.isArray(st.messages) ? st.messages.length : '—'}\n`);
                    } catch (_) {}
                    break;
                  }
                  case 'error': {
                    const msg = data?.error || 'Unbekannter Fehler';
                    threadState.messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
                    // Note: Error messages are not saved to DB, so no need to refresh Livewire
                    // They're only shown in the debug panel
                    if (rtStatus) rtStatus.textContent = 'error';
                    updateDebugDump();
                    removeStreamingAssistantMessage();
                    removeStreamingMetaMessages();
                    break;
                  }
                  default:
                    // ignore
                }

                // Keep footer Stop + timer stable during streaming, even if Livewire re-renders the footer
                // (e.g. after the first tool call / model update). This is cheap and prevents "blinking".
                try {
                  updateThreadBusyIndicators();
                  updateFooterBusy();
                } catch (_) {}
                // Auto-scroll is handled by MutationObserver
              }
            }
          } catch (e) {
            // Abort is expected (Stop button)
            if (e && (e.name === 'AbortError' || threadState.userAborted)) {
              // Revert last user message from UI+history (the server also deletes it for this request).
              try {
                const idx = threadState._lastSentUserIndex;
                const last = (typeof idx === 'number' && idx >= 0) ? threadState.messages[idx] : null;
                if (last && last.role === 'user' && last.content === threadState._lastSentUserContent) {
                  threadState.messages.splice(idx, 1);
                } else if (threadState.messages?.length && threadState.messages[threadState.messages.length - 1]?.role === 'user') {
                  threadState.messages.pop();
                }
                if (threadState._lastSentUserEl && threadState._lastSentUserEl.parentNode) {
                  threadState._lastSentUserEl.parentNode.removeChild(threadState._lastSentUserEl);
                }
                threadState._lastSentUserEl = null;
                threadState._lastSentUserIndex = null;
                threadState._lastSentUserContent = null;
              } catch (_) {}

              // Capture abort info for debugging (user-initiated, not an error)
              debugState.streamError = {
                message: 'User aborted',
                name: 'AbortError',
                timestamp: new Date().toISOString(),
                lastEventType: debugState.lastEventType,
                bytesReceived: debugState.bytesReceived,
                sseEventsCount: debugState.sseEvents.length,
                userAborted: true,
              };
              updateDebugDump();

              // Show status via footer/event (no persistence in chat_history)
              try { setEventLabel('abgebrochen'); } catch (_) {}
              if (rtStatus) rtStatus.textContent = 'abgebrochen';
              // Clear any partial live stream buffers
              try {
                threadState.live.assistant = '';
                threadState.live.reasoning = '';
                threadState.live.thinking = '';
                if (rtAssistant) rtAssistant.textContent = '';
                if (rtReasoning) rtReasoning.textContent = '';
                if (rtThinking) rtThinking.textContent = '';
              } catch (_) {}
              try { removeStreamingMetaMessages(); } catch (_) {}
            } else {
              // Capture stream error info for debugging
              debugState.streamError = {
                message: e?.message || 'Unbekannter Fehler',
                name: e?.name || null,
                timestamp: new Date().toISOString(),
                lastEventType: debugState.lastEventType,
                bytesReceived: debugState.bytesReceived,
                sseEventsCount: debugState.sseEvents.length,
                toolCallsCount: debugState.toolCalls.length,
                lastToolCall: debugState.toolCalls.slice(-1)[0] || null,
                streamCompleted: debugState.streamCompleted,
              };
              updateDebugDump();

              threadState.messages.push({ role: 'assistant', content: `❌ Fehler: ${e?.message || 'Unbekannter Fehler'}` });
              renderMessage('assistant', `❌ Fehler: ${e?.message || 'Unbekannter Fehler'}`);
              if (rtStatus) rtStatus.textContent = 'error';
            }
          } finally {
            // Stop auto-scroll interval
            stopScrollInterval();
            threadState.inFlight = false;
            threadState.abortController = null;
            setSendButtonBusy(false);
            input.disabled = false;
            const ms = document.getElementById('modelSelect');
            if (ms) ms.disabled = false;
            input.focus();
            updateThreadBusyIndicators();
            updateFooterBusy();
            threadState.userAborted = false;
            // Note: overflow-anchor CSS handles auto-scroll now
          }
        };

        // Live elapsed timer for the inline busy chip (idempotent binding)
        window.__simplePlaygroundTimers = window.__simplePlaygroundTimers || {};
        if (!window.__simplePlaygroundTimers.inlineElapsedBound) {
          window.__simplePlaygroundTimers.inlineElapsedBound = true;
          setInterval(() => {
            try {
              const tid = refreshThreadIdFromDom();
              const st = getThreadState(tid || 'none');
              const el = document.getElementById('pgInlineSeconds');
              const elFooter = document.getElementById('pgFooterSeconds');
              if (!el && !elFooter) return;
              if (!st || !st.inFlight || !st.startedAtMs) {
                if (el) el.textContent = '0s';
                if (elFooter) elFooter.textContent = '0s';
                return;
              }
              const s = Math.max(0, Math.floor((Date.now() - st.startedAtMs) / 1000));
              const txt = formatElapsed(s);
              if (el) el.textContent = txt;
              if (elFooter) elFooter.textContent = txt;
            } catch (_) {}
          }, 250);
        }

        // Always bind submit handlers (ensure they work even after Livewire updates)
        // Use a wrapper function to ensure send() is accessible
        const bindSubmitHandlers = () => {
          const currentForm = document.getElementById('chatForm');
          const currentInput = document.getElementById('chatInput');
          const currentSendBtn = document.getElementById('chatSend');
          // Keep outer refs in sync with the currently rendered DOM
          form = currentForm;
          input = currentInput;
          sendBtn = currentSendBtn;
          chatList = document.getElementById('chatList');
          chatScroll = document.getElementById('simpleChatScroll');
          
          if (currentForm && !currentForm.dataset.submitBound) {
            currentForm.addEventListener('submit', (e) => { 
              e.preventDefault(); 
              e.stopPropagation();
              send(); 
            });
            currentForm.dataset.submitBound = '1';
          }
          
          if (currentInput && !currentInput.dataset.keydownBound) {
            currentInput.addEventListener('keydown', (e) => {
              if (e.key === 'Enter' && !e.shiftKey) { 
                e.preventDefault(); 
                e.stopPropagation();
                send(); 
              }
            });
            currentInput.dataset.keydownBound = '1';
          }
          
          if (currentSendBtn && !currentSendBtn.dataset.clickBound) {
            currentSendBtn.addEventListener('click', (e) => {
              e.preventDefault();
              e.stopPropagation();
              send();
            });
            currentSendBtn.dataset.clickBound = '1';
          }
        };
        
        bindSubmitHandlers();
        bindFooterHandlers();

        // File upload handling
        window.__simplePlaygroundAttachments = window.__simplePlaygroundAttachments || [];

        const bindFileUploadHandlers = () => {
          const fileInput = document.getElementById('pgFileInput');
          const fileBtn = document.getElementById('pgFileUploadBtn');
          const attachmentsPreview = document.getElementById('pgAttachmentsPreview');
          const attachmentCount = document.getElementById('pgAttachmentCount');
          const uploadProgress = document.getElementById('pgUploadProgress');

          if (fileBtn && !fileBtn.dataset.clickBound) {
            fileBtn.addEventListener('click', () => {
              if (fileInput) fileInput.click();
            });
            fileBtn.dataset.clickBound = '1';
          }

          if (fileInput && !fileInput.dataset.changeBound) {
            fileInput.addEventListener('change', async (e) => {
              const files = e.target.files;
              if (!files || files.length === 0) return;

              // Show upload progress
              if (uploadProgress) uploadProgress.classList.remove('hidden');
              if (attachmentCount) attachmentCount.classList.add('hidden');

              // Upload each file via Livewire
              for (const file of files) {
                try {
                  // Create FormData for upload
                  const formData = new FormData();
                  formData.append('file', file);
                  formData.append('thread_id', currentThreadId);

                  // Upload via API endpoint
                  const uploadUrl = window.__simplePlaygroundUploadUrl || '/api/core/playground/upload';
                  const res = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                      'X-CSRF-TOKEN': csrf,
                      'Accept': 'application/json',
                    },
                    body: formData,
                    credentials: 'same-origin',
                  });

                  if (!res.ok) {
                    const errData = await res.json().catch(() => ({}));
                    console.error('Upload failed:', errData);
                    continue;
                  }

                  const data = await res.json();
                  if (data.success && data.attachment) {
                    window.__simplePlaygroundAttachments.push(data.attachment);
                    updateAttachmentsPreview();
                  }
                } catch (err) {
                  console.error('Upload error:', err);
                }
              }

              // Hide progress, show count
              if (uploadProgress) uploadProgress.classList.add('hidden');
              updateAttachmentCount();

              // Reset input
              fileInput.value = '';
            });
            fileInput.dataset.changeBound = '1';
          }
        };

        const updateAttachmentsPreview = () => {
          const preview = document.getElementById('pgAttachmentsPreview');
          if (!preview) return;

          const attachments = window.__simplePlaygroundAttachments || [];
          if (attachments.length === 0) {
            preview.style.display = 'none';
            preview.innerHTML = '';
            return;
          }

          preview.style.display = 'flex';
          preview.innerHTML = attachments.map((att, idx) => {
            if (att.is_image) {
              return `
                <div class="relative group">
                  <img src="${att.url}" alt="${att.original_name}" class="w-16 h-16 object-cover rounded border border-[var(--ui-border)]" title="${att.original_name}" />
                  <button type="button" onclick="window.__simplePlaygroundRemoveAttachment(${idx})" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs hover:bg-red-600 opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                </div>
              `;
            } else {
              return `
                <div class="relative group flex items-center gap-2 px-2 py-1 rounded bg-[var(--ui-muted-5)] border border-[var(--ui-border)]">
                  <svg class="w-4 h-4 flex-shrink-0 text-[var(--ui-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  <span class="text-xs truncate max-w-[80px]" title="${att.original_name}">${att.original_name}</span>
                  <button type="button" onclick="window.__simplePlaygroundRemoveAttachment(${idx})" class="w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center text-[10px] hover:bg-red-600">×</button>
                </div>
              `;
            }
          }).join('');
        };

        const updateAttachmentCount = () => {
          const countEl = document.getElementById('pgAttachmentCount');
          const attachments = window.__simplePlaygroundAttachments || [];
          if (!countEl) return;

          if (attachments.length > 0) {
            countEl.textContent = attachments.length;
            countEl.classList.remove('hidden');
          } else {
            countEl.classList.add('hidden');
          }
        };

        window.__simplePlaygroundRemoveAttachment = (idx) => {
          const attachments = window.__simplePlaygroundAttachments || [];
          if (idx >= 0 && idx < attachments.length) {
            // Optionally call API to delete the file
            const att = attachments[idx];
            if (att.id) {
              fetch((window.__simplePlaygroundDeleteUrl || '/api/core/playground/attachment') + '/' + att.id, {
                method: 'DELETE',
                headers: {
                  'X-CSRF-TOKEN': csrf,
                  'Accept': 'application/json',
                },
                credentials: 'same-origin',
              }).catch(() => {});
            }
            attachments.splice(idx, 1);
            window.__simplePlaygroundAttachments = attachments;
            updateAttachmentsPreview();
            updateAttachmentCount();
          }
        };

        window.__simplePlaygroundClearAttachments = () => {
          window.__simplePlaygroundAttachments = [];
          updateAttachmentsPreview();
          updateAttachmentCount();
        };

        window.__simplePlaygroundGetAttachmentIds = () => {
          return (window.__simplePlaygroundAttachments || []).map(a => a.id).filter(Boolean);
        };

        bindFileUploadHandlers();

        // Re-bind after Livewire updates
        document.addEventListener('livewire:update', () => {
          setTimeout(() => {
            bindSubmitHandlers();
            bindFooterHandlers();
            bindFileUploadHandlers();
            // After DOM re-render, recompute busy state so Stop doesn't "disappear" (disabled) mid-stream.
            updateThreadBusyIndicators();
            updateFooterBusy();
            try {
              const last = window.__simplePlaygroundLastEvent?.key;
              if (last) setEventLabel(last);
            } catch (_) {}
            // Note: overflow-anchor CSS handles auto-scroll now
          }, 50);
        });
      };

      // Expose boot for modal-open refresh (Livewire opens modal after initial page load)
      window.__simplePlaygroundBoot = boot;
      // Wake is safe to call on every modal open: it only re-syncs DOM refs & indicators.
      window.__simplePlaygroundWake = () => {
        try {
          // Re-bind submit handlers after Livewire has re-rendered
          const f = document.getElementById('chatForm');
          if (f) {
            // Trigger the existing rebind pipeline by emitting livewire:update
            // (bindSubmitHandlers is in the boot closure; this is best-effort)
          }
          // Update thread busy dots from global store
          const dots = document.querySelectorAll('[data-thread-busy]');
          dots.forEach((el) => {
            const tid = el.getAttribute('data-thread-busy');
            const st = (window.__simplePlaygroundThreadStore || {})[String(tid || 'none')];
            if (st && st.inFlight) el.classList.remove('hidden');
            else el.classList.add('hidden');
          });
          // Also ensure footer Stop reflects current in-flight state when reopening modal.
          try { updateFooterBusy(); } catch (_) {}
          try {
            const last = window.__simplePlaygroundLastEvent?.key;
            if (last) setEventLabel(last);
          } catch (_) {}
        } catch (_) {}
      };

      const ensureBootSoon = () => {
        // Livewire opens the modal after a roundtrip; ensure boot runs once the DOM is actually there.
        if (window.__simplePlaygroundBootedOnce) {
          // Just re-bind handlers / refresh indicators when reopening; do not reset JS state.
          try { if (typeof window.__simplePlaygroundWake === 'function') window.__simplePlaygroundWake(); } catch (_) {}
          return;
        }
        let tries = 0;
        const t = setInterval(() => {
          tries++;
          if (document.getElementById('chatForm')) {
            try { if (typeof window.__simplePlaygroundBoot === 'function') window.__simplePlaygroundBoot(); } catch (_) {}
            window.__simplePlaygroundBootedOnce = true;
            clearInterval(t);
          }
          if (tries > 80) clearInterval(t);
        }, 50);
      };

      if (!window.__simplePlaygroundModalOpenBound) {
        window.__simplePlaygroundModalOpenBound = true;
        window.addEventListener('simple-playground-modal-opened', () => {
          ensureBootSoon();
        });
        // Also listen to the user action that triggers the modal (always bubbles from the navbar button).
        window.addEventListener('playground:open', () => {
          ensureBootSoon();
        });
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
      } else {
        boot();
      }
    })();
    </script>
</div>


