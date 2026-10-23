{{--
    Kontext-Panel (rechte Sidebar) — siehe ui-tailwind/docs/module-layout.md
    ≥1440px: angedockt (offen/eingeklappt, Breite ziehbar, pro User gemerkt)
    768–1439px: Icon-Leiste, Klick öffnet als Overlay
    <768px: schwebender Button, Panel als Vollbild-Overlay
--}}
<div style="display: contents">
@if ($contextId && count($panelApps))
    <aside
        x-data="{
            wide: window.matchMedia('(min-width: 1440px)').matches,
            small: window.matchMedia('(max-width: 767px)').matches,
            overlay: false,
            resizing: false,
            init() {
                const w = window.matchMedia('(min-width: 1440px)');
                const s = window.matchMedia('(max-width: 767px)');
                w.addEventListener('change', e => { this.wide = e.matches; this.overlay = false; });
                s.addEventListener('change', e => { this.small = e.matches; this.overlay = false; });
            },
            get pinned() { const v = this.$store.ui?.g('context_panel', 'open'); return v === undefined ? true : v; },
            get width() { const v = this.$store.ui?.g('context_panel', 'width'); return Math.max(320, Math.min(720, v === undefined ? 400 : v)); },
            get docked() { return this.wide && this.pinned; },
            get showBody() { return this.docked || this.overlay; },
            open(key) {
                if (key) { this.pick(key); }
                if (this.wide) { this.$store.ui?.gSet('context_panel', 'open', true); } else { this.overlay = true; }
            },
            close() {
                if (this.wide) { this.$store.ui?.gSet('context_panel', 'open', false); }
                this.overlay = false;
            },
            pick(key) {
                this.$store.ui?.gSet('context_panel', 'app', key);
                this.$wire.selectApp(key);
            },
            startResize(e) {
                this.resizing = true;
                const startX = e.clientX, startW = this.width;
                const move = ev => this.$store.ui?.gSet('context_panel', 'width', Math.max(320, Math.min(720, startW - (ev.clientX - startX))));
                const up = () => { this.resizing = false; document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); };
                document.addEventListener('mousemove', move);
                document.addEventListener('mouseup', up);
            },
        }"
        x-on:keydown.escape.window="overlay = false"
        :style="small ? 'width: 0px' : (docked ? 'width: ' + width + 'px' : 'width: 44px')"
        :class="(resizing ? '' : 'transition-all duration-200') + (small ? '' : ' border-l')"
        class="terminal-light relative flex-shrink-0 h-screen bg-[color:var(--nx-bg)] border-[color:var(--nx-line)]"
        aria-label="Kontext"
    >
        {{-- Icon-Leiste (eingeklappt / schmaler Bildschirm) --}}
        <div x-show="!docked && !small" x-cloak class="h-full w-11 flex flex-col items-center">
            <button type="button" x-on:click="open()" title="Kontext öffnen"
                class="h-11 w-11 flex-shrink-0 flex items-center justify-center border-b border-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)] transition-colors">
                @svg('heroicon-o-chevron-double-left', 'w-4 h-4')
            </button>
            <div class="flex flex-col items-center gap-1 py-2">
                @foreach ($panelApps as $key => $app)
                    <button type="button" x-on:click="open('{{ $key }}')" title="{{ $app->label() }}"
                        @class([
                            'w-8 h-8 flex items-center justify-center rounded-md transition-colors',
                            'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' => $activeApp === $key,
                            'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]' => $activeApp !== $key,
                        ])>
                        @svg('heroicon-o-' . $app->icon(), 'w-4 h-4')
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Overlay-Hintergrund (nicht angedockt) --}}
        <div x-show="overlay && !docked" x-cloak x-on:click="overlay = false" x-transition.opacity
            class="fixed inset-0 bg-black/20" style="z-index: 39"></div>

        {{-- Panel --}}
        <div x-show="showBody" x-cloak
            :class="docked ? 'relative h-full' : 'fixed top-0 right-0 h-screen'"
            :style="docked ? '' : ('z-index: 40; box-shadow: var(--nx-shadow-pop); width: ' + (small ? '100vw' : Math.min(width, window.innerWidth - 44) + 'px'))"
            class="flex flex-col bg-[color:var(--nx-bg)] overflow-hidden">

            {{-- Kopf: Datensatz + Einklappen --}}
            <div class="h-11 flex-shrink-0 flex items-center gap-2 px-3 border-b border-[color:var(--nx-line)]">
                <span class="min-w-0 flex-1 truncate text-sm font-medium text-[color:var(--nx-text)]" title="{{ $subject }}">{{ $subject ?: 'Kontext' }}</span>
                <button type="button" x-on:click="close()" :title="docked ? 'Einklappen' : 'Schließen'"
                    class="inline-flex items-center justify-center w-7 h-7 flex-shrink-0 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)] transition-colors">
                    <span x-show="docked">@svg('heroicon-o-chevron-double-right', 'w-4 h-4')</span>
                    <span x-show="!docked" x-cloak>@svg('heroicon-o-x-mark', 'w-4 h-4')</span>
                </button>
            </div>

            {{-- Reiter --}}
            <div class="flex-shrink-0 flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-[color:var(--nx-line)]">
                @foreach ($panelApps as $key => $app)
                    <button type="button" x-on:click="pick('{{ $key }}')"
                        @class([
                            'inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium whitespace-nowrap transition-colors',
                            'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' => $activeApp === $key,
                            'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]' => $activeApp !== $key,
                        ])>
                        @svg('heroicon-o-' . $app->icon(), 'w-3.5 h-3.5')
                        {{ $app->label() }}
                    </button>
                @endforeach
            </div>

            {{-- Inhalt: nur die aktive App wird gerendert --}}
            <div class="flex-1 min-h-0 flex flex-col overflow-y-auto">
                @if ($active)
                    @livewire($active->livewireComponent(), $activeProps, key('cp-' . $active->key() . '-' . md5($contextType) . '-' . $contextId))
                @endif
            </div>

            {{-- Breite ziehen (nur angedockt) --}}
            <div x-show="docked" x-on:mousedown.prevent="startResize($event)"
                class="absolute top-0 left-0 w-1 h-full cursor-ew-resize group/resize" style="z-index: 20">
                <div class="absolute inset-y-0 left-0 w-px bg-transparent group-hover/resize:bg-[color:var(--nx-line-strong)] transition"></div>
            </div>
        </div>

        {{-- Mobil: schwebender Button --}}
        <button type="button" x-show="small && !overlay" x-cloak x-on:click="open()" title="Kontext"
            class="fixed w-11 h-11 flex items-center justify-center rounded-full bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]"
            style="bottom: 16px; right: 16px; z-index: 38; box-shadow: var(--nx-shadow-pop)">
            @svg('heroicon-o-squares-2x2', 'w-5 h-5')
        </button>
    </aside>
@endif
</div>
