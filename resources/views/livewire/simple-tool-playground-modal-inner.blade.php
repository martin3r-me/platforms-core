<div class="h-full flex gap-4">
    {{-- Left: Model selection --}}
    <div class="w-80 flex-shrink-0 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] overflow-hidden">
        <div class="p-4 space-y-4 h-full overflow-y-auto">
            <div class="flex items-center justify-between">
                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Model</div>
                <button id="modelsReload" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Reload</button>
            </div>

            <div>
                <div class="text-xs text-[var(--ui-muted)] mb-1">Ausgewählt (Drop Zone)</div>
                <div id="modelDropZone" class="min-h-[44px] px-3 py-2 rounded border border-dashed border-[var(--ui-border)] bg-[var(--ui-bg)] text-sm">
                    <span id="selectedModelLabel" class="text-[var(--ui-secondary)]">—</span>
                </div>
                <div class="mt-2 text-xs text-[var(--ui-muted)]">Drag ein Model aus der Liste hier rein (oder Doppelklick).</div>
            </div>

            <div>
                <div class="text-xs text-[var(--ui-muted)] mb-1">Fallback Dropdown</div>
                <select id="modelSelect" class="w-full px-3 py-2 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-sm"></select>
            </div>

            <div class="pt-2 border-t border-[var(--ui-border)]">
                <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)] mb-2">Verfügbare Models</div>
                <div id="modelsList" class="space-y-2 max-h-[calc(80vh-22rem)] overflow-y-auto pr-1"></div>
            </div>
        </div>
    </div>

    {{-- Center: Chat --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-[var(--ui-muted)]">
                Kontext: <span id="pgContextLabel" class="text-[var(--ui-secondary)]">—</span>
            </div>
            <div class="text-xs text-[var(--ui-muted)]">
                Stream: <span id="rtStatus" class="text-[var(--ui-secondary)]">idle</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-4 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)]" id="chatScroll">
            <div id="chatList" class="space-y-4"></div>
        </div>

        <form id="chatForm" class="mt-4 flex gap-2" method="post" action="javascript:void(0)" onsubmit="return false;">
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

    {{-- Right: Realtime / Debug --}}
    <div class="w-80 flex-shrink-0 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] overflow-hidden">
        <div class="p-4 space-y-4 h-full overflow-y-auto">
            <div class="flex items-center justify-between">
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
                try { e.dataTransfer.setData('text/plain', m); } catch (_) {}
              });
              modelsList.appendChild(row);
            }
          }
          if (!selectedModel) setSelectedModel(models[0] || serverDefaultModel);
        };

        const loadModels = async () => {
          try {
            const res = await fetch(modelsUrl, { headers: { 'Accept': 'application/json' }});
            const json = await res.json();
            const models = json?.models || [];
            renderModels(models);
          } catch (_) {
            renderModels([serverDefaultModel]);
          }
        };
        if (modelsReload) modelsReload.addEventListener('click', loadModels);
        loadModels();

        // Send
        const send = async ({ isContinue = false } = {}) => {
          if (inFlight) return;
          const text = (input?.value || '').trim();
          if (!isContinue && !text) return;
          inFlight = true;
          sendBtn.disabled = true;

          if (!isContinue) {
            messages.push({ role: 'user', content: text });
            renderMessage('user', text);
            input.value = '';
          }

          // optimistic assistant placeholder
          messages.push({ role: 'assistant', content: '' });
          renderMessage('assistant', '');
          const lastAssistantEl = chatList.lastElementChild?.querySelector('.whitespace-pre-wrap');

          resetRealtime();
          debugState.startedAt = new Date().toISOString();
          if (rtStatus) rtStatus.textContent = 'streaming…';

          const payload = {
            message: (isContinue ? '' : text),
            chat_history: messages.slice(0, -1),
            model: selectedModel || null,
            continuation: (isContinue ? continuation : null),
            context: ctx || null,
          };
          debugState.payload = payload;
          updateDebugDump();

          try {
            const res = await fetch(url, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': csrf,
              },
              body: JSON.stringify(payload),
            });

            if (!res.ok) {
              const text = await res.text();
              throw new Error(`HTTP ${res.status}: ${text.slice(0, 300)}`);
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';
            let assistantText = '';

            const parseSse = (chunk) => {
              buffer += chunk;
              let idx;
              while ((idx = buffer.indexOf('\n\n')) !== -1) {
                const rawEvent = buffer.slice(0, idx);
                buffer = buffer.slice(idx + 2);
                const lines = rawEvent.split('\n').filter(Boolean);
                let eventName = 'message';
                let data = '';
                for (const line of lines) {
                  if (line.startsWith('event:')) eventName = line.slice(6).trim();
                  if (line.startsWith('data:')) data += line.slice(5).trim();
                }
                if (!data) continue;
                let decoded;
                try { decoded = JSON.parse(data); } catch { decoded = { raw: data }; }

                if (eventName === 'assistant.delta') {
                  assistantText = decoded?.content || assistantText + (decoded?.delta || '');
                  if (lastAssistantEl) lastAssistantEl.textContent = assistantText;
                  if (rtAssistant) rtAssistant.textContent = assistantText;
                  debugState.lastAssistant = assistantText;
                  updateDebugDump();
                } else if (eventName === 'openai.event') {
                  rtEvent({ key: decoded?.event, preview: decoded?.preview || null, raw: decoded?.raw || null });
                  debugState.events.push({ t: Date.now(), event: decoded?.event, preview: decoded?.preview || null });
                  updateDebugDump();
                } else if (eventName === 'usage') {
                  debugState.usage = decoded;
                  if (rtTokensIn) rtTokensIn.textContent = String(decoded?.input_tokens ?? '—');
                  if (rtTokensOut) rtTokensOut.textContent = String(decoded?.output_tokens ?? '—');
                  if (rtTokensTotal) rtTokensTotal.textContent = String(decoded?.total_tokens ?? '—');
                  const cached = decoded?.cached_tokens != null ? `cached:${decoded.cached_tokens}` : '';
                  const reasoning = decoded?.reasoning_tokens != null ? `reasoning:${decoded.reasoning_tokens}` : '';
                  if (rtTokensExtra) rtTokensExtra.textContent = [cached, reasoning].filter(Boolean).join(' / ') || '—';
                  updateDebugDump();
                } else if (eventName === 'tool.executed') {
                  debugState.toolCalls.push(decoded);
                  // render last 5
                  if (rtToolCalls) {
                    const items = debugState.toolCalls.slice(-5).reverse();
                    rtToolCalls.innerHTML = '';
                    for (const it of items) {
                      const row = document.createElement('div');
                      row.className = 'text-[10px] font-mono flex items-center justify-between gap-2 border border-[var(--ui-border)] rounded px-2 py-1 bg-[var(--ui-bg)]';
                      row.innerHTML = `<span class="truncate">${it.tool}</span><span>${it.success ? 'ok' : 'fail'} ${it.ms != null ? `${it.ms}ms` : ''}</span>`;
                      rtToolCalls.appendChild(row);
                    }
                  }
                  updateDebugDump();
                } else if (eventName === 'debug.tools') {
                  debugState.toolsVisible = decoded;
                  updateDebugDump();
                } else if (eventName === 'complete') {
                  // finalize
                  continuation = decoded?.continuation || null;
                  messages[messages.length - 1].content = decoded?.assistant || assistantText || '';
                  if (rtStatus) rtStatus.textContent = continuation?.pending ? 'paused (continue möglich)' : 'done';
                  updateDebugDump();
                } else if (eventName === 'error') {
                  if (rtStatus) rtStatus.textContent = 'error';
                  rtEvent({ key: 'error', preview: { message: decoded?.error }, raw: JSON.stringify(decoded) });
                  updateDebugDump();
                }
              }
            };

            while (true) {
              const { value, done } = await reader.read();
              if (done) break;
              parseSse(decoder.decode(value, { stream: true }));
            }
          } catch (e) {
            if (rtStatus) rtStatus.textContent = 'error';
            rtEvent({ key: 'fetch.error', preview: { message: String(e?.message || e) }, raw: String(e?.stack || '') });
          } finally {
            inFlight = false;
            sendBtn.disabled = false;
          }
        };

        form?.addEventListener('submit', (e) => { e.preventDefault(); send(); });
        input?.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });
      };

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
      } else {
        boot();
      }
    })();
    </script>
    @endverbatim
</div>


