{{-- Presenter-Overlay: pollt den Kanal, zeigt neue Kommentare als Sprechblase (Demo/Screencast). --}}
<div wire:poll.1500ms="tick" class="pointer-events-none">
    @if($current)
        <div
            wire:key="presenter-{{ $current['id'] }}"
            x-data="{ show: false }"
            x-init="$nextTick(() => show = true); setTimeout(() => { show = false; setTimeout(() => $wire.dismiss(), 350) }, {{ (int) ($current['duration'] ?? 9) * 1000 }})"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-6"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-6"
            class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] w-[min(680px,calc(100vw-2rem))] pointer-events-auto"
            style="display:none"
        >
            <div class="relative overflow-hidden rounded-2xl bg-[#15242c] text-white shadow-2xl ring-1 ring-white/10">
                <div class="flex items-start gap-3 p-4 pr-11">
                    <div class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#0e7c6b] text-sm font-bold tracking-tight">C</div>
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-[#7fd4c6]">{{ $current['speaker'] ?? 'Claude' }}</div>
                        @if(!empty($current['title']))
                            <div class="mt-0.5 text-sm font-semibold leading-snug">{{ $current['title'] }}</div>
                        @endif
                        <div class="mt-0.5 text-[15px] leading-relaxed text-white/90">{{ $current['message'] }}</div>
                    </div>
                </div>
                <button type="button" @click="show=false; setTimeout(() => $wire.dismiss(), 300)"
                        class="absolute right-3 top-3 grid h-6 w-6 place-items-center rounded-md text-white/50 hover:text-white hover:bg-white/10 transition-colors"
                        aria-label="Schließen">&times;</button>
                <div class="h-1 w-full bg-white/10">
                    <div class="h-full bg-[#0e7c6b]"
                         x-init="$el.animate([{width:'100%'},{width:'0%'}], {duration: {{ (int) ($current['duration'] ?? 9) * 1000 }}, easing:'linear'})"></div>
                </div>
            </div>
        </div>
    @endif
</div>
