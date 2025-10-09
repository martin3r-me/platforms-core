<div x-data="{ tab: 'modules' }" x-init="
    window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });
">
<x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Zentrale Steuerung</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">⌘K / M</span>
            </div>
        </div>
        <div class="flex gap-1 mt-4 border-b border-gray-200">
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'modules', 'text-gray-500 hover:text-gray-700' : tab !== 'modules' }" @click="tab = 'modules'">Module</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'billing', 'text-gray-500 hover:text-gray-700' : tab !== 'billing' }" @click="tab = 'billing'">Abrechnung</button>
            @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors ml-auto" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'matrix', 'text-gray-500 hover:text-gray-700' : tab !== 'matrix' }" @click="tab = 'matrix'">Matrix</button>
            @endif
        </div>
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
            </div>
        </div>

        {{-- Matrix --}}
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


        {{-- Billing --}}
        <div class="mt-6" x-show="tab === 'billing'" x-cloak>
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Kostenübersicht für diesen Monat</h2>
                @if(!empty($monthlyUsages) && count($monthlyUsages))
                    <div class="overflow-auto rounded-lg border border-[var(--ui-border)]/60">
                        <table class="w-full text-sm bg-[var(--ui-surface)]">
                            <thead class="bg-[var(--ui-muted-5)]">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-[var(--ui-secondary)]">Datum</th>
                                    <th class="px-4 py-3 text-left font-semibold text-[var(--ui-secondary)]">Modul</th>
                                    <th class="px-4 py-3 text-left font-semibold text-[var(--ui-secondary)]">Typ</th>
                                    <th class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">Anzahl</th>
                                    <th class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">Einzelpreis</th>
                                    <th class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">Gesamt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyUsages as $usage)
                                    <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                        <td class="px-4 py-3 text-[var(--ui-secondary)]">{{ \Illuminate\Support\Carbon::parse($usage->usage_date)->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-[var(--ui-secondary)]">{{ $usage->label }}</td>
                                        <td class="px-4 py-3 text-[var(--ui-muted)]">{{ $usage->billable_type }}</td>
                                        <td class="px-4 py-3 text-right text-[var(--ui-secondary)]">{{ $usage->count }}</td>
                                        <td class="px-4 py-3 text-right text-[var(--ui-muted)]">{{ number_format($usage->cost_per_unit, 4, ',', '.') }} €</td>
                                        <td class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">{{ number_format($usage->total_cost, 2, ',', '.') }} €</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end">
                        <div class="px-4 py-2 bg-[var(--ui-primary-5)] rounded-lg border border-[var(--ui-primary)]/20">
                            <span class="font-bold text-[var(--ui-primary)]">Monatssumme: {{ number_format((float)($monthlyTotal ?? 0), 2, ',', '.') }} €</span>
                        </div>
                    </div>
                @else
                    <div class="text-[var(--ui-muted)] text-sm p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">Für diesen Monat liegen noch keine Nutzungsdaten vor.</div>
                @endif
            </div>
        </div>


    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>