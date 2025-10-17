<div x-data="{ tab: 'modules', currentPath: window.location.pathname.replace(/^\//,'') }" x-init="window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; currentPath = window.location.pathname.replace(/^\//,'') });">
<x-ui-modal size="xl" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3 min-w-0">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0 truncate">Zentrale Steuerung</h2>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2" :class="tab === 'modules' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'" @click="tab = 'modules'">Navigation</button>
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
                {{-- Teams zuerst --}}
                <div class="pt-0">
                    <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-2">Teams</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach($userTeams as $team)
                            @php $isActiveTeam = auth()->user()?->currentTeam?->id === $team->id; @endphp
                            <button type="button"
                                wire:click="switchTeam({{ $team->id }})"
                                class="group text-left flex items-start gap-3 px-3 py-2 rounded-lg border bg-[var(--ui-surface)] transition-all duration-200 {{ $isActiveTeam ? 'border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)]' }}">
                                <div class="flex-shrink-0 mt-0.5">
                                    @svg('heroicon-o-user-group', 'w-6 h-6 ' . ($isActiveTeam ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-primary)]') . ' group-hover:scale-110 transition-transform')
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold leading-snug break-words {{ $isActiveTeam ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)]' }}">{{ $team->name }}</div>
                                    @php $memberCount = $team->users()->count(); @endphp
                                    @if($memberCount > 0 && $memberCount <= 10)
                                        <div class="mt-1 text-[9px] text-[var(--ui-muted)] leading-tight">
                                            @foreach($team->users()->limit(10)->get() as $member)
                                                <span>{{ $member->name }}</span>@if(!$loop->last), @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 mt-1">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Module danach --}}
                <div class="pt-2 border-t border-[var(--ui-border)]/60">
                    <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-2">Module</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    {{-- Main Dashboard Card --}}
                    @php $currentRouteName = optional(request()->route())->getName(); @endphp
                    <a href="{{ route('platform.dashboard') }}"
                       x-data="{ prefix: 'dashboard' }"
                       :class="(currentPath === prefix || currentPath.startsWith(prefix + '/')) ? 'border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)]'"
                       class="group flex items-start gap-3 px-3 py-2 rounded-lg border bg-[var(--ui-surface)] transition-all duration-200">
                        <div class="flex-shrink-0 mt-0.5">
                            @svg('heroicon-o-home', 'w-6 h-6 ' . ($isPlatformActive ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-primary)]') . ' group-hover:scale-110 transition-transform')
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold leading-snug text-[var(--ui-secondary)]">Haupt-Dashboard</div>
                            <div class="text-[10px] text-[var(--ui-muted)]">currentPath: <span x-text="currentPath"></span>, match: <span x-text="(currentPath === 'dashboard' || currentPath.startsWith('dashboard/')) ? 'yes' : 'no'"></span></div>
                        </div>
                        <div class="flex-shrink-0 mt-1">
                            @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>

                    @foreach($availableModules as $key => $module)
                        @php
                            $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                            $icon  = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                ? route($routeName)
                                : ($module['url'] ?? '#');
                            $prefix = strtolower($module['routing']['prefix'] ?? ($module['key'] ?? $key));
                            $isActiveModule = request()->is($prefix) || request()->is($prefix . '/*');
                        @endphp
                    <a href="{{ $finalUrl }}"
                       x-data="{ prefix: '{{ $prefix }}' }"
                       :class="(currentPath === prefix || currentPath.startsWith(prefix + '/')) ? 'border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)]'"
                       class="group flex items-center gap-3 px-3 py-2 rounded-lg border bg-[var(--ui-surface)] transition-all duration-200">
                        <div class="flex-shrink-0">
                            @if(!empty($icon))
                                <x-dynamic-component :component="$icon" class="w-6 h-6 text-[var(--ui-primary)] group-hover:scale-110 transition-transform" />
                            @else
                                @svg('heroicon-o-cube', 'w-6 h-6 text-[var(--ui-primary)] group-hover:scale-110 transition-transform')
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold leading-snug text-[var(--ui-secondary)]">{{ $title }}</div>
                            <div class="text-[10px] text-[var(--ui-muted)]">
                                key: {{ $module['key'] ?? $key }}, prefix: {{ $prefix }}, routeName: {{ $routeName ?? '-' }}, currentPath: <span x-text="currentPath"></span>, active: <span x-text="(currentPath === prefix || currentPath.startsWith(prefix + '/')) ? 'yes' : 'no'"></span>
                            </div>
                        </div>
                        <div class="flex-shrink-0 mt-1">
                            @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
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