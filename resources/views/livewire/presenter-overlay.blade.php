{{-- Presenter/Regie-Player: zeigt den aktuellen Schritt (Tour oder Ad-hoc) als Sprechblase,
     hebt optional ein Ziel-Element hervor (Spotlight). Rein klick-/load-getrieben, kein Poll. --}}
<div class="pointer-events-none">
    <style>
        .presenter-highlight {
            position: relative;
            z-index: 55 !important;
            border-radius: 8px;
            outline: 3px solid #0e7c6b;
            outline-offset: 3px;
            box-shadow: 0 0 0 5px rgba(14, 124, 107, .30);
            animation: presenter-pulse 1.5s ease-in-out infinite;
        }
        @keyframes presenter-pulse {
            0%, 100% { box-shadow: 0 0 0 4px rgba(14, 124, 107, .38); }
            50%      { box-shadow: 0 0 0 11px rgba(14, 124, 107, .10); }
        }
    </style>
    <script>
        (function () {
            if (window.__presenterHighlightInit) return;
            window.__presenterHighlightInit = true;
            var cur = null;
            function clear() { if (cur) { cur.classList.remove('presenter-highlight'); cur = null; } }
            function find(sel) {
                if (!sel) return null;
                if (sel.indexOf('text:') === 0) {
                    var t = sel.slice(5).trim().toLowerCase();
                    var els = document.querySelectorAll('button, a, [role="button"], label, summary, h1, h2, h3');
                    for (var i = 0; i < els.length; i++) {
                        var txt = (els[i].innerText || els[i].value || '').trim().toLowerCase();
                        if (txt && txt.indexOf(t) !== -1) return els[i];
                    }
                    return null;
                }
                try { return document.querySelector(sel); } catch (e) { return null; }
            }
            window.addEventListener('presenter-highlight', function (e) {
                clear();
                var d = e.detail || {};
                var sel = (d.selector != null) ? d.selector : (Array.isArray(d) && d[0] ? d[0].selector : null);
                if (!sel) return;
                setTimeout(function () {
                    var el = find(sel);
                    if (!el) return;
                    el.classList.add('presenter-highlight');
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    cur = el;
                }, 180);
            });
        })();
    </script>

    @if($current)
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] w-[min(680px,calc(100vw-2rem))] pointer-events-auto">
            <div class="relative overflow-hidden rounded-2xl bg-[#15242c] text-white shadow-2xl ring-1 ring-white/10">
                <div class="flex items-start gap-3 p-4">
                    <div class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#0e7c6b] text-sm font-bold tracking-tight">C</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#7fd4c6]">{{ $current['speaker'] ?? 'Claude' }}</span>
                            @if(($current['mode'] ?? '') === 'tour')
                                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-medium tabular-nums text-white/70">Schritt {{ $current['progress'] }}</span>
                            @endif
                        </div>
                        @if(!empty($current['title']))
                            <div class="mt-1 text-sm font-semibold leading-snug">{{ $current['title'] }}</div>
                        @endif
                        <div class="mt-1 text-[15px] leading-relaxed text-white/90">{{ $current['message'] }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 border-t border-white/10 px-4 py-2.5">
                    <div>
                        @if(($current['mode'] ?? '') === 'tour')
                            <button type="button" wire:click="stopTour"
                                    class="text-xs text-white/45 hover:text-white/80 transition-colors">Tour beenden</button>
                        @endif
                    </div>
                    <button type="button" wire:click="next"
                            class="inline-flex items-center gap-1.5 rounded-md bg-[#0e7c6b] px-3.5 py-1.5 text-sm font-medium text-white hover:bg-[#0b5c50] transition-colors">
                        @if(($current['mode'] ?? '') === 'tour')
                            {{ ($current['is_last'] ?? false) ? 'Fertig' : 'Weiter' }}
                            @if(!($current['is_last'] ?? false))
                                @svg('heroicon-o-arrow-right', 'w-4 h-4')
                            @endif
                        @else
                            Verstanden
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
