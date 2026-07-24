<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Mein Tag" icon="heroicon-o-home" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-72" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-2 p-4">
                <span class="px-1 pb-1 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Aktionen</span>
                <x-nx-button variant="secondary" class="w-full justify-start" x-data @click="$dispatch('open-modal-checkin')">
                    @svg('heroicon-o-check-circle', 'w-4 h-4') Täglicher Check-in
                </x-nx-button>
                <x-nx-button variant="ghost" class="w-full justify-start" x-data @click="$dispatch('open-modal-team')">
                    @svg('heroicon-o-user-group', 'w-4 h-4') Team verwalten
                </x-nx-button>
                <x-nx-button variant="ghost" class="w-full justify-start" x-data @click="$dispatch('open-modal-modules')">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4') Module verwalten
                </x-nx-button>
                <x-nx-button variant="ghost" class="w-full justify-start" x-data @click="$dispatch('open-modal-user')">
                    @svg('heroicon-o-user-circle', 'w-4 h-4') Benutzer-Einstellungen
                </x-nx-button>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container width="contained">
        {{-- Begrüßung --}}
        <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[color:var(--nx-text)]">
                    {{ $greeting }}{{ $firstName ? ', ' . $firstName : '' }}
                </h1>
                <p class="mt-1 text-sm text-[color:var(--nx-muted)]">
                    {{ \Illuminate\Support\Carbon::now()->locale('de')->isoFormat('dddd, D. MMMM YYYY') }}
                </p>
            </div>
            @if($streak > 0)
                <x-nx-badge variant="accent">🔥 {{ $streak }} {{ $streak === 1 ? 'Tag' : 'Tage' }} Streak</x-nx-badge>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Heute: Check-in --}}
            @if(!$todayCheckin)
                <x-nx-callout variant="warning" title="Noch kein Check-in heute" icon="heroicon-o-sun">
                    Starte deinen Tag mit einem kurzen Check-in — Ziel setzen, Stimmung festhalten.
                    <x-slot name="action">
                        <x-nx-button variant="primary" x-data @click="$dispatch('open-modal-checkin')">
                            Jetzt einchecken
                        </x-nx-button>
                    </x-slot>
                </x-nx-callout>
            @else
                @php
                    $moodLabels = \Platform\Core\Models\Checkin::getMoodScoreOptions();
                    $energyLabels = \Platform\Core\Models\Checkin::getEnergyScoreOptions();
                    $goal = trim((string) ($todayCheckin['daily_goal'] ?? ''));
                    $moodScore = $todayCheckin['mood_score'] ?? null;
                    $energyScore = $todayCheckin['energy_score'] ?? null;
                @endphp
                <x-nx-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">
                            @svg('heroicon-o-check-circle', 'w-4 h-4 text-[color:var(--nx-success)]')
                            Heute eingecheckt
                        </div>
                        <button type="button" x-data @click="$dispatch('open-modal-checkin')"
                                class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition-colors">
                            bearbeiten
                        </button>
                    </div>

                    <div class="mt-3">
                        <div class="text-xs text-[color:var(--nx-faint)]">Tagesziel</div>
                        <div class="mt-0.5 text-[color:var(--nx-text)]">
                            {{ $goal !== '' ? $goal : '— kein Ziel gesetzt —' }}
                        </div>
                    </div>

                    @if($moodScore !== null || $energyScore !== null)
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if($moodScore !== null)
                                <x-nx-badge variant="neutral" dot>Stimmung: {{ $moodLabels[$moodScore] ?? $moodScore }}</x-nx-badge>
                            @endif
                            @if($energyScore !== null)
                                <x-nx-badge variant="neutral" dot>Energie: {{ $energyLabels[$energyScore] ?? $energyScore }}</x-nx-badge>
                            @endif
                        </div>
                    @endif
                </x-nx-card>
            @endif

            {{-- Offene Todos --}}
            <x-nx-card flush>
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="text-sm font-semibold text-[color:var(--nx-text)]">Offene Todos</h2>
                    @if(count($openTodos) > 0)
                        <span class="text-xs text-[color:var(--nx-faint)] tabular-nums">{{ count($openTodos) }}</span>
                    @endif
                </div>

                @if(count($openTodos) === 0)
                    <x-nx-empty icon="heroicon-o-check-circle">
                        Keine offenen Todos — alles erledigt.
                    </x-nx-empty>
                @else
                    <ul class="divide-y divide-[color:var(--nx-line)] border-t border-[color:var(--nx-line)]">
                        @foreach($openTodos as $todo)
                            <li class="flex items-center gap-3 px-4 py-2.5">
                                <button type="button" wire:click="toggleTodo({{ $todo['id'] }})"
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-[6px] border border-[color:var(--nx-line-strong)] text-transparent hover:border-[color:var(--nx-accent)] hover:text-[color:var(--nx-faint)] transition-colors"
                                        aria-label="Als erledigt markieren">
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                </button>
                                <span class="min-w-0 flex-1 text-sm text-[color:var(--nx-text)]">{{ $todo['title'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-nx-card>

            {{-- Weiterarbeiten --}}
            @if(count($modules) > 0)
                <div>
                    <h2 class="mb-3 text-sm font-semibold text-[color:var(--nx-text)]">Weiterarbeiten</h2>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($modules as $module)
                            @php
                                $title = $module['title'] ?? $module['label'] ?? ucfirst($module['key']);
                                $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? 'heroicon-o-cube');
                                $routeName = $module['navigation']['route'] ?? null;
                                $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                    ? route($routeName)
                                    : ($module['url'] ?? '#');
                                $isLast = ($module['key'] ?? null) === $lastModuleKey;
                            @endphp
                            <a href="{{ $finalUrl }}"
                               class="group flex items-center gap-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-3 transition-colors hover:bg-[color:var(--nx-hover)]">
                                <x-dynamic-component :component="$icon" class="w-5 h-5 shrink-0 text-[color:var(--nx-muted)] group-hover:text-[color:var(--nx-text)]" />
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $title }}</span>
                                @if($isLast)
                                    <x-nx-badge variant="neutral">zuletzt</x-nx-badge>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Team-Kontext (dezent) --}}
            <div class="grid grid-cols-2 gap-3 border-t border-[color:var(--nx-line)] pt-6">
                <x-nx-stat label="Team-Mitglieder" :value="$memberCount"
                           hint="{{ $currentTeam?->name ?? 'aktuelles Team' }}" icon="heroicon-o-user-group" />
                <x-nx-stat label="Kosten (Monat)" value="€{{ number_format($monthlyTotal, 2, ',', '.') }}"
                           hint="Module & Services" icon="heroicon-o-banknotes" />
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
