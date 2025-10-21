<div
  x-data="chatTerminal()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle(); $nextTick(() => { const c = $refs.body; if(c){ c.scrollTop = c.scrollHeight } })"
  x-on:ai-stream-start.window="startStream($event.detail?.url)"
  x-on:ai-stream-delta.window="$nextTick(() => { const c = $refs.body; if(c){ c.scrollTop = c.scrollHeight } })"
  x-on:ai-stream-complete.window="onStreamComplete()"
  x-on:ai-stream-error.window="onStreamError()"
  x-on:ai-stream-drained.window="onStreamDrained()"
  x-on:terminal-scroll.window="$nextTick(() => { const c = $refs.body; if(c){ c.scrollTop = c.scrollHeight } })"
  class="w-full"
  wire:key="terminal-root"
>
  <!-- Slide container -->
  <div
    class="w-full border-t border-[var(--ui-border)]/60 bg-[var(--ui-surface)]/95 backdrop-blur overflow-hidden transition-[max-height] duration-300 ease-out flex flex-col"
    x-bind:style="open ? 'max-height: 14rem' : 'max-height: 0px'"
    style="max-height: 0px;"
    wire:key="terminal-slide"
  >
    <!-- Header -->
    <div class="h-10 px-3 flex items-center justify-between text-xs border-b border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-header">
      <div class="flex items-center gap-2 text-[var(--ui-muted)]">
        @svg('heroicon-o-command-line', 'w-4 h-4')
        <span>Terminal</span>
      </div>
      <div class="flex items-center gap-1">
        <button type="button" @click="toggle()"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition"
                aria-label="Terminal schließen">
          @svg('heroicon-o-x-mark','w-4 h-4')
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center border-b border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] opacity-100 transition-opacity duration-200"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-tabs">
      <div class="flex items-center overflow-x-auto">
        @foreach($chats as $chat)
          <div class="flex items-center border-r border-[var(--ui-border)]/60" wire:key="chat-tab-{{ $chat['id'] }}">
            <button
              type="button"
              wire:click="setActiveChat({{ $chat['id'] }})"
              class="flex items-center gap-2 px-3 py-2 text-xs transition-colors min-w-0"
              :class="$wire.activeChatId == {{ $chat['id'] }}
                ? 'text-[var(--ui-primary)] bg-[var(--ui-surface)]'
                : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]'"
              aria-current="{{ $activeChatId == $chat['id'] ? 'true' : 'false' }}"
            >
              <span class="truncate max-w-20">{{ $chat['title'] ?: 'Chat ' . $chat['id'] }}</span>
            </button>
            <button
              type="button"
              @click.stop="$wire.deleteChat({{ $chat['id'] }})"
              class="mx-1 inline-flex items-center justify-center w-4 h-4 rounded hover:bg-[var(--ui-danger-5)] hover:text-[var(--ui-danger)] transition-colors"
              title="Chat löschen"
              aria-label="Chat löschen"
              wire:key="chat-tab-delete-{{ $chat['id'] }}"
            >
              @svg('heroicon-o-x-mark', 'w-3 h-3')
            </button>
          </div>
        @endforeach

        <!-- New Chat Button -->
        <button
          type="button"
          wire:click="createNewChat"
          class="flex items-center gap-2 px-3 py-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition-colors"
          title="Neuen Chat erstellen"
          aria-label="Neuen Chat erstellen"
          wire:key="chat-tab-create"
        >
          @svg('heroicon-o-plus', 'w-4 h-4')
        </button>
      </div>
    </div>

    <!-- Body -->
    <div
      class="flex-1 min-h-0 overflow-y-auto px-3 py-2 pb-6 text-xs font-mono text-[var(--ui-secondary)] opacity-100 transition-opacity duration-200"
      :class="open ? 'opacity-100' : 'opacity-0'"
      data-terminal-body
      x-ref="body"
      wire:key="terminal-body"
    >
      <div class="space-y-2" wire:key="terminal-body-inner">
        @if(empty($messages))
          <div class="text-[var(--ui-muted)]" wire:key="terminal-help-hint">Tippe "help" für verfügbare Befehle…</div>
          <div class="mt-2 space-y-1" wire:key="terminal-help-examples">
            <div>$ help</div>
            <div>- kpi            Zeigt Team-KPIs</div>
            <div>- tasks --mine   Eigene Aufgaben</div>
          </div>
        @endif

        @foreach($messages as $message)
          <div class="flex items-start gap-2"
               wire:key="msg-{{ $message['id'] ?? (($message['thread_id'] ?? 't') . '-' . $loop->index) }}">
            <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">
              {{ $message['role'] === 'user' ? 'User' : 'AI' }}:
            </span>
            <span class="text-[var(--ui-secondary)] text-xs break-words">
              {{ $message['content'] }}
            </span>
          </div>
        @endforeach

        <!-- Streaming-Block -->
        <div class="flex items-start gap-2" x-show="$wire.isStreaming || streamText.length > 0" wire:ignore>
          <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">AI:</span>
          <div class="flex items-center gap-2"
               role="log"
               aria-live="polite"
               aria-atomic="false">
            <span class="text-[var(--ui-secondary)] text-xs break-words" x-text="streamText"></span>
            <div class="w-3 h-3 border-2 border-[var(--ui-primary)] border-t-transparent rounded-full animate-spin"
                 x-show="!hasDelta"
                 aria-hidden="true"></div>
            <template x-if="$wire.currentTool">
              <div class="text-xs text-[var(--ui-muted)]" x-text="'(Tool: ' + $wire.currentTool + ')'"></div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Prompt -->
    <div class="h-10 px-3 flex items-center gap-2 border-t border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200 flex-shrink-0"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-prompt">
      <span class="text-[var(--ui-muted)] text-xs font-mono">$</span>
      <input
        type="text"
        wire:model="messageInput"
        wire:keydown.enter="sendMessage"
        class="flex-1 bg-transparent outline-none text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]"
        :placeholder="$wire.isStreaming ? 'Verarbeite…' : 'Nachricht eingeben…'"
        :disabled="$wire.isStreaming"
        :aria-disabled="$wire.isStreaming ? 'true' : 'false'"
        wire:key="terminal-input"
      />
      @if($canCancel)
        <button
          type="button"
          wire:click="cancelRequest"
          @click="abortStream()"
          class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-danger)]/60 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition"
          wire:key="terminal-cancel"
        >
          Abbrechen
        </button>
      @else
        <button
          type="button"
          wire:click="sendMessage"
          class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition"
          :disabled="$wire.isProcessing"
          :aria-disabled="$wire.isProcessing ? 'true' : 'false'"
          wire:key="terminal-send"
        >
          Senden
        </button>
      @endif
    </div>
  </div>

  <script>
    // Flag für Dev-Logs
    window.__DEV__ = window.__DEV__ ?? false;

    function chatTerminal(){
      return {
        // UI open state via Alpine store
        get open(){ return Alpine?.store('page')?.terminalOpen ?? false; },
        toggle(){ if(Alpine?.store('page')) Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen; },

        // Streaming state
        es: null,
        streamText: '',
        queue: '',
        typingTimer: null,
        typingDelay: 28,
        fastTypingDelay: 12,
        chunkSize: 16,         // adaptives Chunking
        hasDelta: false,
        finalizePending: false,

        // Retry/Backoff
        retryCount: 0,
        maxRetry: 3,
        backoffBaseMs: 400,

        // Cached ref
        bodyEl: null,

        // Livewire bindings (optional safety fallback)
        init(){
          this.bodyEl = this.$refs.body;
          // Bei Page unload: Stream schließen
          window.addEventListener('beforeunload', () => this.closeStream());
          // Falls Terminal beim Mount schon offen ist: ans Ende scrollen
          this.$nextTick(() => { if(Alpine?.store('page')?.terminalOpen && this.bodyEl){ this.bodyEl.scrollTop = this.bodyEl.scrollHeight; }});
        },

        // Typing helpers
        stopTyping(){ if(this.typingTimer){ clearInterval(this.typingTimer); this.typingTimer = null; } },
        startTyping(){
          if(this.typingTimer) return;
          this.typingTimer = setInterval(() => {
            if(this.queue.length === 0){ this.stopTyping(); return; }
            // adaptives Chunking (größere Queues -> schneller abbauen)
            const bigQueue = this.queue.length > 2000 ? 32 : (this.queue.length > 800 ? 24 : this.chunkSize);
            const n = Math.min(bigQueue, this.queue.length);
            this.streamText += this.queue.slice(0, n);
            this.queue = this.queue.slice(n);
            if(this.bodyEl){ requestAnimationFrame(() => { this.bodyEl.scrollTop = this.bodyEl.scrollHeight; }); }
          }, this.typingDelay);
        },
        pushDelta(delta){
          if(!delta) return;
          this.queue += delta;
          this.startTyping();
        },

        // SSE Steuerung
        startStream(url){
          this.closeStream(); // alte Verbindung schließen
          try {
            if(window.__DEV__) console.log('[Terminal SSE] startStream →', url);
            // Reset UI-States
            this.streamText = '';
            this.queue = '';
            this.stopTyping();
            this.hasDelta = false;
            this.finalizePending = false;

            this.es = new EventSource(url);
            this.es.onopen = () => { if(window.__DEV__) console.log('[Terminal SSE] connection open'); this.retryCount = 0; };
            this.es.onmessage = (e) => {
              if(!e.data) return;
              if(window.__DEV__) console.log('[Terminal SSE] onmessage raw:', e.data);
              if(e.data === '[DONE]'){
                if(window.__DEV__) console.log('[Terminal SSE] DONE');
                this.closeStream();
                window.dispatchEvent(new CustomEvent('ai-stream-complete'));
                return;
              }
              // Robust JSON-Parsing
              try {
                const data = JSON.parse(e.data);
                if(data?.delta){
                  if(window.__DEV__) console.log('[Terminal SSE] delta:', data.delta);
                  if(!this.hasDelta) this.hasDelta = true;
                  this.pushDelta(data.delta);
                  window.dispatchEvent(new CustomEvent('ai-stream-delta', { detail: { delta: data.delta } }));
                }
                // optional: tool name/status über globales Event oder Livewire setzen
                if(data?.tool){ this.$wire && this.$wire.set && this.$wire.set('currentTool', data.tool); }
              } catch(parseErr){
                // Non-JSON Lines ignorieren (Keep-Alive etc.)
                if(window.__DEV__) console.warn('[Terminal SSE] parse skip (non-JSON line)');
              }
            };
            this.es.onerror = (err) => {
              if(window.__DEV__) console.error('[Terminal SSE] error:', err);
              this.closeStream();
              // Retry mit Backoff (capped)
              if(this.retryCount < this.maxRetry){
                const delay = Math.min(4000, this.backoffBaseMs * Math.pow(2, this.retryCount++));
                setTimeout(() => this.startStream(url), delay);
              } else {
                window.dispatchEvent(new CustomEvent('ai-stream-error'));
              }
            };
          } catch(e){
            if(window.__DEV__) console.error('[Terminal SSE] start error', e);
          }
        },
        closeStream(){
          if(this.es){ try { this.es.close(); } catch(_){} this.es = null; }
        },
        abortStream(){
          // Manueller Abbruch aus UI
          this.closeStream();
          this.stopTyping();
          this.queue = '';
          this.finalizePending = false;
          this.hasDelta = false;
          // Livewire-State aufräumen (falls gebunden)
          this.$wire?.set?.('isProcessing', false);
          this.$wire?.set?.('isStreaming', false);
          this.$wire?.set?.('canCancel', false);
          this.$wire?.set?.('progressText', '');
          this.$wire?.set?.('currentTool', null);
          window.dispatchEvent(new CustomEvent('ai-stream-error'));
        },

        // Drain → wartet bis die Queue leer ist, dann signalisiert „drained“
        drainUntilEmpty(){
          if(this.queue.length === 0){
            window.dispatchEvent(new CustomEvent('ai-stream-drained'));
            return;
          }
          setTimeout(() => this.drainUntilEmpty(), 30);
        },

        // Event-Brücken (aus x-on)
        onStreamComplete(){
          if(window.__DEV__) console.log('[Terminal SSE] ai-stream-complete');
          this.finalizePending = true;
          this.$wire?.set?.('canCancel', false);
          this.typingDelay = this.fastTypingDelay; // schneller zu Ende tippen
          this.startTyping();
          this.drainUntilEmpty();
          if(this.bodyEl){ requestAnimationFrame(() => { this.bodyEl.scrollTop = this.bodyEl.scrollHeight; }); }
        },
        onStreamError(){
          if(window.__DEV__) console.log('[Terminal SSE] ai-stream-error');
          this.stopTyping();
          this.queue = '';
          this.finalizePending = false;
          this.hasDelta = false;
          this.$wire?.set?.('isProcessing', false);
          this.$wire?.set?.('isStreaming', false);
          this.$wire?.set?.('canCancel', false);
          this.$wire?.set?.('progressText', '');
          this.$wire?.set?.('currentTool', null);
        },
        async onStreamDrained(){
          if(window.__DEV__) console.log('[Terminal SSE] ai-stream-drained');
          this.stopTyping();
          // Livewire: Streaming-Fahnen zurücksetzen
          this.$wire?.set?.('isStreaming', false);
          this.$wire?.set?.('canCancel', false);
          this.hasDelta = false;
          this.finalizePending = false;
          this.$wire?.set?.('isProcessing', false);
          this.$wire?.set?.('progressText','');
          // **Wichtig**: Stream-Text erst NACH History-Reload leeren, um Flicker zu vermeiden
          try {
            await this.$wire?.call?.('loadMessages');
          } catch(_) {}
          // kleiner Delay, damit DOM die neuen Messages rendert
          setTimeout(() => {
            this.streamText = '';
            this.$wire?.set?.('currentTool', null);
            window.dispatchEvent(new CustomEvent('terminal-scroll'));
          }, 60);
        },
      };
    }
  </script>
</div>