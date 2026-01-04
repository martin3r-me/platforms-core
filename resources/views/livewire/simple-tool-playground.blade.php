<x-ui-page>
  <x-slot name="navbar">
    <x-ui-page-navbar title="Simple Chat Playground" icon="heroicon-o-chat-bubble-left-right" />
  </x-slot>

  <x-slot name="sidebar">
    <x-ui-page-sidebar title="Model" width="w-80" :defaultOpen="true" side="left">
      <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
          <div class="text-xs font-semibold uppercase tracking-wide text-[var(--ui-muted)]">Auswahl</div>
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
          <div id="modelsList" class="space-y-2 max-h-[calc(100vh-22rem)] overflow-y-auto pr-1"></div>
        </div>
      </div>
    </x-ui-page-sidebar>
  </x-slot>

  <x-slot name="activity">
    <x-ui-page-sidebar title="Realtime" width="w-80" :defaultOpen="true" side="right" storeKey="activityOpen">
      <div class="p-4 space-y-4">
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
          <pre id="rtAssistant" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[80px] max-h-[30vh] overflow-y-auto"></pre>
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
        </div>
        <div>
          <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Reasoning (summary, live)</div>
          <pre id="rtReasoning" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[60px] max-h-[20vh] overflow-y-auto"></pre>
        </div>
        <div>
          <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Thinking (detailed, live)</div>
          <pre id="rtThinking" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[60px] max-h-[20vh] overflow-y-auto"></pre>
        </div>
        <div class="pt-2 border-t border-[var(--ui-border)]">
          <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Events</div>
          <div id="rtEvents" class="text-xs space-y-2 text-[var(--ui-muted)] max-h-[18vh] overflow-y-auto pr-1"></div>
        </div>

        <div id="rtStatus" class="text-xs text-[var(--ui-muted)]">idle</div>
      </div>
    </x-ui-page-sidebar>
  </x-slot>

  <x-ui-page-container>
    <div class="flex flex-col h-[calc(100vh-12rem)]">
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

    @php
      $simpleStreamUrl = route('core.tools.simple.stream');
      $simpleModelsUrl = route('core.tools.simple.models');
    @endphp

    <script>
      // Provide URLs for the verbatim JS block below (no Blade parsing inside that block).
      window.__simpleStreamUrl = @json($simpleStreamUrl);
      window.__simpleModelsUrl = @json($simpleModelsUrl);
    </script>

    @verbatim
    <script>
      (() => {
        const boot = () => {
        const url = window.__simpleStreamUrl;
        const modelsUrl = window.__simpleModelsUrl;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        // Simple Playground: fixed model for now (explicit user request)
        const serverDefaultModel = 'gpt-5.2';

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
        const rtTokensIn = document.getElementById('rtTokensIn');
        const rtTokensOut = document.getElementById('rtTokensOut');
        const rtTokensTotal = document.getElementById('rtTokensTotal');
        const rtTokensExtra = document.getElementById('rtTokensExtra');
        const rtUsageModel = document.getElementById('rtUsageModel');

        /** type: {role:'user'|'assistant', content:string}[] */
        let messages = [];
        let inFlight = false;
        let selectedModel = localStorage.getItem('simple.selectedModel') || '';

        // Ensure sidebars are visible on this debug playground even if the user previously closed them
        // (x-ui-page persists sidebarOpen/activityOpen in localStorage: page.sidebarOpen / page.activityOpen)
        const forceSidebarsOpen = () => {
          try {
            if (window.Alpine?.store && window.Alpine.store('page')) {
              window.Alpine.store('page').sidebarOpen = true;
              window.Alpine.store('page').activityOpen = true;
            }
          } catch (_) {}
        };
        forceSidebarsOpen();
        // In case Alpine initializes after this script, retry briefly.
        (() => {
          let tries = 0;
          const t = setInterval(() => {
            tries++;
            forceSidebarsOpen();
            if ((window.Alpine?.store && window.Alpine.store('page')) || tries > 40) clearInterval(t);
          }, 50);
        })();

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

        // Event log: compact entries + optional raw JSON, with aggregation of repeated events
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

        // IMPORTANT: keep UI realtime. Pretty-printing large JSON synchronously blocks the main thread.
        // So we render raw/pretty JSON lazily only when the user expands an event (and only in verbose mode).
        const safeJsonPretty = (raw) => {
          const s = String(raw || '');
          if (!s) return '';
          // Fast path: just return raw. (Pretty-print would require JSON.parse which can be expensive.)
          return s;
        };

        const mkSummaryText = (key, preview, count) => {
          const c = count > 1 ? ` ×${count}` : '';
          if (preview && Object.keys(preview).length) {
            const small = {};
            for (const k of ['type','id','name','status','query']) {
              if (preview[k] != null && preview[k] !== '') small[k] = preview[k];
            }
            const p = Object.keys(small).length ? ` ${JSON.stringify(small)}` : '';
            return `${key}${c}${p}`;
          }
          return `${key}${c}`;
        };

        let skippedDeltaCount = 0;
        let lastDeltaTs = 0;

        const rtEvent = ({ key, preview = null, raw = null }) => {
          if (!key) return;
          // Don't spam deltas: assistant text is already visible in rtAssistant
          if (key === 'response.output_text.delta') {
            skippedDeltaCount++;
            const now = Date.now();
            // Update status at most every 250ms
            if (now - lastDeltaTs > 250) {
              lastDeltaTs = now;
              if (rtStatus?.textContent?.includes('streaming')) {
                rtStatus.textContent = `streaming… (deltas: ${skippedDeltaCount})`;
              }
            }
            return;
          }

          // Aggregate consecutive identical events
          if (lastEventKey === key && lastEventSummaryEl) {
            lastEventCount++;
            lastEventSummaryEl.textContent = mkSummaryText(key, preview, lastEventCount);
            return;
          }

          lastEventKey = key;
          lastEventCount = 1;

          // Non-verbose: render only a single compact line (fast).
          if (!rtVerbose) {
            const row = document.createElement('div');
            row.className = 'px-2 py-1 border border-[var(--ui-border)] rounded bg-[var(--ui-bg)] text-[11px]';
            row.textContent = mkSummaryText(key, preview, 1);
            rtEvents.appendChild(row);
            lastEventSummaryEl = row;
          } else {
            // Verbose: details with lazy body render on first open.
            const wrap = document.createElement('details');
            wrap.className = 'border border-[var(--ui-border)] rounded bg-[var(--ui-bg)]';
            wrap.open = false;

            const summary = document.createElement('summary');
            summary.className = 'cursor-pointer px-2 py-1 text-[11px]';
            summary.textContent = mkSummaryText(key, preview, 1);
            wrap.appendChild(summary);
            lastEventSummaryEl = summary;

            const pre = document.createElement('pre');
            pre.className = 'px-2 pb-2 text-[10px] whitespace-pre-wrap overflow-x-hidden';
            pre.textContent = ''; // lazy
            wrap.appendChild(pre);

            let hydrated = false;
            wrap.addEventListener('toggle', () => {
              if (!wrap.open || hydrated) return;
              hydrated = true;
              // Render raw first (fast). If it's JSON, we keep it raw to avoid blocking.
              if (raw) pre.textContent = safeJsonPretty(raw);
              else if (preview) pre.textContent = JSON.stringify(preview, null, 2);
              else pre.textContent = '';
            });

            rtEvents.appendChild(wrap);
          }

          while (rtEvents.children.length > maxEventItems) rtEvents.removeChild(rtEvents.firstChild);
          rtEvents.scrollTop = rtEvents.scrollHeight;
        };

        const rtClear = () => {
          rtAssistant.textContent = '';
          rtReasoning.textContent = '';
          rtThinking.textContent = '';
          rtEvents.innerHTML = '';
          rtStatus.textContent = 'idle';
          realtimeModel.textContent = selectedModel || '—';
          if (rtTokensIn) rtTokensIn.textContent = '—';
          if (rtTokensOut) rtTokensOut.textContent = '—';
          if (rtTokensTotal) rtTokensTotal.textContent = '—';
          if (rtTokensExtra) rtTokensExtra.textContent = '—';
          if (rtUsageModel) rtUsageModel.textContent = '';
          lastEventKey = null;
          lastEventCount = 0;
          lastEventSummaryEl = null;
          skippedDeltaCount = 0;
          lastDeltaTs = 0;
        };

        realtimeClear.addEventListener('click', rtClear);

        const setSelectedModel = (modelId) => {
          selectedModel = modelId || '';
          selectedModelLabel.textContent = selectedModel || '—';
          localStorage.setItem('simple.selectedModel', selectedModel);
          if (modelSelect) modelSelect.value = selectedModel;
          realtimeModel.textContent = selectedModel || '—';
        };

        const renderModels = (ids) => {
          modelsList.innerHTML = '';
          modelSelect.innerHTML = '';

          const placeholder = document.createElement('option');
          placeholder.value = '';
          placeholder.textContent = '— wählen —';
          modelSelect.appendChild(placeholder);

          for (const id of ids) {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = id;
            modelSelect.appendChild(opt);

            const item = document.createElement('div');
            item.className = 'px-3 py-2 rounded border border-[var(--ui-border)] bg-[var(--ui-bg)] text-sm cursor-grab';
            item.textContent = id;
            item.draggable = true;
            item.addEventListener('dragstart', (e) => {
              e.dataTransfer.setData('text/plain', id);
              e.dataTransfer.effectAllowed = 'copy';
            });
            item.addEventListener('dblclick', () => setSelectedModel(id));
            modelsList.appendChild(item);
          }

          // Selection strategy:
          // - keep existing selection if valid
          // - else auto-pick first model (keeps UI non-empty)
          // - else empty
          if (selectedModel && ids.includes(selectedModel)) setSelectedModel(selectedModel);
          else if (ids.length > 0) setSelectedModel(ids[0]);
          else setSelectedModel('');
        };

        const loadModels = async () => {
          // fixed model: no API call
          modelsList.innerHTML = '<div class="text-xs text-[var(--ui-muted)]">Fix: gpt-5.2</div>';
          renderModels([serverDefaultModel]);
        };

        // drag & drop dropzone
        modelDropZone.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; });
        modelDropZone.addEventListener('drop', (e) => {
          e.preventDefault();
          const id = e.dataTransfer.getData('text/plain');
          if (id) setSelectedModel(id);
        });

        modelSelect.addEventListener('change', () => setSelectedModel(modelSelect.value));
        modelsReload.addEventListener('click', () => loadModels());
        loadModels();

        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          const text = (input.value || '').trim();
          if (!text || inFlight) return;

          // UI + local state
          messages.push({ role: 'user', content: text });
          renderMessage('user', text);
          input.value = '';
          inFlight = true;
          sendBtn.disabled = true;
          input.disabled = true;
          // Realtime panel: reset + start
          rtClear();
          rtStatus.textContent = 'streaming…';
          realtimeModel.textContent = selectedModel || '—';
          rtEvent({ key: 'client.start' });

          // Request payload = full conversation history + new message already included
          const payload = { message: text, chat_history: messages.slice(0, -1), model: selectedModel || null };

          try {
            rtEvent({ key: 'client.fetch.start', preview: { url } });
            const res = await fetch(url, {
              method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-CSRF-TOKEN': csrf },
              body: JSON.stringify(payload),
            });
            if (!res.ok || !res.body) throw new Error(`HTTP ${res.status}`);

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
                    break;
                  case 'reasoning.delta':
                    if (data?.delta) rtReasoning.textContent += data.delta;
                    break;
                  case 'thinking.delta':
                    if (data?.delta) rtThinking.textContent += data.delta;
                    break;
                  case 'usage': {
                    const usage = data?.usage || {};
                    const inTok = usage?.input_tokens ?? usage?.input ?? null;
                    const outTok = usage?.output_tokens ?? usage?.output ?? null;
                    const totalTok = usage?.total_tokens ?? usage?.total ?? null;
                    const cached = usage?.input_tokens_details?.cached_tokens ?? null;
                    const reasoning = usage?.output_tokens_details?.reasoning_tokens ?? null;
                    if (rtTokensIn) rtTokensIn.textContent = (inTok ?? '—');
                    if (rtTokensOut) rtTokensOut.textContent = (outTok ?? '—');
                    if (rtTokensTotal) rtTokensTotal.textContent = (totalTok ?? '—');
                    if (rtTokensExtra) rtTokensExtra.textContent =
                      `${cached != null ? cached : '—'} / ${reasoning != null ? reasoning : '—'}`;
                    if (rtUsageModel) rtUsageModel.textContent = data?.model ? `Model: ${data.model}` : '';
                    break;
                  }
                  case 'openai.event': {
                    const ev = data?.event || 'openai.event';
                    rtEvent({ key: ev, preview: data?.preview || null, raw: data?.raw || null });
                    break;
                  }
                  case 'complete': {
                    const assistant = data?.assistant || rtAssistant.textContent;
                    messages.push({ role: 'assistant', content: assistant });
                    renderMessage('assistant', assistant);
                    rtStatus.textContent = 'done';
                    rtEvent({ key: 'client.complete' });
                    break;
                  }
                  case 'error': {
                    const msg = data?.error || 'Unbekannter Fehler';
                    messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
                    renderMessage('assistant', `❌ Fehler: ${msg}`);
                    rtStatus.textContent = 'error';
                    rtEvent({ key: 'client.error', preview: { error: msg } });
                    break;
                  }
                  default:
                    if (rtVerbose && currentEvent) rtEvent({ key: `sse.${currentEvent}`, raw: data?.raw || null, preview: data || null });
                }
                scrollToBottom();
              }
            }
          } catch (err) {
            const msg = err?.message || String(err);
            messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
            renderMessage('assistant', `❌ Fehler: ${msg}`);
            rtStatus.textContent = 'error';
            rtEvent({ key: 'client.error', preview: { error: msg } });
          } finally {
            inFlight = false;
            sendBtn.disabled = false;
            input.disabled = false;
            input.focus();
          }
        });
        };

        // Important: this script sits inside the main slot of <x-ui-page>.
        // The right "activity" sidebar is rendered AFTER the slot, so we must wait
        // until the full DOM is parsed; otherwise elements like #realtimeClear are null
        // and the whole script crashes (breaking Send).
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', boot, { once: true });
        } else {
          boot();
        }
      })();
    </script>
    @endverbatim
  </x-ui-page-container>
</x-ui-page>
