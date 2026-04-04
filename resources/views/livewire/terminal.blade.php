<div
  x-data="terminalShell()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle()"
  class="w-full"
  wire:key="terminal-root"
>
  <!-- Always-visible strip (15px) — click to toggle -->
  <div
    class="h-[15px] w-full bg-[var(--ui-surface)]/95 backdrop-blur border-t border-[var(--ui-border)]/60 flex items-center px-3 cursor-pointer select-none hover:bg-[var(--ui-surface)]"
    @click="toggle()"
    wire:key="terminal-strip"
  >
    <div class="flex items-center gap-4 text-[9px] font-mono text-[var(--ui-muted)] truncate w-full">
      <span class="flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span>
        Anna M. — Statusbericht verschickt
        <span class="opacity-60">14:32</span>
      </span>
      <span class="flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 inline-block"></span>
        Tom K. — Neues Ticket erstellt
        <span class="opacity-60">13:15</span>
      </span>
      <span class="flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 inline-block"></span>
        Lisa R. — Kommentar hinzugefügt
        <span class="opacity-60">11:48</span>
      </span>
    </div>
    <div class="ml-auto flex-shrink-0 text-[var(--ui-muted)]">
      <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd" />
      </svg>
    </div>
  </div>

  <!-- Slide container -->
  <div
    class="w-full border-t border-[var(--ui-border)]/60 bg-[var(--ui-surface)]/95 backdrop-blur overflow-hidden transition-[max-height] duration-300 ease-out flex flex-col"
    x-bind:style="open ? 'max-height: 42rem' : 'max-height: 0px'"
    style="max-height: 0px;"
    wire:key="terminal-slide"
  >
    <!-- Header -->
    <div class="h-10 px-3 flex items-center justify-between text-xs border-b border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-header">
      <div class="flex items-center gap-2 text-[var(--ui-muted)]">
        @svg('heroicon-o-chat-bubble-left-right', 'w-4 h-4')
        <span>Nachrichten</span>
      </div>
      <div class="flex items-center gap-1">
        <button type="button" @click="toggle()"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition"
                aria-label="Panel schließen">
          @svg('heroicon-o-x-mark','w-4 h-4')
        </button>
      </div>
    </div>

    <!-- Body -->
    <div
      class="flex-1 min-h-0 overflow-y-auto px-3 py-2 pb-6 text-xs text-[var(--ui-secondary)] opacity-100 transition-opacity duration-200"
      :class="open ? 'opacity-100' : 'opacity-0'"
      x-ref="body"
      wire:key="terminal-body"
    >
      <div class="space-y-3" wire:key="terminal-body-inner">
        <!-- Dummy messages -->
        <div class="flex gap-2">
          <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">AM</div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline gap-2">
              <span class="font-medium text-[var(--ui-body-color)]">Anna M.</span>
              <span class="text-[10px] text-[var(--ui-muted)]">Heute, 14:32</span>
            </div>
            <p class="mt-0.5 text-[var(--ui-secondary)]">Statusbericht für Q1 wurde verschickt. Bitte gegenprüfen.</p>
          </div>
        </div>

        <div class="flex gap-2">
          <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">TK</div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline gap-2">
              <span class="font-medium text-[var(--ui-body-color)]">Tom K.</span>
              <span class="text-[10px] text-[var(--ui-muted)]">Heute, 13:15</span>
            </div>
            <p class="mt-0.5 text-[var(--ui-secondary)]">Neues Ticket #4821 erstellt — Server-Migration Phase 2.</p>
          </div>
        </div>

        <div class="flex gap-2">
          <div class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">LR</div>
          <div class="flex-1 min-w-0">
            <div class="flex items-baseline gap-2">
              <span class="font-medium text-[var(--ui-body-color)]">Lisa R.</span>
              <span class="text-[10px] text-[var(--ui-muted)]">Heute, 11:48</span>
            </div>
            <p class="mt-0.5 text-[var(--ui-secondary)]">Kommentar zu Projekt «Relaunch» hinzugefügt: Design-Review ist abgeschlossen.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="h-10 px-3 flex items-center gap-2 border-t border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200 flex-shrink-0"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-prompt">
      <input
        type="text"
        class="flex-1 bg-transparent outline-none text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-md px-2 py-1 border border-[var(--ui-border)]/60 focus:border-[var(--ui-primary)]/40"
        placeholder="Nachricht schreiben …"
        disabled
      />
      <button
        type="button"
        class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] opacity-60 cursor-not-allowed"
        disabled
      >
        Senden
      </button>
    </div>
  </div>

  <script>
    function terminalShell(){
      return {
        get open(){ return Alpine?.store('page')?.terminalOpen ?? false; },
        toggle(){ if(Alpine?.store('page')) Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen; },
        init(){
          this.$nextTick(() => {
            const c = this.$refs.body;
            if (c && this.open) c.scrollTop = c.scrollHeight;
          });
        },
      };
    }
  </script>
</div>
