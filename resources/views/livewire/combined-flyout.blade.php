<div x-data="{ combinedFlyoutOpen: false }" 
     @open-team-flyout.window="combinedFlyoutOpen = true"
     @open-module-flyout.window="combinedFlyoutOpen = true"
     @click.away="combinedFlyoutOpen = false"
     class="relative hidden sm:block">
    
    <button @click="combinedFlyoutOpen = !combinedFlyoutOpen" 
        class="inline-flex items-center gap-1 px-2 py-1 h-7 rounded-md border transition text-xs
        text-[var(--ui-primary)] bg-[var(--ui-primary-5)] border-[var(--ui-primary)]/60"
        title="Team & Module wechseln">
        <span class="truncate max-w-[12rem]">{{ $currentTeam?->name ?? 'Team' }}</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>
    
    <div x-show="combinedFlyoutOpen" x-cloak x-transition
        class="absolute top-full right-0 mt-2 w-screen max-w-4xl bg-[var(--ui-surface)] rounded-2xl border border-[var(--ui-border)]/60 shadow-lg z-50">
        <div class="p-6">
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                {{-- Modules Section (Links) --}}
                <div>
                    <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-4">Verfügbare Module</h3>
                    <div class="space-y-3">
                        {{-- Dashboard --}}
                        <a href="{{ route('platform.dashboard') }}"
                            class="group relative flex gap-x-6 rounded-lg p-4 hover:bg-[var(--ui-muted-5)] transition">
                            <div class="mt-1 flex size-11 flex-none items-center justify-center rounded-lg bg-[var(--ui-primary-5)] group-hover:bg-[var(--ui-primary-10)]">
                                @svg('heroicon-o-home', 'w-6 h-6 text-[var(--ui-primary)]')
                            </div>
                            <div>
                                <div class="font-semibold text-[var(--ui-secondary)]">Haupt-Dashboard</div>
                                <p class="mt-1 text-sm text-[var(--ui-muted)]">Übersicht & Start</p>
                            </div>
                        </a>

                        @foreach($modules as $key => $module)
                            @php
                                $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                                $description = $module['description'] ?? 'Ein leistungsstarkes Tool für Ihr Team.';
                                $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                                $routeName = $module['navigation']['route'] ?? null;
                                $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : ($module['url'] ?? '#');
                            @endphp
                            <a href="{{ $finalUrl }}"
                                class="group relative flex gap-x-6 rounded-lg p-4 hover:bg-[var(--ui-muted-5)] transition">
                                <div class="mt-1 flex size-11 flex-none items-center justify-center rounded-lg bg-[var(--ui-primary-5)] group-hover:bg-[var(--ui-primary-10)]">
                                    @if(!empty($icon))
                                        <x-dynamic-component :component="$icon" class="w-6 h-6 text-[var(--ui-primary)]" />
                                    @else
                                        @svg('heroicon-o-cube', 'w-6 h-6 text-[var(--ui-primary)]')
                                    @endif
                                </div>
                                <div>
                                    <div class="font-semibold text-[var(--ui-secondary)]">{{ $title }}</div>
                                    <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $description }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Teams Section (Rechts) --}}
                <div>
                    <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-4">Ihre Teams</h3>
                    <div class="space-y-3">
                        @foreach($userTeams as $team)
                            @php $isActiveTeam = $currentTeam?->id === $team->id; @endphp
                            @if($isActiveTeam)
                                <div class="group relative flex gap-x-6 rounded-lg p-4 bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/60">
                                    <div class="mt-1 flex size-11 flex-none items-center justify-center rounded-lg bg-[var(--ui-primary-10)]">
                                        @svg('heroicon-o-user-group', 'w-6 h-6 text-[var(--ui-primary)]')
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-[var(--ui-primary)]">{{ $team->name }}</div>
                                        <p class="mt-1 text-sm text-[var(--ui-muted)]">
                                            @if($team->users()->count() > 0)
                                                {{ $team->users()->count() }} Mitglieder
                                            @else
                                                Aktuelles Team
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 mt-1">
                                        @svg('heroicon-o-check', 'w-5 h-5 text-[var(--ui-primary)]')
                                    </div>
                                </div>
                            @else
                                <button type="button" 
                                    wire:click="switchTeam({{ $team->id }})"
                                    @click="combinedFlyoutOpen = false"
                                    class="group relative flex gap-x-6 rounded-lg p-4 hover:bg-[var(--ui-muted-5)] transition w-full text-left">
                                    <div class="mt-1 flex size-11 flex-none items-center justify-center rounded-lg bg-[var(--ui-primary-5)] group-hover:bg-[var(--ui-primary-10)]">
                                        @svg('heroicon-o-user-group', 'w-6 h-6 text-[var(--ui-primary)]')
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-[var(--ui-secondary)]">{{ $team->name }}</div>
                                        <p class="mt-1 text-sm text-[var(--ui-muted)]">
                                            @if($team->users()->count() > 0)
                                                {{ $team->users()->count() }} Mitglieder
                                            @else
                                                Team wechseln
                                            @endif
                                        </p>
                                    </div>
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            
            {{-- Footer wie im Beispiel --}}
            <div class="mt-6 bg-[var(--ui-muted-5)] px-6 py-4 rounded-lg">
                <div class="flex items-center gap-x-3">
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Alle Teams & Module</h3>
                    <span class="rounded-full bg-[var(--ui-primary-5)] px-2.5 py-1.5 text-xs font-semibold text-[var(--ui-primary)]">Neu</span>
                </div>
                <p class="mt-2 text-sm text-[var(--ui-muted)]">Verwalten Sie alle Teams und Module an einem Ort.</p>
                <button type="button" @click="$dispatch('open-modal-modules', { tab: 'modules' }); combinedFlyoutOpen = false" 
                    class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition">
                    Alle anzeigen
                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </button>
            </div>
        </div>
    </div>
</div>
