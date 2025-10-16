<div x-data="{ tab: 'modules', query: '' }" x-init="window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });">
<x-ui-modal size="xl" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3 min-w-0">
                @php $logoSquare = file_exists(public_path('logo_square.png')) ? asset('logo_square.png') : null; @endphp
                @if($logoSquare)
                    <img src="{{ $logoSquare }}" alt="Logo" class="w-7 h-7 rounded-md" />
                @endif
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0 truncate">Zentrale Steuerung</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">⌘K / M</span>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2" :class="tab === 'modules' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'" @click="tab = 'modules'">Module</button>
                @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                    <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors border-b-2" :class="tab === 'matrix' ? 'text-[var(--ui-primary)] border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : 'text-[var(--ui-muted)] border-transparent hover:text-[var(--ui-secondary)]'" @click="tab = 'matrix'">Matrix</button>
                @endif
            </div>
        </div>
        <template x-if="tab === 'modules'">
            <div class="mt-3">
                <div class="relative">
                    <input x-model="query" type="text" placeholder="Module suchen..." class="w-full text-sm px-3 py-2 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:border-[var(--ui-primary)]/60" />
                    <div class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                        @svg('heroicon-o-magnifying-glass','w-4 h-4')
                    </div>
                </div>
            </div>
        </template>
    </x-slot>
        
        {{-- Tabs: Inhalte --}}
        {{-- Module --}}
        <div class="mt-6" x-show="tab === 'modules'" x-cloak>
            @php
                $availableModules = $modules ?? [];
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
                    <a href="{{ $finalUrl }}" class="group flex items-center gap-4 p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all duration-200" :class="(query && !('{{ Str::lower($title) }}'.includes(query.toLowerCase()))) ? 'hidden' : ''">
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
                                            $variant = $hasModule ? 'success-outline' : 'danger-outline';
                                        @endphp
                                        <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-center">
                                            <x-ui-button :variant="$variant" size="sm" wire:click="toggleMatrix({{ $user->id }}, {{ $module->id }})">
                                                @if($hasModule)
                                                    @svg('heroicon-o-hand-thumb-up', 'w-4 h-4 text-[var(--ui-success)]')
                                                @else
                                                    @svg('heroicon-o-hand-thumb-down', 'w-4 h-4 text-[var(--ui-danger)]')
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
        <div class="flex justify-between items-center w-full">
            <div class="text-xs text-[var(--ui-muted)]">Tipp: Suche nutzt Titelfilter</div>
            <x-ui-button variant="secondary-outline" @click="modalShow = false">Schließen</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>