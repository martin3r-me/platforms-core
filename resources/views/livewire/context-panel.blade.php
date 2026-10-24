{{--
    Kontext-Panel (rechte Sidebar) — siehe ui-tailwind/docs/module-layout.md
    ≥1440px: angedockt (offen/eingeklappt, Breite ziehbar, pro User gemerkt)
    768–1439px: Icon-Leiste, Klick öffnet als Overlay
    <768px: schwebender Button, Panel als Vollbild-Overlay
    Ohne Seitenkontext bleibt nur die schmale Leiste mit dem Algedonic-Signal (unten) —
    das Signal ist überall erreichbar, Mobil als kleiner schwebender Knopf.
--}}
@php
    $hasContext = $contextId && count($panelApps);
    // Auf einer Einheit ist sie beim Signal gleich vorausgewählt
    $algedonicPayload = $hasContext && $contextType === 'Platform\\Organization\\Models\\OrganizationEntity'
        ? ['payload' => ['entity_id' => $contextId, 'entity_name' => $subject]]
        : (object) [];
@endphp
<div style="display: contents">
    <style>
        /* Eigene Fläche: etwas dunkler als Seite (weiß) und Hauptsidebar, Kopf noch eine Stufe tiefer */
        .cp-panel { --cp-bg: #f1f0eb; --cp-head: #e8e7e0; background: var(--cp-bg); }
        .cp-head { background: var(--cp-head); }
        .cp-docked { box-shadow: -10px 0 24px -14px rgba(15,15,15,.18); }
        .cp-overline { font-size: 10px; letter-spacing: .06em; text-transform: uppercase; color: var(--nx-faint); }
        /* Icon-Leiste (eingeklappt) */
        .cp-tab { color: var(--nx-muted); }
        .cp-tab:hover { background: rgba(55,53,47,.06); color: var(--nx-text); }
        .cp-tab-active, .cp-tab-active:hover { background: #fff; color: var(--nx-text); box-shadow: var(--nx-shadow-card); }
        /* Reiter (offen): eine Zeile, nur der aktive mit Namen, Unterstrich gleitet mit */
        .cp-tabs { scrollbar-width: none; }
        .cp-tabs::-webkit-scrollbar { display: none; }
        .cp-rt { display: inline-flex; align-items: center; height: 34px; padding: 0 8px; gap: 0; border-radius: 6px 6px 0 0;
                 color: var(--nx-muted); white-space: nowrap; transition: color .15s, background-color .15s, gap .2s; }
        .cp-rt:hover { color: var(--nx-text); background: rgba(55,53,47,.05); }
        .cp-rt-label { display: inline-block; max-width: 0; opacity: 0; overflow: hidden; font-size: 12px; font-weight: 500;
                       transition: max-width .22s ease, opacity .18s ease; }
        .cp-rt.is-active { color: var(--nx-text); gap: 6px; }
        .cp-rt.is-active .cp-rt-label { max-width: 140px; opacity: 1; }
        .cp-rt.is-empty:not(.is-active) { color: var(--nx-faint); opacity: .7; }
        .cp-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 16px; height: 16px; padding: 0 4px;
                    margin-left: 4px; border-radius: 999px; font-size: 10px; font-weight: 600; line-height: 1; font-variant-numeric: tabular-nums;
                    background: rgba(55,53,47,.08); color: var(--nx-muted); }
        .cp-rt.is-active .cp-badge { background: var(--nx-text); color: #fff; }
        .cp-ink { position: absolute; bottom: -1px; height: 2px; border-radius: 2px; background: var(--nx-text);
                  transition: left .22s ease, width .22s ease; }
        .cp-rail-badge { position: absolute; top: 1px; right: 1px; min-width: 13px; height: 13px; padding: 0 3px; border-radius: 999px;
                         font-size: 9px; font-weight: 600; line-height: 13px; text-align: center; background: var(--nx-faint); color: #fff; }
        /* Startbreite vor Alpine (aus der Präferenz) — danach übernimmt :style */
        .cp-aside { width: 44px; }
        @media (min-width: 1440px) { .cp-aside { width: var(--cp-w); } }
        @media (max-width: 767px) { .cp-aside { width: 0; } }
        /* Algedonic-Signal: unten in der Leiste bzw. im Panel-Fuß */
        .cp-alg { color: var(--nx-danger); transition: background-color .15s, color .15s; }
        .cp-alg:hover { background: color-mix(in srgb, var(--nx-danger) 10%, transparent); }
        .cp-foot { border-top: 1px solid var(--nx-line); }
        .cp-alg-fab { background: var(--nx-surface); color: var(--nx-danger); box-shadow: var(--nx-shadow-pop); }
    </style>
    <aside
        x-data="{
            wide: window.matchMedia('(min-width: 1440px)').matches,
            small: window.matchMedia('(max-width: 767px)').matches,
            overlay: false,
            resizing: false,
            current: $wire.entangle('activeApp'),
            get hasContext() { return !! this.$wire.contextId; },
            signal() { this.$dispatch('open-modal-algedonic', @js($algedonicPayload)); this.overlay = false; },
            ink: { left: 0, width: 0 },
            moveInk() {
                const el = this.$refs['tab-' + this.current];
                if (el) { this.ink = { left: el.offsetLeft + 6, width: el.offsetWidth - 12 }; }
            },
            init() {
                this.$watch('current', () => this.$nextTick(() => this.moveInk()));
                this.$watch('showBody', v => v && this.$nextTick(() => this.moveInk()));
                this.$nextTick(() => this.moveInk());
                const w = window.matchMedia('(min-width: 1440px)');
                const s = window.matchMedia('(max-width: 767px)');
                w.addEventListener('change', e => { this.wide = e.matches; this.overlay = false; });
                s.addEventListener('change', e => { this.small = e.matches; this.overlay = false; });
            },
            get pinned() { const v = this.$store.ui?.g('context_panel', 'open'); return v === undefined ? true : v; },
            get width() { const v = this.$store.ui?.g('context_panel', 'width'); return Math.max(320, Math.min(720, v === undefined ? 400 : v)); },
            get docked() { return this.wide && this.pinned && this.hasContext; },
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
                this.current = key;
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
        :style="{ width: small ? '0px' : (docked ? width + 'px' : '44px') }"
        :class="(resizing ? '' : 'transition-all duration-200') + (small ? '' : ' border-l') + (docked ? ' cp-docked' : '')"
        class="terminal-light cp-aside cp-panel relative flex-shrink-0 h-screen"
        style="--cp-w: {{ $hasContext && $initialOpen ? $initialWidth : 44 }}px; z-index: 5; border-color: var(--nx-line-strong)"
        aria-label="Kontext"
    >
        @if ($hasContext)
        {{-- Icon-Leiste (eingeklappt / schmaler Bildschirm) --}}
        <div x-show="!docked && !small" x-cloak class="h-full w-11 flex flex-col items-center">
            <button type="button" x-on:click="open()" title="Kontext öffnen"
                class="cp-head h-11 w-11 flex-shrink-0 flex items-center justify-center border-b border-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition-colors">
                @svg('heroicon-o-chevron-double-left', 'w-4 h-4')
            </button>
            <div class="flex flex-col items-center gap-1 py-2">
                @foreach ($panelApps as $key => $app)
                    @php($badge = $badges[$key] ?? null)
                    <button type="button" x-on:click="open('{{ $key }}')" title="{{ $app->label() }}{{ $badge ? ' · ' . $badge : '' }}"
                        :class="{ 'cp-tab-active': current === '{{ $key }}' }"
                        @class([
                            'cp-tab relative w-8 h-8 flex items-center justify-center rounded-md transition-colors',
                            'cp-tab-active' => $activeApp === $key,
                        ])
                        @if (array_key_exists($key, $badges) && ! $badge) style="opacity: .5" @endif>
                        @svg('heroicon-o-' . $app->icon(), 'w-4 h-4')
                        @if ($badge && preg_match('/^\d+\+?$/', $badge)) {{-- Zeit („12h“) nur im Tooltip --}}
                            <span class="cp-rail-badge">{{ $badge }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <div class="mt-auto"></div>
        </div>
        @endif

        {{-- Algedonic-Signal in der Leiste — immer unten, auch ohne Kontext --}}
        <div x-show="!docked && !small" x-cloak class="absolute bottom-0 left-0 w-11 flex justify-center pb-2">
            <button type="button" x-on:click="signal()" title="Algedonic-Signal — direkt an die oberste Ebene"
                class="cp-alg w-8 h-8 flex items-center justify-center rounded-md">
                @svg('heroicon-o-bell-alert', 'w-4 h-4')
            </button>
        </div>

        @if ($hasContext)
        {{-- Overlay-Hintergrund (nicht angedockt) --}}
        <div x-show="overlay && !docked" x-cloak x-on:click="overlay = false" x-transition.opacity
            class="fixed inset-0 bg-black/20" style="z-index: 39"></div>

        {{-- Panel --}}
        <div x-show="showBody" x-cloak
            :class="docked ? 'relative h-full' : 'fixed top-0 right-0 h-screen'"
            :style="docked ? '' : ('z-index: 40; box-shadow: var(--nx-shadow-pop); width: ' + (small ? '100vw' : Math.min(width, window.innerWidth - 44) + 'px'))"
            class="cp-panel flex flex-col overflow-hidden">

            {{-- Kopf: Datensatz + Einklappen, darunter die Reiter — ein abgesetzter Block --}}
            <div class="cp-head flex-shrink-0 border-b" style="border-color: var(--nx-line-strong)">
            <div class="flex items-center gap-2 px-3 pt-3 pb-1">
                <div class="min-w-0 flex-1">
                    <div class="cp-overline leading-none">Kontext</div>
                    <div class="truncate text-sm font-semibold text-[color:var(--nx-text)] mt-1" title="{{ $subject }}">{{ $subject ?: 'Datensatz' }}</div>
                </div>
                <button type="button" x-on:click="close()" :title="docked ? 'Einklappen' : 'Schließen'"
                    class="inline-flex items-center justify-center w-7 h-7 flex-shrink-0 rounded-md text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)] transition-colors">
                    <span x-show="docked">@svg('heroicon-o-chevron-double-right', 'w-4 h-4')</span>
                    <span x-show="!docked" x-cloak>@svg('heroicon-o-x-mark', 'w-4 h-4')</span>
                </button>
            </div>

            {{-- Reiter: eine Zeile, Icon + Zähler; der aktive zeigt seinen Namen --}}
            <div class="cp-tabs relative flex items-end px-1.5 overflow-x-auto">
                @foreach ($panelApps as $key => $app)
                    @php($badge = $badges[$key] ?? null)
                    <button type="button" x-ref="tab-{{ $key }}" x-on:click="pick('{{ $key }}')"
                        title="{{ $app->label() }}{{ $badge ? ' · ' . $badge : '' }}"
                        x-on:transitionend="moveInk()"
                        :class="{ 'is-active': current === '{{ $key }}' }"
                        @class([
                            'cp-rt flex-shrink-0',
                            'is-active' => $activeApp === $key,
                            'is-empty' => array_key_exists($key, $badges) && ! $badge,
                        ])>
                        @svg('heroicon-o-' . $app->icon(), 'w-4 h-4 flex-shrink-0')
                        <span class="cp-rt-label">{{ $app->label() }}</span>
                        @if ($badge)
                            <span class="cp-badge">{{ $badge }}</span>
                        @endif
                    </button>
                @endforeach
                <span class="cp-ink" :style="{ left: ink.left + 'px', width: ink.width + 'px' }" x-show="ink.width" x-cloak></span>
            </div>
            </div>

            {{-- Inhalt: nur die aktive App wird gerendert --}}
            <div class="flex-1 min-h-0 flex flex-col overflow-y-auto">
                @if ($active)
                    @livewire($active->livewireComponent(), $activeProps, key('cp-' . $active->key() . '-' . md5($contextType) . '-' . $contextId))
                @endif
            </div>

            {{-- Fuß: Algedonic-Signal --}}
            <div class="cp-foot flex-shrink-0 px-2 py-1.5">
                <button type="button" x-on:click="signal()" title="Direkt an die oberste Ebene"
                    class="cp-alg w-full inline-flex items-center gap-2 px-2 py-1.5 rounded-md text-xs font-medium">
                    @svg('heroicon-o-bell-alert', 'w-4 h-4')
                    Algedonic-Signal
                </button>
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
        @else
        {{-- Mobil ohne Kontext: Algedonic-Signal als kleiner Knopf --}}
        <button type="button" x-show="small" x-cloak x-on:click="signal()" title="Algedonic-Signal"
            class="cp-alg-fab fixed w-10 h-10 flex items-center justify-center rounded-full"
            style="bottom: 16px; right: 16px; z-index: 38">
            @svg('heroicon-o-bell-alert', 'w-5 h-5')
        </button>
        @endif
    </aside>
</div>
