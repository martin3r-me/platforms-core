<div x-data="{ teamFlyoutOpen: false }" 
     @open-team-flyout.window="teamFlyoutOpen = true"
     @click.away="teamFlyoutOpen = false"
     class="relative hidden sm:block">
    
    <button @click="teamFlyoutOpen = !teamFlyoutOpen" 
        class="inline-flex items-center gap-1 px-2 py-1 h-7 rounded-md border transition text-xs
        {{ $isParentModule ? 'text-[var(--ui-on-warning)] bg-[var(--ui-warning)] border-[var(--ui-warning)]/60' : 'text-[var(--ui-primary)] bg-[var(--ui-primary-5)] border-[var(--ui-primary)]/60' }}"
        title="Team wechseln">
        <span class="truncate max-w-[12rem] flex items-center gap-1">
            @if($baseTeam)
                <span class="flex items-center gap-1">
                    @svg('heroicon-o-user-group', 'w-3 h-3')
                    <span>{{ $baseTeam->name }}</span>
                </span>
                @if($isParentModule && $parentTeam)
                    <span class="text-[0.5rem] opacity-50 leading-none">
                        ({{ $parentTeam->name }})
                    </span>
                @endif
            @else
                <span class="flex items-center gap-1">
                    @svg('heroicon-o-user-group', 'w-3 h-3')
                    <span>{{ $currentTeam?->name ?? 'Team' }}</span>
                </span>
            @endif
        </span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>
    
    <div x-show="teamFlyoutOpen" x-cloak x-transition
        class="absolute top-full right-0 mt-2 w-80 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 shadow-lg z-50 max-h-[80vh] overflow-y-auto">
        <div class="p-2">
            <h3 class="text-[0.625rem] font-semibold text-[var(--ui-muted)] mb-2 px-2">Teams</h3>
            <div class="space-y-1">
                @foreach($groupedTeams as $group)
                    @php 
                        $parentTeam = $group['parent'];
                        $childTeams = $group['children'];
                        $isActiveParentTeam = $baseTeam?->id === $parentTeam->id;
                    @endphp
                    
                    {{-- Parent-Team --}}
                    <button type="button" wire:click="switchTeam({{ $parentTeam->id }})"
                        class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs
                        {{ $isActiveParentTeam ? 'bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/60' : 'hover:bg-[var(--ui-muted-5)]' }}">
                        <div class="flex-shrink-0">
                            @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-primary)]')
                        </div>
                        <div class="min-w-0 flex-1 text-left">
                            <div class="font-medium text-[var(--ui-secondary)] truncate text-xs">{{ $parentTeam->name }}</div>
                        </div>
                        @if($isActiveParentTeam)
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-check', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                            </div>
                        @endif
                    </button>
                    
                    {{-- Kind-Teams (eingerückt) --}}
                    @foreach($childTeams as $childTeam)
                        @php $isActiveChildTeam = $baseTeam?->id === $childTeam->id; @endphp
                        <button type="button" wire:click="switchTeam({{ $childTeam->id }})"
                            class="w-full group flex items-center gap-2 pl-6 pr-2 py-1.5 rounded-md transition text-xs
                            {{ $isActiveChildTeam ? 'bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/60' : 'hover:bg-[var(--ui-muted-5)]' }}">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-user-group', 'w-3.5 h-3.5 text-[var(--ui-primary)] opacity-75')
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="font-medium text-[var(--ui-secondary)] truncate text-xs">{{ $childTeam->name }}</div>
                            </div>
                            @if($isActiveChildTeam)
                                <div class="flex-shrink-0">
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                                </div>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</div>
