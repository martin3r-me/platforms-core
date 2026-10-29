{{-- Kunden-Tor · Meetings: Anstehendes oben, Vergangenes darunter — aufklappbar mit dem, was herauskam
     (Kurzfassung, Beschlüsse, offene Fragen, Aufgaben). Daten: $upcoming, $past (InboxShareView::meetings). --}}
@php
    $dur = fn (?int $m) => $m === null ? null : ($m >= 60 ? intdiv($m, 60) . ' h' . ($m % 60 ? ' ' . ($m % 60) . ' min' : '') : $m . ' min');
    $meta = fn (array $m) => collect([$m['place'], $dur($m['minutes']), $m['organizer'] ? 'mit ' . $m['organizer'] : null, $m['participants'] ? $m['participants'] . ' Teilnehmende' : null])->filter()->implode(' · ');
@endphp
<div class="gp-wrap py-6">
    @if ($upcoming)
        <h3 class="mb-2 text-sm font-semibold">Anstehend</h3>
        <div class="max-w-5xl overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
            @foreach ($upcoming as $m)
                <div class="flex items-start gap-3 px-4 py-3" wire:key="mt-u-{{ $m['id'] }}">
                    <span class="mt-0.5 shrink-0 text-[color:var(--nx-info)]">@svg('heroicon-o-calendar-days', 'w-4 h-4')</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium leading-snug">{{ $m['title'] }}</div>
                        <div class="mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $meta($m) }}</div>
                    </div>
                    <span class="shrink-0 pt-0.5 text-right text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $m['when'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($past)
        <h3 @class(['mb-2 text-sm font-semibold', 'mt-8' => $upcoming])>Stattgefunden</h3>
        <div class="max-w-5xl overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
            @foreach ($past as $m)
                @php $o = $m['outcome']; @endphp
                <div x-data="{ open: @js($loop->first && $o) }" wire:key="mt-p-{{ $m['id'] }}">
                    <button type="button" @if ($o) @click="open = ! open" @endif
                            @class(['flex w-full items-start gap-3 px-4 py-3 text-left', 'hover:bg-[color:var(--nx-hover)]' => $o, 'cursor-default' => ! $o])>
                        <span class="mt-0.5 shrink-0 text-[color:var(--nx-success)]">@svg('heroicon-s-check-circle', 'w-4 h-4')</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-sm font-medium leading-snug">{{ $m['title'] }}</span>
                                @if ($m['recorded'])
                                    <span class="inline-flex items-center gap-1 text-[11px] text-[color:var(--nx-tone-violet)]">@svg('heroicon-o-microphone', 'w-3 h-3') aufgezeichnet</span>
                                @endif
                            </div>
                            <div class="mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $meta($m) }}</div>
                            @if ($o['tldr'] ?? null)
                                <p class="mt-1 text-[13px] leading-snug text-[color:var(--nx-text)]" x-show="! open">{{ $o['tldr'] }}</p>
                            @endif
                        </div>
                        <span class="flex shrink-0 items-center gap-2 pt-0.5 text-xs tabular-nums text-[color:var(--nx-muted)]">
                            {{ $m['start']->copy()->locale('de')->isoFormat('D. MMM YYYY') }}
                            @if ($o)<span class="transition-transform" :class="open && 'rotate-180'">@svg('heroicon-o-chevron-down', 'w-3.5 h-3.5')</span>@endif
                        </span>
                    </button>
                    @if ($o)
                        <div x-show="open" @if (! $loop->first) style="display: none" @endif class="space-y-4 px-4 pb-4 pl-11">
                            @if ($o['summary'] ?? $o['tldr'] ?? null)
                                <p class="max-w-3xl whitespace-pre-line text-[13px] leading-relaxed text-[color:var(--nx-text)]">{{ $o['summary'] ?? $o['tldr'] }}</p>
                            @endif
                            @foreach ([['decisions', 'Beschlossen', 'heroicon-o-scale', 'var(--nx-info)'], ['action_items', 'Nächste Schritte', 'heroicon-o-arrow-right-circle', 'var(--nx-success)'], ['open_questions', 'Offene Fragen', 'heroicon-o-question-mark-circle', 'var(--nx-warning)']] as [$k, $label, $icon, $color])
                                @if (! empty($o[$k]))
                                    <div>
                                        <div class="mb-1 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide" style="color: {{ $color }}">@svg($icon, 'w-3.5 h-3.5') {{ $label }}</div>
                                        <ul class="max-w-3xl space-y-0.5 text-[13px] leading-snug">
                                            @foreach ($o[$k] as $line)
                                                <li class="flex gap-2"><span class="text-[color:var(--nx-faint)]">–</span><span>{{ $line }}</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
