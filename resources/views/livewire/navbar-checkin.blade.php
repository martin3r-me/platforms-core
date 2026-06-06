<div x-data="{ open: false }" @click.outside="open = false" class="relative">
    <button type="button"
        @click="open = !open"
        class="relative inline-flex items-center gap-1 h-7 px-1.5 rounded-md transition text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]"
        :class="open ? 'bg-[var(--ui-muted-5)] text-[var(--ui-primary)]' : ''"
        title="Täglicher Check-in">
        @svg('heroicon-o-sun', 'w-4 h-4')

        @if($streak > 0)
            <span class="text-[11px] font-semibold leading-none">{{ $streak }}</span>
        @endif

        @if(!$hasCheckin)
            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-[var(--ui-warning)] ring-2 ring-[var(--ui-surface)]"></span>
        @endif
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="absolute right-0 mt-2 w-80 z-[60] bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-lg shadow-xl p-4 space-y-4">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-sun', 'w-4 h-4 text-[var(--ui-primary)]')
                <span class="text-sm font-semibold text-[var(--ui-secondary)]">Heute</span>
            </div>
            @if($streak > 0)
                <span class="inline-flex items-center gap-1 text-xs font-medium text-[var(--ui-primary)] bg-[var(--ui-primary)]/10 px-2 py-0.5 rounded-full">
                    @svg('heroicon-s-fire', 'w-3 h-3')
                    {{ $streak }} {{ $streak === 1 ? 'Tag' : 'Tage' }}
                </span>
            @endif
        </div>

        {{-- Daily Goal --}}
        @if($dailyGoal)
            <div class="bg-[var(--ui-primary)]/5 border border-[var(--ui-primary)]/20 rounded-md p-3">
                <div class="flex items-center gap-1.5 text-[11px] font-medium text-[var(--ui-primary)] uppercase tracking-wide mb-1">
                    @svg('heroicon-o-flag', 'w-3 h-3')
                    Tagesziel
                </div>
                <div class="text-sm text-[var(--ui-secondary)] line-clamp-3">{{ $dailyGoal }}</div>
            </div>
        @else
            <button type="button"
                @click="$dispatch('open-modal-checkin'); open = false"
                class="w-full text-left bg-[var(--ui-muted-5)] hover:bg-[var(--ui-primary)]/5 border border-dashed border-[var(--ui-border)] hover:border-[var(--ui-primary)]/30 rounded-md p-3 transition">
                <div class="flex items-center gap-1.5 text-[11px] font-medium text-[var(--ui-muted)] uppercase tracking-wide mb-1">
                    @svg('heroicon-o-flag', 'w-3 h-3')
                    Tagesziel
                </div>
                <div class="text-sm text-[var(--ui-muted)]">Was ist heute wichtig?</div>
            </button>
        @endif

        {{-- Mood --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-1.5 text-xs font-medium text-[var(--ui-secondary)]">
                    @svg('heroicon-o-face-smile', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                    Stimmung
                </div>
                @if($moodScore !== null)
                    <span class="text-[11px] text-[var(--ui-muted)]">{{ \Platform\Core\Models\Checkin::getMoodScoreOptions()[$moodScore] ?? '' }}</span>
                @endif
            </div>
            <div class="grid grid-cols-5 gap-1">
                @foreach([0, 1, 2, 3, 4] as $score)
                    <button type="button"
                        wire:click="setMood({{ $score }})"
                        class="py-1.5 rounded text-xs font-medium transition
                            {{ $moodScore === $score
                                ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] shadow-sm'
                                : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-primary)]/10 hover:text-[var(--ui-primary)]' }}">
                        {{ $score }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Energy --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-1.5 text-xs font-medium text-[var(--ui-secondary)]">
                    @svg('heroicon-o-bolt', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                    Energie
                </div>
                @if($energyScore !== null)
                    <span class="text-[11px] text-[var(--ui-muted)]">{{ \Platform\Core\Models\Checkin::getEnergyScoreOptions()[$energyScore] ?? '' }}</span>
                @endif
            </div>
            <div class="grid grid-cols-5 gap-1">
                @foreach([0, 1, 2, 3, 4] as $score)
                    <button type="button"
                        wire:click="setEnergy({{ $score }})"
                        class="py-1.5 rounded text-xs font-medium transition
                            {{ $energyScore === $score
                                ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] shadow-sm'
                                : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-primary)]/10 hover:text-[var(--ui-primary)]' }}">
                        {{ $score }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Footer link --}}
        <div class="pt-2 border-t border-[var(--ui-border)]/40">
            <button type="button"
                @click="$dispatch('open-modal-checkin'); open = false"
                class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-medium text-[var(--ui-primary)] hover:underline">
                Vollständiger Check-in
                @svg('heroicon-o-arrow-right', 'w-3 h-3')
            </button>
        </div>
    </div>
</div>
