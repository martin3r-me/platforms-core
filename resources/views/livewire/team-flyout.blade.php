<div x-data="{ teamFlyoutOpen: false }" 
     @open-team-flyout.window="teamFlyoutOpen = true"
     @click.away="teamFlyoutOpen = false"
     class="relative hidden sm:block">
    
    <button @click="teamFlyoutOpen = !teamFlyoutOpen" 
        class="inline-flex items-center gap-1 px-2 py-1 h-7 rounded-md border transition text-xs
        text-[var(--ui-primary)] bg-[var(--ui-primary-5)] border-[var(--ui-primary)]/60"
        title="Team wechseln">
        <span class="truncate max-w-[12rem]">{{ $currentTeam?->name ?? 'Team' }} • {{ $currentModule }}</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>
    
    <div x-show="teamFlyoutOpen" x-cloak x-transition
        class="absolute top-full right-0 mt-2 w-80 bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 shadow-lg z-50">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-3">Teams</h3>
            <div class="space-y-2">
                @foreach($userTeams as $team)
                    @php $isActiveTeam = $currentTeam?->id === $team->id; @endphp
                    <button type="button" wire:click="switchTeam({{ $team->id }})"
                        class="w-full group flex items-center gap-3 p-3 rounded-lg transition
                        {{ $isActiveTeam ? 'bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/60' : 'hover:bg-[var(--ui-muted-5)]' }}">
                        <div class="flex-shrink-0">
                            @svg('heroicon-o-user-group', 'w-5 h-5 text-[var(--ui-primary)]')
                        </div>
                        <div class="min-w-0 flex-1 text-left">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $team->name }}</div>
                            @if($team->users()->count() > 0)
                                <div class="text-xs text-[var(--ui-muted)]">{{ $team->users()->count() }} Mitglieder</div>
                            @endif
                        </div>
                        @if($isActiveTeam)
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-check', 'w-4 h-4 text-[var(--ui-primary)]')
                            </div>
                        @endif
                    </button>
                @endforeach
            </div>
            <div class="mt-3 pt-3 border-t border-[var(--ui-border)]/60">
                <button type="button" wire:click="openModal" 
                    class="w-full flex items-center justify-center gap-2 p-2 text-sm font-medium text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition">
                    Alle Teams anzeigen
                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </button>
            </div>
        </div>
    </div>
</div>
