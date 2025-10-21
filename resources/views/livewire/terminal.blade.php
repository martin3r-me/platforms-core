<div
  x-data="chatTerminal({
    isStreaming: $wire?.entangle('isStreaming') ?? false,
    isProcessing: $wire?.entangle('isProcessing') ?? false,
    canCancel:   $wire?.entangle('canCancel')   ?? false,
  })"
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
                aria-label="Terminal schließen"
                wire:key="terminal-close-btn">
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
              wire:key="chat-tab-button-{{ $chat['id'] }}"
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

        <!-- Streaming-Block (zeilenweises Fade-In) -->
        <div class="flex items-start gap-2" x-show="isStreaming || streamLines.length > 0" wire:ignore>
          <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">AI:</span>
          <div class="flex-1 flex flex-col gap-1"
               role="log"
               aria-live="polite"
               aria-atomic="false">
            <!-- schon finalisierte Zeilen -->
            <template x-for="line in streamLines" :key="line.id">
              <div
                class="text-[var(--ui-secondary)] text-xs break-words transition ease-out duration-200"
                :class="line.visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-0.5'"
                x-text="line.text">
              </div>
            </template>

            <!-- aktuell wachsende Zeile (optional, ohne Fade) -->
            <div class="text-[var(--ui-secondary)] text-xs break-words"
                 x-show="currentLine.length"
                 x-text="currentLine">
            </div>

            <!-- Spinner solange noch kein Delta kam -->
            <div class="w-3 h-3 border-2 border-[var(--ui-primary)] border-t-transparent rounded-full animate-spin"
                 x-show="!hasDelta"
                 aria-hidden="true"></div>

            <!-- Toolchip -->
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
        :placeholder="isStreaming ? 'Verarbeite…' : 'Nachricht eingeben…'"
        :disabled="isStreaming || isProcessing"
        :aria-disabled="(isStreaming || isProcessing) ? 'true' : 'false'"
        wire:key="terminal-input"
      />
      <template x-if="canCancel">
        <button
          type="button"
          wire:click="cancelRequest"
          @click="abortStream()"
          class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-danger)]/60 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition"
          wire:key="terminal-cancel"
        >
          Abbrechen
        </button>
      </template>
      <template x-if="!canCancel">
        <button
          type="button"
          wire:click="sendMessage"
          class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition"
          :disabled="isProcessing"
          :aria-disabled="isProcessing ? 'true' : 'false'"
          wire:key="terminal-send"
        >
          Senden
        </button>
      </template>
    </div>
  </div>

  <script>
    // Dev-Logs optional
    window.__DEV__ = window.__DEV__ ?? false;

    function chatTerminal(initial = {}){
      return {
        // Alpine/Livewire entangled Flags (reaktiv)
        isStreaming:  initial.isStreaming ?? false,
        isProcessing: initial.isProcessing ?? false,
        canCancel:    initial.canCancel ?? false,

        // SSE/Stream State
        es: null,
        hasDelta: false,
        // Zeilenweiser Stream:
        streamLines: [],      // [{id,text,visible}]
        currentLine: '',      // die wachsende Zeile
        lineIdSeq: 0,

        // Retry/Backoff
        retryCount: 0,
        maxRetry: 3,
        backoffBaseMs: 400,

        // Refs
        bodyEl: null,

        // Init
        init(){
          this.bodyEl = this.$refs.body;
          window.addEventListener('beforeunload', () => this.closeStream());
          this.$nextTick(() => { if(Alpine?.store('page')?.terminalOpen && this.bodyEl){ this.bodyEl.scrollTop = this.bodyEl.scrollHeight; }});
        },

        // Helpers – UI
        scrollToEnd(){
          if(this.bodyEl){
            requestAnimationFrame(() => { this.bodyEl.scrollTop = this.bodyEl.scrollHeight; });
          }
        },

        // Zeilenlogik
        pushFinalLine(text){
          const id = `line-${++this.lineIdSeq}`;
          const line = { id, text, visible: false };
          this.streamLines.push(line);
          this.$nextTick(() => {
            line.visible = true; // Fade-In triggern
            this.scrollToEnd();
          });
        },
        appendToCurrent(delta){
          this.currentLine += delta;
          this.scrollToEnd();
        },
        finalizeCurrentIfAny(){
          if(this.currentLine.length){
            this.pushFinalLine(this.currentLine);
            this.currentLine = '';
          }
        },
        handleDeltaText(delta){
          if(!delta) return;
          // Split nach Zeilenumbrüchen
          const parts = String(delta).split(/\r?\n/);
          for(let i=0;i<parts.length;i++){
            const seg = parts[i];
            const isLast = i === parts.length - 1;
            if(!isLast){
              // Segment + Zeilenumbruch -> aktuelle Zeile abschließen
              this.appendToCurrent(seg);
              this.finalizeCurrentIfAny();
            } else {
              // letztes Segment: nur anhängen (noch offen)
              if(seg.length) this.appendToCurrent(seg);
            }
          }
        },

        // SSE
        startStream(url){
          this.closeStream();

          // Flags beim Start (Eingabe sperren)
          this.isProcessing = false;
          this.isStreaming  = true;
          this.canCancel    = true;
          $wire?.set?.('isProcessing', false);
          $wire?.set?.('isStreaming', true);
          $wire?.set?.('canCancel', true);

          // Reset Stream-UI
          this.streamLines = [];
          this.currentLine = '';
          this.hasDelta = false;

          try {
            if(window.__DEV__) console.log('[Terminal SSE] startStream →', url);
            this.es = new EventSource(url);

            this.es.onopen = () => {
              if(window.__DEV__) console.log('[Terminal SSE] connection open');
              this.retryCount = 0;
            };

            this.es.onmessage = (e) => {
              if(!e.data) return;
              if(window.__DEV__) console.log('[Terminal SSE] onmessage raw:', e.data);

              if(e.data === '[DONE]'){
                if(window.__DEV__) console.log('[Terminal SSE] DONE');
                this.closeStream();
                window.dispatchEvent(new CustomEvent('ai-stream-complete'));
                return;
              }

              // Robust JSON parsing
              let data = null;
              try { data = JSON.parse(e.data); } catch(_) {}

              if(data && (typeof data.delta === 'string' || typeof data.delta === 'number')){
                if(!this.hasDelta) this.hasDelta = true;
                this.handleDeltaText(String(data.delta));
                if(data.tool && $wire?.set){ $wire.set('currentTool', data.tool); }
                window.dispatchEvent(new CustomEvent('ai-stream-delta', { detail: { delta: String(data.delta) } }));
              } else {
                // Non-JSON oder anderes Format: ignorieren
                if(window.__DEV__) console.warn('[Terminal SSE] skip non-JSON or missing delta');
              }
            };

            this.es.onerror = (err) => {
              if(window.__DEV__) console.error('[Terminal SSE] error:', err);
              this.closeStream();
              if(this.retryCount < this.maxRetry){
                const delay = Math.min(4000, this.backoffBaseMs * Math.pow(2, this.retryCount++));
                setTimeout(() => this.startStream(url), delay);
              } else {
                window.dispatchEvent(new CustomEvent('ai-stream-error'));
              }
            };
          } catch(e){
            if(window.__DEV__) console.error('[Terminal SSE] start error', e);
            this.onStreamError();
          }
        },

        closeStream(){
          if(this.es){ try { this.es.close(); } catch(_){} this.es = null; }
        },

        abortStream(){
          // manuell aus UI
          this.closeStream();
          this.isStreaming = false;
          this.isProcessing = false;
          this.canCancel = false;
          $wire?.set?.('isStreaming', false);
          $wire?.set?.('isProcessing', false);
          $wire?.set?.('canCancel', false);
          $wire?.set?.('progressText', '');
          $wire?.set?.('currentTool', null);
          window.dispatchEvent(new CustomEvent('ai-stream-error'));
        },

        // Event-Brücken
        onStreamComplete(){
          if(window.__DEV__) console.log('[Terminal SSE] ai-stream-complete');
          // letzte offene Zeile finalisieren (falls ohne trailing \n beendet)
          this.finalizeCurrentIfAny();
          this.canCancel = false; $wire?.set?.('canCancel', false);
          this.scrollToEnd();
          // Jetzt drainen: History nachladen, dann Stream-UI leeren
          this.onStreamDrained();
        },

        onStreamError(){
          if(window.__DEV__) console.log('[Terminal SSE] ai-stream-error');
          this.isStreaming = false;
          this.isProcessing = false;
          this.canCancel = false;
          $wire?.set?.('isStreaming', false);
          $wire?.set?.('isProcessing', false);
          $wire?.set?.('canCancel', false);
          $wire?.set?.('progressText', '');
          $wire?.set?.('currentTool', null);
        },

        async onStreamDrained(){
          // Flags zurücksetzen, dann History reload
          this.isStreaming = false;
          this.canCancel = false;
          this.isProcessing = false;
          $wire?.set?.('isStreaming', false);
          $wire?.set?.('canCancel', false);
          $wire?.set?.('isProcessing', false);
          try { await $wire?.call?.('loadMessages'); } catch(_) {}
          setTimeout(() => {
            // Stream-UI leeren NACHDEM die Historie steht
            this.streamLines = [];
            this.currentLine = '';
            $wire?.set?.('currentTool', null);
            this.scrollToEnd();
            window.dispatchEvent(new CustomEvent('terminal-scroll'));
          }, 60);
        },
      };
    }
  </script>
</div>