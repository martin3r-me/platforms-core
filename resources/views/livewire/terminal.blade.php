<div
  x-data="chatTerminal()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle(); $nextTick(() => { const c = $refs.body; if(c){ c.scrollTop = c.scrollHeight } })"
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
                :class="$wire.activeChatId === {{ $chat['id'] }}
                    ? 'text-[var(--ui-primary)] bg-[var(--ui-surface)]'
                    : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]'"
                :aria-current="$wire.activeChatId === {{ $chat['id'] }} ? 'true' : 'false'"
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
        <div class="flex items-start gap-2" x-show="$wire.isStreaming" wire:ignore>
          <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">AI:</span>
          <div class="flex-1 flex flex-col gap-1"
               role="log"
               aria-live="polite"
               aria-atomic="false">
            <!-- Streaming-Status -->
            <div class="text-[var(--ui-secondary)] text-xs break-words">
              Verarbeite Anfrage...
            </div>
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
        :placeholder="$wire.isStreaming ? 'Verarbeite…' : 'Nachricht eingeben…'"
        :disabled="$wire.isStreaming || $wire.isProcessing"
        :aria-disabled="($wire.isStreaming || $wire.isProcessing) ? 'true' : 'false'"
        wire:key="terminal-input"
      />
      <template x-if="$wire.canCancel">
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
      <template x-if="!$wire.canCancel">
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
      </template>
    </div>
  </div>

  <script>
    function chatTerminal(){
      return {
        // Minimal Alpine.js - nur für UI-Interaktionen
        get open(){ return Alpine?.store('page')?.terminalOpen ?? false; },
        toggle(){ if(Alpine?.store('page')) Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen; },
        
        init(){
          // Nur grundlegende Initialisierung
          window.addEventListener('beforeunload', () => this.closeStream());
        },
        
        closeStream(){
          // Minimal cleanup
        }
      };
    }
  </script>
</div>