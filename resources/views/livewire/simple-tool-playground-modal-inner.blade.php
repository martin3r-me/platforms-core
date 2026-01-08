<div x-data="{ tab: 'chat' }" class="h-full min-h-0 overflow-hidden flex flex-col">
    <div class="flex items-center gap-2 px-4 py-3 border-b border-[var(--ui-border)]/60 bg-[var(--ui-surface)] flex-shrink-0">
        <button type="button"
            @click="tab='chat'"
            class="px-3 py-1.5 rounded-md text-sm border transition"
            :class="tab==='chat' ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border-[var(--ui-primary)]/30' : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]'">
            Chat
        </button>
        <button type="button"
            @click="tab='settings'"
            class="px-3 py-1.5 rounded-md text-sm border transition"
            :class="tab==='settings' ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border-[var(--ui-primary)]/30' : 'bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]'">
            Settings
        </button>
        <div class="flex-1"></div>
        <div class="text-xs text-[var(--ui-muted)] truncate">
            Kontext: <span id="pgContextLabel" class="text-[var(--ui-secondary)]">—</span>
        </div>
        <div class="text-xs text-[var(--ui-muted)] ml-3">
            Stream: <span id="rtStatus" class="text-[var(--ui-secondary)]">idle</span>
        </div>
    </div>

    <div class="flex-1 min-h-0 overflow-hidden p-4">
    <div x-show="tab==='chat'" class="h-full min-h-0 grid grid-cols-12 gap-4" x-cloak>
    {{-- Left: Model selection (independent scroll) --}}
        <div class="col-span-3 min-h-0 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] overflow-hidden flex flex-col">
            <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-shrink-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Model</div>
                <button id="modelsReload" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Reload</button>
            </div>
            <div class="p-4 space-y-4 flex-1 min-h-0 overflow-y-auto">
                <div>
                    <div class="text-xs text-[var(--ui-muted)] mb-1">Ausgewählt (Drop Zone)</div>
                    <div id="modelDropZone" class="min-h-[44px] px-3 py-2 rounded border border-dashed border-[var(--ui-border)] bg-[var(--ui-bg)] text-sm">
                        <span id="selectedModelLabel" class="text-[var(--ui-secondary)]">—</span>
                    </div>
                    <div class="mt-2 text-xs text-[var(--ui-muted)]">Drag ein Model aus der Liste hier rein (oder Doppelklick).</div>
                </div>

                <div class="pt-2 border-t border-[var(--ui-border)]">
                    <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Verfügbare Models</div>
                    <div id="modelsList" class="space-y-2 overflow-y-auto pr-1"></div>
                </div>
            </div>
        </div>

    {{-- Center: Chat (independent scroll + input pinned to bottom) --}}
    <div x-show="tab==='chat'" class="col-span-6 min-h-0 flex flex-col" x-cloak>
        <div class="flex-1 min-h-0 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] overflow-hidden flex flex-col">
            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4" id="chatScroll">
                <div id="chatList" class="space-y-4"></div>
            </div>
            <div class="border-t border-[var(--ui-border)]/60 p-3 flex-shrink-0 bg-[var(--ui-surface)]">
                <form id="chatForm" class="flex gap-2 items-center" method="post" action="javascript:void(0)" onsubmit="return false;">
                    <select id="modelSelect" class="w-48 px-3 py-2 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-sm"></select>
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

    {{-- Right: Realtime / Debug (independent scroll) --}}
    <div class="col-span-3 min-h-0 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] overflow-hidden flex flex-col">
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

            <div>
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Tokens</div>
                <div id="rtUsage" class="grid grid-cols-2 gap-2">
                    <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                        <div class="text-[10px] text-[var(--ui-muted)]">Input</div>
                        <div id="rtTokensIn" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                    </div>
                    <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                        <div class="text-[10px] text-[var(--ui-muted)]">Output</div>
                        <div id="rtTokensOut" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                    </div>
                    <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                        <div class="text-[10px] text-[var(--ui-muted)]">Total</div>
                        <div id="rtTokensTotal" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                    </div>
                    <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                        <div class="text-[10px] text-[var(--ui-muted)]">Cached / Reasoning</div>
                        <div id="rtTokensExtra" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                    </div>
                </div>
                <div id="rtUsageModel" class="mt-1 text-[10px] text-[var(--ui-muted)]"></div>

                <div class="mt-2">
                    <div class="text-[10px] text-[var(--ui-muted)] mb-1">Kosten (gpt-5.2)</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                            <div class="text-[10px] text-[var(--ui-muted)]">Input $</div>
                            <div id="rtCostIn" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                        </div>
                        <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                            <div class="text-[10px] text-[var(--ui-muted)]">Cached input $</div>
                            <div id="rtCostCached" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                        </div>
                        <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                            <div class="text-[10px] text-[var(--ui-muted)]">Output $</div>
                            <div id="rtCostOut" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                        </div>
                        <div class="border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] p-2">
                            <div class="text-[10px] text-[var(--ui-muted)]">Total $</div>
                            <div id="rtCostTotal" class="text-sm font-semibold text-[var(--ui-secondary)]">—</div>
                        </div>
                    </div>
                    <div id="rtCostNote" class="mt-1 text-[10px] text-[var(--ui-muted)]"></div>
                </div>
            </div>

            <div>
                <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Tool Calls (letzte 5)</div>
                <div id="rtToolCalls" class="space-y-1"></div>
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

    <div x-show="tab==='settings'" class="h-full min-h-0" x-cloak>
        <div class="h-full min-h-0 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] overflow-hidden flex flex-col">
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
    </div>
    </div>

    @verbatim
    <script>
      (() => {
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
        const modelSelect = document.getElementById('modelSelect');
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
        const rtUsageModel = document.getElementById('rtUsageModel');
        const rtCostIn = document.getElementById('rtCostIn');
        const rtCostCached = document.getElementById('rtCostCached');
        const rtCostOut = document.getElementById('rtCostOut');
        const rtCostTotal = document.getElementById('rtCostTotal');
        const rtCostNote = document.getElementById('rtCostNote');
        const rtToolCalls = document.getElementById('rtToolCalls');

        /** type: {role:'user'|'assistant', content:string}[] */
        let messages = [];
        /** continuation state for interrupted tool-loops */
        let continuation = null;
        let inFlight = false;
        let selectedModel = localStorage.getItem('simple.selectedModel') || '';

        const scrollToBottom = () => { chatScroll.scrollTop = chatScroll.scrollHeight; };

        const renderMessage = (role, content) => {
          const wrap = document.createElement('div');
          wrap.className = `flex ${role === 'user' ? 'justify-end' : 'justify-start'}`;
          wrap.innerHTML = `
            <div class="max-w-3xl rounded-lg p-3 ${role === 'user' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-surface)] border border-[var(--ui-border)]'}">
              <div class="text-sm font-semibold mb-1">${role === 'user' ? 'Du' : 'Assistant'}</div>
              <div class="whitespace-pre-wrap"></div>
            </div>
          `;
          wrap.querySelector('.whitespace-pre-wrap').textContent = content;
          chatList.appendChild(wrap);
          scrollToBottom();
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
          if (modelSelect && selectedModel) modelSelect.value = selectedModel;
        };

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

        const renderModels = (models) => {
          if (!Array.isArray(models)) models = [];
          if (modelSelect) {
            modelSelect.innerHTML = '';
            for (const m of models) {
              const opt = document.createElement('option');
              opt.value = m;
              opt.textContent = m;
              modelSelect.appendChild(opt);
            }
            modelSelect.addEventListener('change', () => setSelectedModel(modelSelect.value));
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
          if (!selectedModel) setSelectedModel(models[0] || serverDefaultModel);
        };

        const loadModels = async () => {
          // parity with page playground: fixed model, no API call
          if (modelsList) modelsList.innerHTML = '<div class="text-xs text-[var(--ui-muted)]">Fix: gpt-5.2</div>';
          renderModels([serverDefaultModel]);
        };
        // Always refresh models (so gpt-5.2 is visible immediately after opening).
        if (!alreadyBound && modelsReload) modelsReload.addEventListener('click', () => loadModels());
        loadModels();

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

          const payload = {
            message: (isContinue ? '' : text),
            chat_history: messages,
            model: selectedModel || null,
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
                      const items = debugState.toolCalls.slice(-5).reverse();
                      rtToolCalls.innerHTML = '';
                      for (const it of items) {
                        const row = document.createElement('div');
                        row.className = 'text-[11px] font-mono flex items-center justify-between gap-2 border border-[var(--ui-border)] rounded px-2 py-1 bg-[var(--ui-bg)]';
                        row.innerHTML = `<span class="truncate">${it.tool}</span><span>${it.success ? 'ok' : 'fail'} ${it.ms != null ? `${it.ms}ms` : ''}</span>`;
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

                    if (rtTokensIn) rtTokensIn.textContent = (inTok ?? '—');
                    if (rtTokensOut) rtTokensOut.textContent = (outTok ?? '—');
                    if (rtTokensTotal) rtTokensTotal.textContent = (totalTok ?? '—');
                    if (rtTokensExtra) rtTokensExtra.textContent = `${cached != null ? cached : '—'} / ${reasoning != null ? reasoning : '—'}`;
                    if (rtUsageModel) {
                      const modelLabel = data?.model ? `Model: ${data.model}` : '';
                      const scope = data?.cumulative ? ' · Request total' : '';
                      rtUsageModel.textContent = modelLabel ? `${modelLabel}${scope}` : (data?.cumulative ? 'Request total' : '');
                    }

                    // Costs (explicit pricing for gpt-5.2, per 1M tokens)
                    const RATE_IN = 1.75;
                    const RATE_CACHED = 0.175;
                    const RATE_OUT = 14.00;
                    const toMoney = (x) => {
                      if (x == null || Number.isNaN(x)) return '—';
                      return `$${Number(x).toFixed(6)}`;
                    };
                    const inputTokens = (typeof inTok === 'number') ? inTok : null;
                    const outputTokens = (typeof outTok === 'number') ? outTok : null;
                    const cachedTokens = (typeof cached === 'number') ? cached : 0;
                    const nonCachedInput = (inputTokens != null) ? Math.max(0, inputTokens - cachedTokens) : null;
                    const costIn = (nonCachedInput != null) ? (nonCachedInput / 1_000_000) * RATE_IN : null;
                    const costCached = (cachedTokens != null) ? (cachedTokens / 1_000_000) * RATE_CACHED : null;
                    const costOut = (outputTokens != null) ? (outputTokens / 1_000_000) * RATE_OUT : null;
                    const costTotal = (costIn != null && costCached != null && costOut != null) ? (costIn + costCached + costOut) : null;
                    if (rtCostIn) rtCostIn.textContent = toMoney(costIn);
                    if (rtCostCached) rtCostCached.textContent = toMoney(costCached);
                    if (rtCostOut) rtCostOut.textContent = toMoney(costOut);
                    if (rtCostTotal) rtCostTotal.textContent = toMoney(costTotal);
                    if (rtCostNote) rtCostNote.textContent = `Rates/1M: input $${RATE_IN}, cached $${RATE_CACHED}, output $${RATE_OUT}`;

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

        if (!alreadyBound) {
          form?.addEventListener('submit', (e) => { e.preventDefault(); send(); });
          input?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
          });
        }
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
    @endverbatim
</div>


