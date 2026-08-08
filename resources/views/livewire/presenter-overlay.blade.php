{{-- Presenter-Overlay: pollt den Kanal, zeigt neue Kommentare als Sprechblase.
     Kein Auto-Dismiss (kein Alpine/poll-Race) — die Blase bleibt stehen, bis sie
     per Klick bestaetigt wird oder eine neue Nachricht sie ersetzt. --}}
<div wire:poll.1500ms="tick" class="pointer-events-none">
    @if($current)
        <div wire:key="presenter-{{ $current['id'] }}"
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] w-[min(680px,calc(100vw-2rem))] pointer-events-auto">
            <div class="relative overflow-hidden rounded-2xl bg-[#15242c] text-white shadow-2xl ring-1 ring-white/10">
                <div class="flex items-start gap-3 p-4">
                    <div class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#0e7c6b] text-sm font-bold tracking-tight">C</div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#7fd4c6]">{{ $current['speaker'] ?? 'Claude' }}</div>
                        @if(!empty($current['title']))
                            <div class="mt-0.5 text-sm font-semibold leading-snug">{{ $current['title'] }}</div>
                        @endif
                        <div class="mt-1 text-[15px] leading-relaxed text-white/90">{{ $current['message'] }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-white/10 px-4 py-2.5">
                    <button type="button" wire:click="dismiss"
                            class="inline-flex items-center gap-1.5 rounded-md bg-[#0e7c6b] px-3.5 py-1.5 text-sm font-medium text-white hover:bg-[#0b5c50] transition-colors">
                        Verstanden
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
