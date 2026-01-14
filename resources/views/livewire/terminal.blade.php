<div
  x-data="terminalShell()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle()"
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

    <!-- Body -->
    <div
      class="flex-1 min-h-0 overflow-y-auto px-3 py-2 pb-6 text-xs font-mono text-[var(--ui-secondary)] opacity-100 transition-opacity duration-200"
      :class="open ? 'opacity-100' : 'opacity-0'"
      x-ref="body"
      wire:key="terminal-body"
    >
      <div class="space-y-2" wire:key="terminal-body-inner">
        <div class="text-[var(--ui-muted)]">Terminal (UI) – Inhalt wird später wieder aufgebaut.</div>
        <div class="mt-2 space-y-1 text-[var(--ui-secondary)]">
          <div>$ …</div>
        </div>
      </div>
    </div>

    <!-- Prompt (UI only) -->
    <div class="h-10 px-3 flex items-center gap-2 border-t border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200 flex-shrink-0"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-prompt">
      <span class="text-[var(--ui-muted)] text-xs font-mono">$</span>
      <input
        type="text"
        class="flex-1 bg-transparent outline-none text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]"
        placeholder="(deaktiviert) …"
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
          // Keep behavior: when opened, scroll to bottom.
          this.$nextTick(() => {
            const c = this.$refs.body;
            if (c && this.open) c.scrollTop = c.scrollHeight;
          });
        },
      };
    }
  </script>
</div>

