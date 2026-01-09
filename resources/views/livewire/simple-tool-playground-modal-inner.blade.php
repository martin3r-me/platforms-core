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
        <div class="h-full min-h-0 border border-[var(--ui-border)] rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] flex-shrink-0">Chat</div>
                    {{-- Thread Tabs --}}
                    <div class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto">
                        @if(isset($threads) && $threads->count() > 0)
                            @foreach($threads as $t)
                                <div
                                    class="relative flex-shrink-0 flex items-center"
                                    x-data="{ editing: false, title: '{{ addslashes($t->title) }}' }"
                                >
                                    <button
                                        type="button"
                                        wire:click="switchThread({{ $t->id }})"
                                        x-show="!editing"
                                        class="px-2 py-1 rounded text-[11px] border transition whitespace-nowrap flex items-center gap-1 {{ ($activeThreadId ?? null) == $t->id ? 'bg-[var(--ui-primary)] text-white border-[var(--ui-primary)]' : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]' }}"
                                    >
                                        <span x-text="title"></span>
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
                {{-- Usage stats: compact, right-aligned - Thread totals + Request totals --}}
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    {{-- Thread totals (Gesamt) --}}
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[9px] text-[var(--ui-muted)]">In:</span>
                        <span id="rtTokensInTotal" class="text-[10px] font-semibold text-[var(--ui-secondary)]">
                            @if(isset($activeThread) && $activeThread)
                                {{ number_format($activeThread->total_tokens_in ?? 0) }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[9px] text-[var(--ui-muted)]">C/R:</span>
                        <span id="rtTokensExtraTotal" class="text-[10px] font-semibold text-[var(--ui-secondary)]">
                            @if(isset($activeThread) && $activeThread)
                                {{ number_format(($activeThread->total_tokens_cached ?? 0) + ($activeThread->total_tokens_reasoning ?? 0)) }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[9px] text-[var(--ui-muted)]">Out:</span>
                        <span id="rtTokensOutTotal" class="text-[10px] font-semibold text-[var(--ui-secondary)]">
                            @if(isset($activeThread) && $activeThread)
                                {{ number_format($activeThread->total_tokens_out ?? 0) }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <span class="text-[9px] text-[var(--ui-muted)]">$:</span>
                        <span id="rtCostTotal" class="text-[10px] font-semibold text-[var(--ui-secondary)]">
                            @if(isset($activeThread) && $activeThread)
                                {{ number_format((float)($activeThread->total_cost ?? 0), 4) }} {{ $activeThread->pricing_currency ?? 'USD' }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    {{-- Separator --}}
                    <div class="w-px h-4 bg-[var(--ui-border)]/60"></div>
                    {{-- Request totals (aktueller Request) --}}
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-bg)]/50">
                        <span class="text-[9px] text-[var(--ui-muted)]">Req In:</span>
                        <span id="rtTokensIn" class="text-[10px] font-semibold text-[var(--ui-secondary)]">—</span>
                    </div>
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-bg)]/50">
                        <span class="text-[9px] text-[var(--ui-muted)]">Req C/R:</span>
                        <span id="rtTokensExtra" class="text-[10px] font-semibold text-[var(--ui-secondary)]">—</span>
                    </div>
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-bg)]/50">
                        <span class="text-[9px] text-[var(--ui-muted)]">Req Out:</span>
                        <span id="rtTokensOut" class="text-[10px] font-semibold text-[var(--ui-secondary)]">—</span>
                    </div>
                    <div class="flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/40 bg-[var(--ui-bg)]/50">
                        <span class="text-[9px] text-[var(--ui-muted)]">Req $:</span>
                        <span id="rtCostRequest" class="text-[10px] font-semibold text-[var(--ui-secondary)]">—</span>
                    </div>
                </div>
            </div>
            <div class="flex-1 min-h-0 overflow-auto">
                <div class="w-full h-full min-h-0 flex gap-5 px-4" style="width:100%; max-width:100%;">

    {{-- Left: Chat (3/4 width) --}}
    <div class="flex-[3_1_0%] min-h-0 min-w-0 flex flex-col flex-shrink" style="max-width:75%;">
        <div class="flex-1 min-h-0 border border-[var(--ui-border)]/80 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm">
            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4" id="chatScroll">
                <div id="chatList" class="space-y-4"></div>
                <div id="chatEmpty" class="text-sm text-[var(--ui-muted)]">
                    <div class="font-semibold text-[var(--ui-secondary)]">Start</div>
                    <div class="mt-1">Schreib eine Nachricht (z. B. „Liste meine Teams“ oder „Welche Tools kann ich im OKR-Modul nutzen?“).</div>
                    <div class="mt-3 text-xs">
                        Tipp: Links kannst du Models per Drag&Drop wählen, und direkt neben dem Input pro Request wechseln.
                    </div>
                </div>
            </div>
            <div class="border-t border-[var(--ui-border)]/60 p-3 flex-shrink-0 bg-[var(--ui-surface)]">
                <form id="chatForm" class="flex gap-2 items-center" method="post" action="javascript:void(0)" onsubmit="return false;">
                    <div class="w-48">
                        <x-ui-input-select
                            name="modelSelect"
                            id="modelSelect"
                            :options="$modelOptions ?? []"
                            :nullable="false"
                            size="sm"
                            :value="$activeThreadModel ?? $defaultModelId ?? 'gpt-5.2'"
                            class="w-full"
                        />
                    </div>
                    <input
                        id="chatInput"
                        type="text"
                        class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
                        placeholder="Nachricht eingeben…"
                        autocomplete="off"
                    />
                    <button id="chatSend" type="submit" class="px-6 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90">
                        Senden
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Right: Realtime / Debug (1/4 width) --}}
    <div class="flex-[1_1_0%] min-h-0 min-w-0 flex-shrink border border-[var(--ui-border)]/80 rounded-xl bg-[var(--ui-surface)] overflow-hidden flex flex-col shadow-sm" style="max-width:25%;">
        <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
            <div class="text-xs text-[var(--ui-muted)]">
                Model: <span id="realtimeModel" class="text-[var(--ui-secondary)]">—</span>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs text-[var(--ui-muted)] inline-flex items-center gap-2 select-none">
                    <input id="rtVerbose" type="checkbox" class="accent-[var(--ui-primary)]" />
                    verbose
                </label>
                <button id="realtimeClear" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Clear</button>
            </div>
        </div>

        <div class="p-4 space-y-4 flex-1 min-h-0 overflow-y-auto">
            <div>
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Assistant (live)</div>
                <pre id="rtAssistant" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[80px] max-h-[22vh] overflow-y-auto"></pre>
            </div>

            {{-- Tokens & costs moved to the top bar --}}

            <div>
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Tool Calls (letzte 10)</div>
                <div id="rtToolCalls" class="space-y-2 max-h-[30vh] overflow-y-auto"></div>
            </div>
            <div>
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Reasoning (live)</div>
                <pre id="rtReasoning" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[60px] max-h-[16vh] overflow-y-auto"></pre>
            </div>
            <div>
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Thinking (live)</div>
                <pre id="rtThinking" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[60px] max-h-[16vh] overflow-y-auto"></pre>
            </div>
            <div class="pt-2 border-t border-[var(--ui-border)]">
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Events</div>
                <div id="rtEvents" class="text-xs space-y-2 text-[var(--ui-muted)] max-h-[16vh] overflow-y-auto pr-1"></div>
            </div>

            <div class="pt-2 border-t border-[var(--ui-border)]">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-xs font-semibold text-[var(--ui-secondary)]">Debug Dump</div>
                    <button id="rtCopyDebug" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Copy</button>
                </div>
                <textarea id="rtDebugDump" class="w-full text-[10px] leading-snug whitespace-pre border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[90px] max-h-[18vh] overflow-y-auto" readonly></textarea>
                <div id="rtCopyStatus" class="mt-1 text-[10px] text-[var(--ui-muted)]"></div>
            </div>
        </div>
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
                <div><span class="font-semibold text-[var(--ui-secondary)]">Debug:</span> rechts bleibt immer sichtbar (Assistant/Reasoning/Thinking/Tool Calls).</div>
                <div class="text-xs">Wenn du willst, kann ich Debug/Events auch in den Settings-Tab verschieben und den Chat-Tab noch „cleaner“ machen.</div>
            </div>
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

                {{-- Model selection moved here (left column removed from Chat tab) --}}
                <div class="grid grid-cols-12 gap-4 mb-4">
                    <div class="col-span-12 lg:col-span-4 min-h-0 border border-[var(--ui-border)] rounded-xl bg-[var(--ui-bg)] overflow-hidden">
                        <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Model Auswahl</div>
                            <button id="modelsReload" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Reload</button>
                        </div>
                        <div class="p-4 space-y-4">
                            <div>
                                <div class="text-xs text-[var(--ui-muted)] mb-1">Ausgewählt (Drop Zone)</div>
                                <div id="modelDropZone" class="min-h-[44px] px-3 py-2 rounded border border-dashed border-[var(--ui-border)] bg-[var(--ui-surface)] text-sm">
                                    <span id="selectedModelLabel" class="text-[var(--ui-secondary)]">—</span>
                                </div>
                                <div class="mt-2 text-xs text-[var(--ui-muted)]">Drag ein Model aus der Liste hier rein (oder Doppelklick).</div>
                            </div>

                            <div class="pt-2 border-t border-[var(--ui-border)]">
                                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">
                                    Verfügbare Models
                                    <span class="ml-2 text-[10px] font-normal text-[var(--ui-muted)]">
                                        (DB: {{ ($coreAiModels ?? collect())->count() }})
                                    </span>
                                </div>
                                <div id="modelsList" class="space-y-2 max-h-[40vh] overflow-y-auto pr-1">
                                    {{-- Server-side fallback (JS will replace on loadModels()) --}}
                                    @if(($coreAiModels ?? collect())->count() > 0)
                                        @foreach(($coreAiModels ?? collect())->unique('model_id') as $mm)
                                            <div class="flex items-center justify-between gap-2 px-3 py-2 rounded border border-[var(--ui-border)] bg-[var(--ui-surface)] text-sm">
                                                <span class="truncate">{{ $mm->model_id }}</span>
                                                <span class="text-[10px] text-[var(--ui-muted)]">{{ $mm->provider?->key ?? '—' }}</span>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="text-xs text-[var(--ui-muted)]">Keine Models in DB – bitte zuerst syncen.</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-span-12 lg:col-span-8 text-xs text-[var(--ui-muted)]">
                        Hier pflegst du Preise/Default-Model. Die Auswahl wirkt sich direkt auf den Chat (Dropdown neben Input) und den Default aus.
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
                                    <th class="text-left py-2 pr-4 font-semibold">Context</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Max output</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Cutoff</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Pricing (manuell)</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Features</th>
                                    <th class="text-left py-2 pr-4 font-semibold">Default</th>
                                    <th class="text-left py-2 pr-0 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-[var(--ui-secondary)]">
                                @foreach(($coreAiModels ?? collect()) as $m)
                                    @php
                                        $p = $m->provider?->key ?? '—';
                                        $isDefault = (int)($m->provider?->default_model_id ?? 0) === (int)$m->id;
                                        $features = [];
                                        if ($m->supports_reasoning_tokens) $features[] = 'reasoning';
                                        if ($m->supports_streaming) $features[] = 'stream';
                                        if ($m->supports_function_calling) $features[] = 'tools';
                                        if ($m->supports_structured_outputs) $features[] = 'json';
                                    @endphp
                                    <tr class="border-b border-[var(--ui-border)]/40">
                                        <td class="py-2 pr-4">{{ $p }}</td>
                                        <td class="py-2 pr-4">
                                            <div class="font-semibold">{{ $m->name }}</div>
                                            <div class="text-[10px] text-[var(--ui-muted)]">{{ $m->model_id }}</div>
                                        </td>
                                        <td class="py-2 pr-4">{{ $m->context_window ? number_format($m->context_window) : '—' }}</td>
                                        <td class="py-2 pr-4">{{ $m->max_output_tokens ? number_format($m->max_output_tokens) : '—' }}</td>
                                        <td class="py-2 pr-4">{{ $m->knowledge_cutoff_date ? $m->knowledge_cutoff_date->format('Y-m-d') : '—' }}</td>
                                        <td class="py-2 pr-4 align-top">
                                            <div class="grid grid-cols-4 gap-2 items-end min-w-[420px]">
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Währung</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs"
                                                        wire:model.defer="pricingEdits.{{ $m->id }}.pricing_currency"
                                                        maxlength="3"
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Input / 1M</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs"
                                                        wire:model.defer="pricingEdits.{{ $m->id }}.price_input_per_1m"
                                                        inputmode="decimal"
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Cached / 1M</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs"
                                                        wire:model.defer="pricingEdits.{{ $m->id }}.price_cached_input_per_1m"
                                                        inputmode="decimal"
                                                    />
                                                </div>
                                                <div>
                                                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Output / 1M</div>
                                                    <input
                                                        class="w-full px-2 py-1 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-xs"
                                                        wire:model.defer="pricingEdits.{{ $m->id }}.price_output_per_1m"
                                                        inputmode="decimal"
                                                    />
                                                </div>
                                                <div class="col-span-4 flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="px-3 py-1.5 rounded-md bg-[var(--ui-primary)] text-white text-xs hover:bg-opacity-90"
                                                        wire:click="saveModelPricing({{ $m->id }})"
                                                    >
                                                        Speichern
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2 pr-4 text-[10px] text-[var(--ui-muted)]">
                                            {{ count($features) ? implode(', ', $features) : '—' }}
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
                                        <td class="py-2 pr-0">
                                            @if($m->is_deprecated)
                                                <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-danger-5)] text-[var(--ui-danger)]">deprecated</span>
                                            @elseif(!$m->is_active)
                                                <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">inactive</span>
                                            @else
                                                <span class="text-[10px] px-2 py-1 rounded bg-[var(--ui-primary-10)] text-[var(--ui-primary)]">active</span>
                                            @endif
                                        </td>
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

        const chatList = document.getElementById('chatList');
        const chatScroll = document.getElementById('chatScroll');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSend');

        // Idempotent binding (modal exists globally; avoid duplicate listeners).
        // IMPORTANT: even when already bound, we still refresh models so the UI is usable right after opening.
        const alreadyBound = (form?.dataset?.simplePlaygroundBound === '1');
        if (!form) return;
        if (!alreadyBound) form.dataset.simplePlaygroundBound = '1';

        const realtimeClear = document.getElementById('realtimeClear');
        const realtimeModel = document.getElementById('realtimeModel');
        const rtAssistant = document.getElementById('rtAssistant');
        const rtReasoning = document.getElementById('rtReasoning');
        const rtThinking = document.getElementById('rtThinking');
        const rtEvents = document.getElementById('rtEvents');
        const rtStatus = document.getElementById('rtStatus');
        const rtVerboseEl = document.getElementById('rtVerbose');
        const rtDebugDump = document.getElementById('rtDebugDump');
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
        const rtCostRequest = document.getElementById('rtCostRequest');
        const rtCostNote = document.getElementById('rtCostNote');
        const rtToolCalls = document.getElementById('rtToolCalls');

        /** type: {role:'user'|'assistant', content:string}[] */
        let messages = [];
        let currentThreadId = null;
        /** continuation state for interrupted tool-loops */
        let continuation = null;
        let inFlight = false;
        
        // Initialize with default model or thread model
        let selectedModel = activeThreadModel || defaultModelId;
        if (!selectedModel) {
          selectedModel = localStorage.getItem('simple.selectedModel') || '';
        }
        if (!selectedModel) {
          selectedModel = defaultModelId;
        }
        localStorage.setItem('simple.selectedModel', selectedModel);

        const scrollToBottom = () => { chatScroll.scrollTop = chatScroll.scrollHeight; };
        
        // Helper: format numbers
        const formatNumber = (n) => {
          if (n == null || n === '' || Number.isNaN(n)) return '—';
          return new Intl.NumberFormat('de-DE').format(Number(n));
        };

        const renderMessage = (role, content) => {
          const wrap = document.createElement('div');
          wrap.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'}`;
          wrap.innerHTML = `
            <div class="max-w-4xl rounded-lg p-3 ${role === 'user' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-surface)] border border-[var(--ui-border)]'}">
              <div class="text-sm font-semibold mb-1">${role === 'user' ? 'Du' : 'Assistant'}</div>
              <div class="whitespace-pre-wrap"></div>
            </div>
          `;
          wrap.querySelector('.whitespace-pre-wrap').textContent = content;
          chatList.appendChild(wrap);
          const empty = document.getElementById('chatEmpty');
          if (empty) empty.style.display = 'none';
          scrollToBottom();
        };

        const refreshThreadIdFromDom = () => {
          const el = document.getElementById('pgActiveThreadId');
          const raw = (el && typeof el.value === 'string') ? el.value : '';
          const n = parseInt(raw || '', 10);
          currentThreadId = Number.isFinite(n) ? n : null;
          return currentThreadId;
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
          model: null,
          events: [],
          lastAssistant: '',
          toolCalls: [],
          toolsVisible: null,
        };

        const updateDebugDump = () => {
          if (!rtDebugDump) return;
          const out = {
            startedAt: debugState.startedAt,
            payload: debugState.payload,
            usage: debugState.usage,
            model: debugState.model,
            lastAssistant: debugState.lastAssistant,
            events: debugState.events.slice(-120),
            toolCalls: debugState.toolCalls.slice(-20),
            toolsVisible: debugState.toolsVisible,
          };
          rtDebugDump.value = JSON.stringify(out, null, 2);
        };

        if (rtCopyDebug) {
          rtCopyDebug.addEventListener('click', async () => {
            try {
              await navigator.clipboard.writeText(rtDebugDump?.value || '');
              if (rtCopyStatus) rtCopyStatus.textContent = 'kopiert';
              setTimeout(() => { if (rtCopyStatus) rtCopyStatus.textContent = ''; }, 1500);
            } catch (e) {
              if (rtCopyStatus) rtCopyStatus.textContent = 'copy fehlgeschlagen';
            }
          });
        }

        const rtEvent = ({ key, preview = null, raw = null }) => {
          if (!key) return;
          const eventKey = `${key}:${preview?.type || ''}:${preview?.id || ''}:${preview?.name || ''}`;
          if (eventKey === lastEventKey && lastEventSummaryEl) {
            lastEventCount++;
            lastEventSummaryEl.textContent = `${key} ×${lastEventCount}`;
          } else {
            lastEventKey = eventKey;
            lastEventCount = 1;
            const row = document.createElement('div');
            row.className = 'border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)]';
            const summary = document.createElement('div');
            summary.className = 'font-mono text-[10px] text-[var(--ui-secondary)]';
            summary.textContent = key;
            row.appendChild(summary);
            if (rtVerbose && raw) {
              const pre = document.createElement('pre');
              pre.className = 'mt-1 text-[10px] whitespace-pre-wrap text-[var(--ui-muted)]';
              pre.textContent = String(raw);
              row.appendChild(pre);
            }
            rtEvents.appendChild(row);
            lastEventSummaryEl = summary;
          }
          while (rtEvents.children.length > maxEventItems) rtEvents.removeChild(rtEvents.firstChild);
          rtEvents.scrollTop = rtEvents.scrollHeight;
        };

        const resetRealtime = () => {
          if (rtAssistant) rtAssistant.textContent = '';
          if (rtReasoning) rtReasoning.textContent = '';
          if (rtThinking) rtThinking.textContent = '';
          if (rtEvents) rtEvents.innerHTML = '';
          if (rtToolCalls) rtToolCalls.innerHTML = '';
          if (rtTokensIn) rtTokensIn.textContent = '—';
          if (rtTokensOut) rtTokensOut.textContent = '—';
          if (rtTokensTotal) rtTokensTotal.textContent = '—';
          if (rtTokensExtra) rtTokensExtra.textContent = '—';
          if (rtCostIn) rtCostIn.textContent = '—';
          if (rtCostCached) rtCostCached.textContent = '—';
          if (rtCostOut) rtCostOut.textContent = '—';
          if (rtCostTotal) rtCostTotal.textContent = '—';
          if (rtStatus) rtStatus.textContent = 'idle';
          debugState.startedAt = null;
          debugState.payload = null;
          debugState.usage = null;
          debugState.model = null;
          debugState.events = [];
          debugState.lastAssistant = '';
          debugState.toolCalls = [];
          debugState.toolsVisible = null;
          updateDebugDump();
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

        // Thread change handler: load messages from DB and update totals
        const loadThreadMessages = async (threadId) => {
          if (!threadId) {
            messages = [];
            chatList.innerHTML = '';
            const empty = document.getElementById('chatEmpty');
            if (empty) empty.style.display = '';
            // Reset totals and model
            if (rtTokensInTotal) rtTokensInTotal.textContent = '—';
            if (rtTokensOutTotal) rtTokensOutTotal.textContent = '—';
            if (rtTokensExtraTotal) rtTokensExtraTotal.textContent = '—';
            if (rtCostTotal) rtCostTotal.textContent = '—';
            if (rtTokensIn) rtTokensIn.textContent = '—';
            if (rtTokensOut) rtTokensOut.textContent = '—';
            if (rtTokensExtra) rtTokensExtra.textContent = '—';
            if (rtCostRequest) rtCostRequest.textContent = '—';
            setSelectedModel(''); // Clear model selection
            currentThreadId = null;
            return;
          }

          try {
            currentThreadId = threadId;
            
            // Load messages from Livewire
            let threadMessages = [];
            if (window.Livewire) {
              const result = await window.Livewire.find(livewireComponentId).call('getThreadMessages', threadId);
              if (result && Array.isArray(result)) {
                threadMessages = result;
              }
            }
            
            // Clear and reload messages
            messages = threadMessages;
            chatList.innerHTML = '';
            const empty = document.getElementById('chatEmpty');
            if (messages.length === 0) {
              if (empty) empty.style.display = '';
            } else {
              if (empty) empty.style.display = 'none';
              // Render all messages
              messages.forEach(msg => {
                renderMessage(msg.role, msg.content);
              });
              scrollToBottom();
            }
            
            // Update totals from server-side rendered values (they will be updated by Livewire re-render)
            // The totals are already set in the HTML from server-side rendering
          } catch (e) {
            console.error('Failed to load thread messages:', e);
            messages = [];
            chatList.innerHTML = '';
            const empty = document.getElementById('chatEmpty');
            if (empty) empty.style.display = '';
          }
        };

        // Listen for thread changes from Livewire
        window.addEventListener('simple-playground:thread-changed', (e) => {
          const threadIdRaw = e.detail?.thread_id;
          const threadId = (typeof threadIdRaw === 'number') ? threadIdRaw : parseInt(String(threadIdRaw || ''), 10);
          if (!Number.isFinite(threadId)) return;
          // Livewire re-render updates hidden input; refresh from DOM to stay consistent
          refreshThreadIdFromDom();
          if (threadId !== currentThreadId) {
            currentThreadId = threadId;
          }
          loadThreadMessages(threadId);
        });

        // When the modal is opened / Livewire re-renders, refresh the thread id from DOM and load its messages.
        refreshThreadIdFromDom();
        if (currentThreadId) {
          loadThreadMessages(currentThreadId);
        }
        document.addEventListener('livewire:update', () => {
          const before = currentThreadId;
          refreshThreadIdFromDom();
          if (currentThreadId && currentThreadId !== before) {
            loadThreadMessages(currentThreadId);
          }
        });

        // Send (parity with page playground: chat updates only on complete)
        const send = async () => {
          const text = (input?.value || '').trim();
          if (inFlight) return;

          const canContinue = !!(continuation && continuation.pending);
          const isContinue = (!text && canContinue);
          if (!text && !isContinue) return;
          if (text && canContinue) {
            messages.push({ role: 'assistant', content: '⚠️ Es gibt noch einen laufenden Prozess. Bitte zuerst einmal mit leerer Eingabe fortsetzen (Enter), danach kannst du die nächste Frage senden.' });
            renderMessage('assistant', '⚠️ Es gibt noch einen laufenden Prozess. Bitte zuerst einmal mit leerer Eingabe fortsetzen (Enter), danach kannst du die nächste Frage senden.');
            return;
          }

          if (!isContinue) {
            messages.push({ role: 'user', content: text });
            renderMessage('user', text);
            input.value = '';
          }

          inFlight = true;
          sendBtn.disabled = true;
          input.disabled = true;
          resetRealtime();
          if (rtStatus) rtStatus.textContent = 'streaming…';
          if (realtimeModel) realtimeModel.textContent = selectedModel || '—';
          debugState.startedAt = new Date().toISOString();

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

          // Ensure we always send a thread_id so DB persistence + usage totals work.
          if (!currentThreadId) {
            refreshThreadIdFromDom();
          }
          if (!currentThreadId) {
            renderMessage('assistant', '⚠️ Kein aktiver Thread gefunden. Bitte einmal einen Thread anlegen oder neu auswählen.');
            inFlight = false;
            sendBtn.disabled = false;
            input.disabled = false;
            return;
          }

          const payload = {
            message: (isContinue ? '' : text),
            chat_history: messages,
            thread_id: currentThreadId,
            model: modelToUse,
            continuation: (isContinue ? continuation : null),
            context: ctx || null,
          };
          debugState.payload = payload;
          updateDebugDump();

          try {
            const res = await fetch(url, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrf },
              body: JSON.stringify(payload),
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

            while (true) {
              const { done, value } = await reader.read();
              if (done) break;
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

                switch (currentEvent) {
                  case 'assistant.delta':
                    if (data?.delta) rtAssistant.textContent += data.delta;
                    debugState.lastAssistant = rtAssistant.textContent;
                    break;
                  case 'reasoning.delta':
                    if (data?.delta) rtReasoning.textContent += data.delta;
                    break;
                  case 'thinking.delta':
                    if (data?.delta) rtThinking.textContent += data.delta;
                    break;
                  case 'debug.tools':
                    debugState.toolsVisible = data || null;
                    updateDebugDump();
                    break;
                  case 'tool.executed':
                    debugState.toolCalls.push(data);
                    if (rtToolCalls) {
                      const items = debugState.toolCalls.slice(-10).reverse();
                      rtToolCalls.innerHTML = '';
                      for (const it of items) {
                        const row = document.createElement('div');
                        row.className = 'border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)]';
                        const statusClass = it.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]';
                        const statusIcon = it.success ? '✅' : '❌';
                        const statusText = it.success ? 'Erfolgreich' : 'Fehlgeschlagen';
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
                    }
                    updateDebugDump();
                    break;
                  case 'openai.event': {
                    const ev = data?.event || 'openai.event';
                    rtEvent({ key: ev, preview: data?.preview || null, raw: data?.raw || null });
                    debugState.events.push({ t: Date.now(), event: ev, preview: data?.preview || null });
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
                        rtCostTotal.textContent = `$${cost.toFixed(4)} ${currency}`;
                      }
                    }
                    
                    // Update request values (aktueller Request) - right side (always update during stream)
                    if (rtTokensIn) rtTokensIn.textContent = (inTok != null ? formatNumber(inTok) : '—');
                    if (rtTokensOut) rtTokensOut.textContent = (outTok != null ? formatNumber(outTok) : '—');
                    const cachedReq = (cached != null ? cached : 0) + (reasoning != null ? reasoning : 0);
                    if (rtTokensExtra) rtTokensExtra.textContent = cachedReq > 0 ? formatNumber(cachedReq) : '—';
                    
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
                    updateDebugDump();
                    break;
                  }
                  case 'complete': {
                    const assistant = data?.assistant || rtAssistant.textContent;
                    messages.push({ role: 'assistant', content: assistant });
                    renderMessage('assistant', assistant);
                    continuation = data?.continuation || null;
                    if (rtStatus) rtStatus.textContent = 'done';
                    updateDebugDump();
                    break;
                  }
                  case 'error': {
                    const msg = data?.error || 'Unbekannter Fehler';
                    messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
                    renderMessage('assistant', `❌ Fehler: ${msg}`);
                    if (rtStatus) rtStatus.textContent = 'error';
                    updateDebugDump();
                    break;
                  }
                  default:
                    // ignore
                }
                scrollToBottom();
              }
            }
          } finally {
            inFlight = false;
            sendBtn.disabled = false;
            input.disabled = false;
            input.focus();
          }
        };

        // Always bind submit handlers (ensure they work even after Livewire updates)
        // Use a wrapper function to ensure send() is accessible
        const bindSubmitHandlers = () => {
          const currentForm = document.getElementById('chatForm');
          const currentInput = document.getElementById('chatInput');
          const currentSendBtn = document.getElementById('chatSend');
          
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
        
        // Re-bind after Livewire updates
        document.addEventListener('livewire:update', () => {
          setTimeout(bindSubmitHandlers, 50);
        });
      };

      // Expose boot for modal-open refresh (Livewire opens modal after initial page load)
      window.__simplePlaygroundBoot = boot;

      const ensureBootSoon = () => {
        // Livewire opens the modal after a roundtrip; ensure boot runs once the DOM is actually there.
        let tries = 0;
        const t = setInterval(() => {
          tries++;
          if (document.getElementById('chatForm')) {
            try { if (typeof window.__simplePlaygroundBoot === 'function') window.__simplePlaygroundBoot(); } catch (_) {}
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


