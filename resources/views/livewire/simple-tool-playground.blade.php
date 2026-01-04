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
          <button id="realtimeClear" type="button" class="text-xs text-[var(--ui-muted)] hover:underline">Clear</button>
        </div>

        <div>
          <div class="text-xs font-semibold text-[var(--ui-secondary)] mb-1">Assistant (live)</div>
          <pre id="rtAssistant" class="text-xs whitespace-pre-wrap border border-[var(--ui-border)] rounded p-2 bg-[var(--ui-bg)] min-h-[80px] max-h-[30vh] overflow-y-auto"></pre>
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
          <div id="rtEvents" class="text-xs space-y-1 text-[var(--ui-muted)] max-h-[18vh] overflow-y-auto pr-1"></div>
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

      <form id="chatForm" class="mt-4 flex gap-2">
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

    <script>
      (() => {
        const url = '{{ route("core.tools.simple.stream") }}';
        const modelsUrl = '{{ route("core.tools.simple.models") }}';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

        /** @type {{role:'user'|'assistant', content:string}[]} */
        let messages = [];
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

        const rtLog = (text) => {
          const row = document.createElement('div');
          row.textContent = text;
          rtEvents.appendChild(row);
          // keep last 80
          while (rtEvents.children.length > 80) rtEvents.removeChild(rtEvents.firstChild);
        };

        const rtClear = () => {
          rtAssistant.textContent = '';
          rtReasoning.textContent = '';
          rtThinking.textContent = '';
          rtEvents.innerHTML = '';
          rtStatus.textContent = 'idle';
          realtimeModel.textContent = selectedModel || '—';
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

          // keep selection if still valid
          if (selectedModel && ids.includes(selectedModel)) {
            setSelectedModel(selectedModel);
          } else {
            setSelectedModel('');
          }
        };

        const loadModels = async () => {
          modelsList.innerHTML = '<div class="text-xs text-[var(--ui-muted)]">Lade…</div>';
          try {
            const res = await fetch(modelsUrl, {
              method: 'GET',
              headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json?.success) throw new Error(json?.error || `HTTP ${res.status}`);
            const ids = Array.isArray(json.models) ? json.models : [];
            renderModels(ids);
          } catch (e) {
            modelsList.innerHTML = `<div class="text-xs text-[var(--ui-warning)]">Models konnten nicht geladen werden: ${e.message}</div>`;
          }
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
          rtLog('start');

          // Request payload = full conversation history + new message already included
          const payload = { message: text, chat_history: messages.slice(0, -1), model: selectedModel || null };

          try {
            const res = await fetch(url, {
              method: 'POST',
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
                  case 'openai.event': {
                    const ev = data?.event || 'openai.event';
                    const p = data?.preview ? JSON.stringify(data.preview) : '';
                    const raw = data?.raw ? String(data.raw) : '';
                    rtLog(p ? `${ev} ${p}` : ev);
                    if (raw) rtLog(raw);
                    break;
                  }
                  case 'complete': {
                    const assistant = data?.assistant || rtAssistant.textContent;
                    messages.push({ role: 'assistant', content: assistant });
                    renderMessage('assistant', assistant);
                    rtStatus.textContent = 'done';
                    rtLog('complete');
                    break;
                  }
                  case 'error': {
                    const msg = data?.error || 'Unbekannter Fehler';
                    messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
                    renderMessage('assistant', `❌ Fehler: ${msg}`);
                    rtStatus.textContent = 'error';
                    rtLog('error: ' + msg);
                    break;
                  }
                  default:
                    // optional: show debug/request events
                    if (currentEvent) rtLog(currentEvent);
                }
                scrollToBottom();
              }
            }
          } catch (err) {
            const msg = err?.message || String(err);
            messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
            renderMessage('assistant', `❌ Fehler: ${msg}`);
            rtStatus.textContent = 'error';
            rtLog('error: ' + msg);
          } finally {
            inFlight = false;
            sendBtn.disabled = false;
            input.disabled = false;
            input.focus();
          }
        });
      })();
    </script>
  </x-ui-page-container>
</x-ui-page>
