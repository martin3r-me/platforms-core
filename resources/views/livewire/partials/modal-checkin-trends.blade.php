@php
    $td = $trendData ?? [];
    $days = $td['days'] ?? [];
    $habits = $td['habits'] ?? [];
    $habitLabels = [
        'hydrated' => ['Hydriert', 'heroicon-o-beaker'],
        'exercised' => ['Bewegt', 'heroicon-o-fire'],
        'slept_well' => ['Geschlafen', 'heroicon-o-moon'],
        'focused_work' => ['Fokus', 'heroicon-o-eye'],
        'social_time' => ['Sozial', 'heroicon-o-users'],
    ];
    $window = $td['window_days'] ?? 30;
    $totalCheckins = $td['total_checkins'] ?? 0;
@endphp

<div class="space-y-6 h-full overflow-y-auto pr-1">

    {{-- KPI-Row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-medium text-[var(--ui-muted)] uppercase tracking-wide mb-1">
                @svg('heroicon-s-fire', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                Streak
            </div>
            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                {{ $td['current_streak'] ?? 0 }}
                <span class="text-sm font-medium text-[var(--ui-muted)]">{{ ($td['current_streak'] ?? 0) === 1 ? 'Tag' : 'Tage' }}</span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-medium text-[var(--ui-muted)] uppercase tracking-wide mb-1">
                @svg('heroicon-o-calendar-days', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                Check-ins
            </div>
            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                {{ $totalCheckins }}<span class="text-sm font-medium text-[var(--ui-muted)]">/{{ $window }}</span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-medium text-[var(--ui-muted)] uppercase tracking-wide mb-1">
                @svg('heroicon-o-face-smile', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                Ø Stimmung
            </div>
            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                {{ $td['avg_mood'] ?? '–' }}<span class="text-sm font-medium text-[var(--ui-muted)]">/4</span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-4">
            <div class="flex items-center gap-2 text-[11px] font-medium text-[var(--ui-muted)] uppercase tracking-wide mb-1">
                @svg('heroicon-o-bolt', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                Ø Energie
            </div>
            <div class="text-2xl font-bold text-[var(--ui-secondary)]">
                {{ $td['avg_energy'] ?? '–' }}<span class="text-sm font-medium text-[var(--ui-muted)]">/4</span>
            </div>
        </div>
    </div>

    @if($totalCheckins === 0)
        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-12 text-center">
            <div class="w-14 h-14 mx-auto mb-3 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                @svg('heroicon-o-chart-bar', 'w-7 h-7 text-[var(--ui-muted)]')
            </div>
            <p class="text-sm text-[var(--ui-secondary)] font-medium">Noch keine Daten</p>
            <p class="text-xs text-[var(--ui-muted)] mt-1">Sobald du Check-ins machst, siehst du hier deine Trends.</p>
        </div>
    @else
        {{-- Mood chart --}}
        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-4">
                @svg('heroicon-o-face-smile', 'w-5 h-5 text-[var(--ui-primary)]')
                <h3 class="text-base font-semibold text-[var(--ui-secondary)]">Stimmung (30 Tage)</h3>
            </div>
            <div class="flex items-end gap-px h-24">
                @foreach($days as $day)
                    @php
                        $val = $day['mood'];
                        $heightPct = $val !== null ? max(8, (($val + 1) / 5) * 100) : 0;
                        $hasData = $val !== null;
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                        @if($hasData)
                            <div class="w-full rounded-t transition-all duration-200
                                {{ $day['is_today'] ? 'bg-[var(--ui-primary)]' : 'bg-[var(--ui-primary)]/60 group-hover:bg-[var(--ui-primary)]' }}"
                                style="height: {{ $heightPct }}%"></div>
                        @else
                            <div class="w-full h-1 rounded-t bg-[var(--ui-border)] {{ $day['is_weekend'] ? 'opacity-40' : '' }}"></div>
                        @endif
                        <div class="absolute -top-8 left-1/2 -translate-x-1/2 px-1.5 py-0.5 bg-[var(--ui-secondary)] text-[var(--ui-on-secondary)] text-[10px] rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-10">
                            {{ $day['label'] }} {{ $hasData ? '· '.$val : '· –' }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-[10px] text-[var(--ui-muted)]">
                <span>{{ $days[0]['label'] ?? '' }}</span>
                <span>Heute</span>
            </div>
        </div>

        {{-- Energy chart --}}
        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-4">
                @svg('heroicon-o-bolt', 'w-5 h-5 text-[var(--ui-primary)]')
                <h3 class="text-base font-semibold text-[var(--ui-secondary)]">Energie (30 Tage)</h3>
            </div>
            <div class="flex items-end gap-px h-24">
                @foreach($days as $day)
                    @php
                        $val = $day['energy'];
                        $heightPct = $val !== null ? max(8, (($val + 1) / 5) * 100) : 0;
                        $hasData = $val !== null;
                    @endphp
                    <div class="flex-1 flex flex-col items-center justify-end h-full group relative">
                        @if($hasData)
                            <div class="w-full rounded-t transition-all duration-200
                                {{ $day['is_today'] ? 'bg-[var(--ui-warning)]' : 'bg-[var(--ui-warning)]/60 group-hover:bg-[var(--ui-warning)]' }}"
                                style="height: {{ $heightPct }}%"></div>
                        @else
                            <div class="w-full h-1 rounded-t bg-[var(--ui-border)] {{ $day['is_weekend'] ? 'opacity-40' : '' }}"></div>
                        @endif
                        <div class="absolute -top-8 left-1/2 -translate-x-1/2 px-1.5 py-0.5 bg-[var(--ui-secondary)] text-[var(--ui-on-secondary)] text-[10px] rounded opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-10">
                            {{ $day['label'] }} {{ $hasData ? '· '.$val : '· –' }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-[10px] text-[var(--ui-muted)]">
                <span>{{ $days[0]['label'] ?? '' }}</span>
                <span>Heute</span>
            </div>
        </div>

        {{-- Habits --}}
        <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-4">
                @svg('heroicon-o-sparkles', 'w-5 h-5 text-[var(--ui-primary)]')
                <h3 class="text-base font-semibold text-[var(--ui-secondary)]">Gewohnheiten (30 Tage)</h3>
            </div>
            <div class="space-y-3">
                @foreach($habitLabels as $key => [$label, $icon])
                    @php
                        $count = $habits[$key] ?? 0;
                        $pct = $window > 0 ? round(($count / $window) * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2 text-sm text-[var(--ui-secondary)]">
                                <x-dynamic-component :component="$icon" class="w-4 h-4 text-[var(--ui-primary)]" />
                                {{ $label }}
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">
                                <span class="font-semibold text-[var(--ui-secondary)]">{{ $count }}</span>/{{ $window }} Tage
                                <span class="ml-1 text-[10px]">({{ $pct }}%)</span>
                            </div>
                        </div>
                        <div class="h-2 bg-[var(--ui-muted-5)] rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-[var(--ui-primary)]/70 to-[var(--ui-primary)] rounded-full transition-all duration-500"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
