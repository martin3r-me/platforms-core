<div
    x-data="{ get open(){ return Alpine?.store('page')?.terminalOpen ?? false }, toggle(){ Alpine?.store('page') && (Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen) } }"
    x-on:toggle-terminal.window="toggle()"
    class="w-full"
>

    <!-- Slide container (wie Sidebars: Größe animiert) -->
    <div
        class="w-full border-t border-[var(--ui-border)]/60 bg-[var(--ui-surface)]/95 backdrop-blur overflow-hidden transition-[max-height] duration-300 ease-out flex flex-col"
        x-bind:style="open ? 'max-height: 14rem' : 'max-height: 0px'"
        style="max-height: 0px;"
    >
        <!-- Header -->
        <div class="h-10 px-3 flex items-center justify-between text-xs border-b border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200" :class="open ? 'opacity-100' : 'opacity-0'">
            <div class="flex items-center gap-2 text-[var(--ui-muted)]">
                @svg('heroicon-o-command-line', 'w-4 h-4')
                <span>Terminal</span>
            </div>
            <div class="flex items-center gap-1">
                <button @click="toggle()" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition">
                    @svg('heroicon-o-x-mark','w-4 h-4')
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="flex-1 min-h-0 overflow-y-auto px-3 py-2 text-xs font-mono text-[var(--ui-secondary)] opacity-100 transition-opacity duration-200" :class="open ? 'opacity-100' : 'opacity-0'">
            <div class="text-[var(--ui-muted)]">Tippe "help" für verfügbare Befehle…</div>
            <div class="mt-2 space-y-1">
                <div>$ help</div>
                <div>- kpi            Zeigt Team-KPIs</div>
                <div>- tasks --mine   Eigene Aufgaben</div>
            </div>
        </div>

        <!-- Prompt -->
        <div class="h-10 px-3 flex items-center gap-2 border-t border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200 flex-shrink-0" :class="open ? 'opacity-100' : 'opacity-0'">
            <span class="text-[var(--ui-muted)] text-xs font-mono">$</span>
            <input type="text" class="flex-1 bg-transparent outline-none text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]" placeholder="Befehl eingeben… (nur Demo)" />
            <button class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition">Ausführen</button>
        </div>
    </div>
</div>
