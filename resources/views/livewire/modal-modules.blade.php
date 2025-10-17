<div x-data="{ tab: 'modules' }" x-init="window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });">
<x-ui-modal size="xl" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3 min-w-0">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0 truncate">Zentrale Steuerung</h2>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2" :class="tab === 'modules' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'" @click="tab = 'modules'">Module</button>
                @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                    <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2" :class="tab === 'matrix' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'" @click="tab = 'matrix'">Matrix</button>
                @endif
            </div>
        </div>
        
    </x-slot>
        
        {{-- Inhalte: Module + Teams auf einer Seite --}}
        <div class="mt-6" x-show="tab === 'modules'" x-cloak>
            @php
                $availableModules = $modules ?? [];
                $userTeams = auth()->user()?->teams()->get() ?? collect();
            @endphp
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($availableModules as $key => $module)
                        @php
                            $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                            $icon  = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                ? route($routeName)
                                : ($module['url'] ?? '#');
                        @endphp
                    <a href="{{ $finalUrl }}" class="group flex items-center gap-4 p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all duration-200">
                        <div class="flex-shrink-0">
                            @if(!empty($icon))
                                <x-dynamic-component :component="$icon" class="w-8 h-8 text-[var(--ui-primary)] group-hover:scale-110 transition-transform" />
                            @else
                                @svg('heroicon-o-cube', 'w-8 h-8 text-[var(--ui-primary)] group-hover:scale-110 transition-transform')
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-[var(--ui-secondary)] truncate">{{ $title }}</div>
                            <div class="text-xs text-[var(--ui-muted)] truncate">
                                {{ $routeName ? $routeName : ($finalUrl ?? '') }}
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
                    @endforeach
                </div>

                {{-- Teams Liste --}}
                <div class="pt-2 border-t border-[var(--ui-border)]/60">
                    <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-3">Teams</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        @foreach($userTeams as $team)
                            <button type="button"
                                @click="$dispatch('open-modal-team', { preselect: {{ $team->id }} })"
                                class="group text-left flex items-center gap-4 p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all duration-200">
                                <div class="flex-shrink-0">
                                    @svg('heroicon-o-user-group', 'w-8 h-8 text-[var(--ui-primary)] group-hover:scale-110 transition-transform')
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-[var(--ui-secondary)] truncate">{{ $team->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">Team-ID: {{ $team->id }}</div>
                                </div>
                                <div class="flex-shrink-0">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Matrix --}}
        @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
        <div class="mt-6" x-show="tab === 'matrix'" x-cloak>
            @if(!empty($matrixUsers) && !empty($matrixModules))
                <div class="overflow-auto rounded-lg border border-[var(--ui-border)]/60">
                    <table class="min-w-full bg-[var(--ui-surface)]">
                        <thead class="bg-[var(--ui-muted-5)]">
                            <tr>
                                <th class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-left font-semibold text-[var(--ui-secondary)]">User</th>
                                @foreach($matrixModules as $module)
                                    <th class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-center font-semibold text-[var(--ui-secondary)]">{{ $module->title ?? 'Modul' }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrixUsers as $user)
                                <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                    <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 font-medium text-[var(--ui-secondary)]">{{ $user->name }}</td>
                                    @foreach($matrixModules as $module)
                                        @php
                                            $hasModule = in_array($module->id, $userModuleMap[$user->id] ?? []);
                                            $variant = 'secondary-outline';
                                        @endphp
                                        <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-center">
                                            <x-ui-button :variant="$variant" size="sm" wire:click="toggleMatrix({{ $user->id }}, {{ $module->id }})">
                                                @if($hasModule)
                                                    @svg('heroicon-o-check', 'w-4 h-4 text-[var(--ui-secondary)]')
                                                @else
                                                    @svg('heroicon-o-minus', 'w-4 h-4 text-[var(--ui-muted)]')
                                                @endif
                                            </x-ui-button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-sm text-[var(--ui-muted)] p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">Matrix-Daten nicht verfügbar.</div>
            @endif
        </div>
        @endif




    <x-slot name="footer">
        <div class="flex justify-end items-center w-full">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">Schließen</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>