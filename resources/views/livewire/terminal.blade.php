<div
  x-data="terminalShell()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle()"
  class="w-full"
  wire:key="terminal-root"
>
  <!-- Always-visible strip (15px) — click to toggle -->
  <div
    class="h-[25px] w-full bg-[var(--ui-surface)]/95 backdrop-blur border-t border-[var(--ui-border)]/60 flex items-center px-3 cursor-pointer select-none hover:bg-[var(--ui-surface)]"
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
    <!-- Panel Content: Sidebar + Main -->
    <div class="flex-1 min-h-0 flex opacity-100 transition-opacity duration-200"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-content">

      <!-- Sidebar (200px) -->
      <div class="w-[200px] flex-shrink-0 border-r border-[var(--ui-border)]/60 overflow-y-auto py-2" wire:key="terminal-sidebar">

        <!-- Chats Section -->
        <div class="px-2 mb-3" x-data="{ chatsOpen: true }">
          <button @click="chatsOpen = !chatsOpen" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">
            <span>Chats</span>
            <svg class="w-3 h-3 transition-transform duration-150" :class="chatsOpen ? '' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div x-show="chatsOpen" x-collapse class="mt-0.5 space-y-px">
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md bg-[var(--ui-primary-5)] text-[var(--ui-primary)] text-xs transition hover:bg-[var(--ui-primary-10)]">
              <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[9px] font-semibold flex-shrink-0">AM</div>
              <span class="truncate flex-1 text-left">Anna M.</span>
              <span class="w-4 h-4 rounded-full bg-[var(--ui-primary)] text-white text-[9px] flex items-center justify-center flex-shrink-0">2</span>
            </button>
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-[9px] font-semibold flex-shrink-0">TK</div>
              <span class="truncate flex-1 text-left">Tom K.</span>
            </button>
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <div class="w-5 h-5 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-[9px] font-semibold flex-shrink-0">LR</div>
              <span class="truncate flex-1 text-left">Lisa R.</span>
            </button>
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <div class="w-5 h-5 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center text-[9px] font-semibold flex-shrink-0">MH</div>
              <span class="truncate flex-1 text-left">Max H.</span>
              <span class="w-4 h-4 rounded-full bg-[var(--ui-muted)]/30 text-[var(--ui-muted)] text-[9px] flex items-center justify-center flex-shrink-0">1</span>
            </button>
          </div>
        </div>

        <!-- Channels Section -->
        <div class="px-2" x-data="{ channelsOpen: true }">
          <button @click="channelsOpen = !channelsOpen" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">
            <span>Channels</span>
            <svg class="w-3 h-3 transition-transform duration-150" :class="channelsOpen ? '' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div x-show="channelsOpen" x-collapse class="mt-0.5 space-y-px">
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <span class="text-[var(--ui-muted)]">#</span>
              <span class="truncate flex-1 text-left">general</span>
              <span class="w-4 h-4 rounded-full bg-[var(--ui-primary)] text-white text-[9px] flex items-center justify-center flex-shrink-0">5</span>
            </button>
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <span class="text-[var(--ui-muted)]">#</span>
              <span class="truncate flex-1 text-left">development</span>
            </button>
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <span class="text-[var(--ui-muted)]">#</span>
              <span class="truncate flex-1 text-left">design</span>
            </button>
            <button class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-[var(--ui-secondary)] text-xs transition hover:bg-[var(--ui-surface-hover)]">
              <span class="text-[var(--ui-muted)]">#</span>
              <span class="truncate flex-1 text-left">random</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Main Chat Area -->
      <div class="flex-1 min-w-0 flex flex-col" wire:key="terminal-main">

        <!-- Chat Header -->
        <div class="h-10 px-3 flex items-center gap-2 text-xs border-b border-[var(--ui-border)]/60 flex-shrink-0">
          <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[9px] font-semibold flex-shrink-0">AM</div>
          <span class="font-medium text-[var(--ui-body-color)]">Anna M.</span>
          <span class="text-[var(--ui-muted)]">·</span>
          <span class="text-[10px] text-[var(--ui-muted)]">Online</span>
        </div>

        <!-- Messages -->
        <div class="flex-1 min-h-0 overflow-y-auto px-3 py-3 text-xs" x-ref="body">
          <div class="space-y-3">
            <div class="flex gap-2">
              <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">AM</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2">
                  <span class="font-medium text-[var(--ui-body-color)]">Anna M.</span>
                  <span class="text-[10px] text-[var(--ui-muted)]">Heute, 14:22</span>
                </div>
                <p class="mt-0.5 text-[var(--ui-secondary)]">Hey, hast du den Statusbericht schon gesehen?</p>
              </div>
            </div>

            <div class="flex gap-2">
              <div class="w-6 h-6 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">Du</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2">
                  <span class="font-medium text-[var(--ui-body-color)]">Du</span>
                  <span class="text-[10px] text-[var(--ui-muted)]">Heute, 14:25</span>
                </div>
                <p class="mt-0.5 text-[var(--ui-secondary)]">Noch nicht, schaue ich mir gleich an. Gibt es etwas Dringendes?</p>
              </div>
            </div>

            <div class="flex gap-2">
              <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">AM</div>
              <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2">
                  <span class="font-medium text-[var(--ui-body-color)]">Anna M.</span>
                  <span class="text-[10px] text-[var(--ui-muted)]">Heute, 14:28</span>
                </div>
                <p class="mt-0.5 text-[var(--ui-secondary)]">Die Zahlen für Q1 sehen gut aus, aber bitte einmal die Umsatztabelle gegenprüfen. Ich bin mir bei den März-Werten nicht sicher.</p>
              </div>
            </div>

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
          </div>
        </div>

        <!-- Input -->
        <div class="h-10 px-3 flex items-center gap-2 border-t border-[var(--ui-border)]/60 flex-shrink-0">
          <input
            type="text"
            class="flex-1 bg-transparent outline-none text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] rounded-md px-2 py-1 border border-[var(--ui-border)]/60 focus:border-[var(--ui-primary)]/40"
            placeholder="Nachricht an Anna M. …"
            disabled
          />
          <button
            type="button"
            class="inline-flex items-center justify-center h-7 px-3 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] opacity-60 cursor-not-allowed text-xs"
            disabled
          >
            Senden
          </button>
        </div>
      </div>
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
