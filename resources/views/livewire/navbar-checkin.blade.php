<div x-data="{ open: false }" @click.outside="open = false" class="relative">
    <button type="button"
        @click="open = !open"
        class="relative inline-flex items-center gap-1 h-7 px-1.5 rounded-md transition text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]"
        :class="open ? 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-text)]' : ''"
        title="Täglicher Check-in">
        @svg('heroicon-o-sun', 'w-4 h-4')

        @if($streak > 0)
            <span class="text-[11px] font-semibold leading-none">{{ $streak }}</span>
        @endif

        @if(!$hasCheckin)
            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-[color:var(--nx-warning)] ring-2 ring-[color:var(--nx-surface)]"></span>
        @endif
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="absolute right-0 mt-2 w-80 z-[60] bg-[color:var(--nx-surface)] border border-[color:var(--nx-line)] rounded-[8px] shadow-[var(--nx-shadow-pop)] p-4 space-y-4">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-sun', 'w-4 h-4 text-[color:var(--nx-text)]')
                <span class="text-sm font-semibold text-[color:var(--nx-text)]">Heute</span>
            </div>
            @if($streak > 0)
                <span class="inline-flex items-center gap-1 text-xs font-medium text-[color:var(--nx-text)] bg-[color:var(--nx-accent-soft)] px-2 py-0.5 rounded-full">
                    @svg('heroicon-s-fire', 'w-3 h-3')
                    {{ $streak }} {{ $streak === 1 ? 'Tag' : 'Tage' }}
                </span>
            @endif
        </div>

        {{-- Daily Goal --}}
        @if($dailyGoal)
            <div class="bg-[color:var(--nx-accent-soft)] border border-[color:var(--nx-line)] rounded-md p-3">
                <div class="flex items-center gap-1.5 text-[11px] font-medium text-[color:var(--nx-muted)] uppercase tracking-wide mb-1">
                    @svg('heroicon-o-flag', 'w-3 h-3')
                    Tagesziel
                </div>
                <div class="text-sm text-[color:var(--nx-text)] line-clamp-3">{{ $dailyGoal }}</div>
            </div>
        @else
            <button type="button"
                @click="$dispatch('open-modal-checkin'); open = false"
                class="w-full text-left bg-[color:var(--nx-hover)] hover:bg-[color:var(--nx-accent-soft)] border border-dashed border-[color:var(--nx-line)] hover:border-[color:var(--nx-line-strong)] rounded-md p-3 transition">
                <div class="flex items-center gap-1.5 text-[11px] font-medium text-[color:var(--nx-muted)] uppercase tracking-wide mb-1">
                    @svg('heroicon-o-flag', 'w-3 h-3')
                    Tagesziel
                </div>
                <div class="text-sm text-[color:var(--nx-muted)]">Was ist heute wichtig?</div>
            </button>
        @endif

        {{-- Mood --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-1.5 text-xs font-medium text-[color:var(--nx-text)]">
                    @svg('heroicon-o-face-smile', 'w-3.5 h-3.5 text-[color:var(--nx-text)]')
                    Stimmung
                </div>
                @if($moodScore !== null)
                    <span class="text-[11px] text-[color:var(--nx-muted)]">{{ \Platform\Core\Models\Checkin::getMoodScoreOptions()[$moodScore] ?? '' }}</span>
                @endif
            </div>
            <div class="grid grid-cols-5 gap-1">
                @foreach([0, 1, 2, 3, 4] as $score)
                    <button type="button"
                        wire:click="setMood({{ $score }})"
                        class="py-1.5 rounded text-xs font-medium transition
                            {{ $moodScore === $score
                                ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]'
                                : 'bg-[color:var(--nx-hover)] text-[color:var(--nx-text)] hover:bg-[color:var(--nx-accent-soft)]' }}">
                        {{ $score }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Energy --}}
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-1.5 text-xs font-medium text-[color:var(--nx-text)]">
                    @svg('heroicon-o-bolt', 'w-3.5 h-3.5 text-[color:var(--nx-text)]')
                    Energie
                </div>
                @if($energyScore !== null)
                    <span class="text-[11px] text-[color:var(--nx-muted)]">{{ \Platform\Core\Models\Checkin::getEnergyScoreOptions()[$energyScore] ?? '' }}</span>
                @endif
            </div>
            <div class="grid grid-cols-5 gap-1">
                @foreach([0, 1, 2, 3, 4] as $score)
                    <button type="button"
                        wire:click="setEnergy({{ $score }})"
                        class="py-1.5 rounded text-xs font-medium transition
                            {{ $energyScore === $score
                                ? 'bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)]'
                                : 'bg-[color:var(--nx-hover)] text-[color:var(--nx-text)] hover:bg-[color:var(--nx-accent-soft)]' }}">
                        {{ $score }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Footer link --}}
        <div class="pt-2 border-t border-[color:var(--nx-line)]">
            <button type="button"
                @click="$dispatch('open-modal-checkin'); open = false"
                class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-medium text-[color:var(--nx-text)] hover:underline">
                Vollständiger Check-in
                @svg('heroicon-o-arrow-right', 'w-3 h-3')
            </button>
        </div>
    </div>
</div>
