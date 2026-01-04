<x-ui-page>
  <x-slot name="navbar">
    <x-ui-page-navbar title="Simple Chat Playground" icon="heroicon-o-chat-bubble-left-right" />
  </x-slot>

  <x-ui-page-container>
    <div class="flex flex-col h-[calc(100vh-10rem)]">
      <div class="flex-1 overflow-y-auto p-4 space-y-4 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)]" id="chatScroll">
        <div id="chatList" class="space-y-4"></div>

        <!-- Active streaming assistant bubble -->
        <div id="streamBubble" class="hidden">
          <div class="flex justify-start">
            <div class="max-w-3xl rounded-lg p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]">
              <div class="text-sm font-semibold mb-1">Assistant</div>
              <div class="whitespace-pre-wrap" id="streamAssistant"></div>
              <details class="mt-3">
                <summary class="cursor-pointer text-xs text-[var(--ui-muted)]">Reasoning (live)</summary>
                <pre class="mt-2 text-xs whitespace-pre-wrap" id="streamReasoning"></pre>
              </details>
              <details class="mt-2">
                <summary class="cursor-pointer text-xs text-[var(--ui-muted)]">Thinking (live)</summary>
                <pre class="mt-2 text-xs whitespace-pre-wrap" id="streamThinking"></pre>
              </details>
              <div class="mt-2 text-xs text-[var(--ui-muted)] animate-pulse">● streaming…</div>
            </div>
          </div>
        </div>
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
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const chatList = document.getElementById('chatList');
        const chatScroll = document.getElementById('chatScroll');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSend');

        const streamBubble = document.getElementById('streamBubble');
        const streamAssistant = document.getElementById('streamAssistant');
        const streamReasoning = document.getElementById('streamReasoning');
        const streamThinking = document.getElementById('streamThinking');

        /** @type {{role:'user'|'assistant', content:string}[]} */
        let messages = [];
        let inFlight = false;

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

        const setStreamingVisible = (visible) => {
          streamBubble.classList.toggle('hidden', !visible);
          if (!visible) {
            streamAssistant.textContent = '';
            streamReasoning.textContent = '';
            streamThinking.textContent = '';
          }
        };

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
          setStreamingVisible(true);

          // Request payload = full conversation history + new message already included
          const payload = { message: text, chat_history: messages.slice(0, -1) };

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
                    if (data?.delta) streamAssistant.textContent += data.delta;
                    break;
                  case 'reasoning.delta':
                    if (data?.delta) streamReasoning.textContent += data.delta;
                    break;
                  case 'thinking.delta':
                    if (data?.delta) streamThinking.textContent += data.delta;
                    break;
                  case 'complete': {
                    const assistant = data?.assistant || streamAssistant.textContent;
                    messages.push({ role: 'assistant', content: assistant });
                    renderMessage('assistant', assistant);
                    setStreamingVisible(false);
                    break;
                  }
                  case 'error': {
                    const msg = data?.error || 'Unbekannter Fehler';
                    messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
                    renderMessage('assistant', `❌ Fehler: ${msg}`);
                    setStreamingVisible(false);
                    break;
                  }
                }
                scrollToBottom();
              }
            }
          } catch (err) {
            const msg = err?.message || String(err);
            messages.push({ role: 'assistant', content: `❌ Fehler: ${msg}` });
            renderMessage('assistant', `❌ Fehler: ${msg}`);
            setStreamingVisible(false);
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
