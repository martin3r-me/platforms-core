<div class="h-full overflow-y-auto p-6">
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Platform Dashboard</h1>
                <p class="text-gray-600">Übersicht</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
        <x-ui-dashboard-tile
            title="Verfügbare Module"
            :count="count($modules)"
            subtitle="Tools & Services"
            icon="cube"
            variant="primary"
            size="lg"
        />

        <x-ui-dashboard-tile
            title="Monatliche Kosten"
            :count="$monthlyTotal ?? 0"
            subtitle="Aktueller Monat"
            icon="banknotes"
            variant="info"
            size="lg"
        />

        <x-ui-dashboard-tile
            title="Team-Mitglieder"
            :count="count($teamMembers)"
            subtitle="Aktive Nutzer"
            icon="users"
            variant="success"
            size="lg"
        />
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Module</h3>
            <p class="text-sm text-gray-600 mt-1">Sortiert nach monatlicher Belastung (absteigend). Klickbar, wenn freigegeben.</p>
        </div>
        <div class="p-6">
            @if(count($sortedModules) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($sortedModules as $module)
                        @php
                            $key = $module['key'] ?? null;
                            $allowed = in_array($key, $allowedModuleKeys ?? []);
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName ? route($routeName) : ($module['url'] ?? '#');
                            $cost = $moduleCosts[$key]['cost'] ?? 0.0;
                            $title = $module['title'] ?? ucfirst($key);
                            $icon = $module['navigation']['icon'] ?? 'heroicon-o-cube';
                        @endphp

                        <div class="rounded-lg border p-4 {{ $allowed ? 'bg-gray-50 hover:bg-gray-100 transition' : 'bg-gray-100 opacity-60' }}">
                            <div class="d-flex items-center gap-3 mb-2">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    @svg($icon, 'w-5 h-5')
                                </div>
                                <div class="font-medium text-gray-900">{{ $title }}</div>
                            </div>
                            <div class="text-sm text-gray-600">Gesamt (Monat)</div>
                            <div class="text-lg font-semibold text-green-600">{{ number_format((float)$cost, 2, ',', '.') }} €</div>

                            <div class="mt-3">
                                @if($allowed && $routeName)
                                    <a href="{{ $finalUrl }}" class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-on-primary rounded-md hover:bg-primary-dark transition text-sm" wire:navigate>
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Öffnen</span>
                                    </a>
                                @else
                                    <span class="inline-flex items-center gap-2 px-3 py-2 bg-gray-200 text-gray-600 rounded-md text-sm cursor-not-allowed">
                                        @svg('heroicon-o-lock-closed', 'w-4 h-4')
                                        <span>Nicht verfügbar</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    @svg('heroicon-o-cube', 'w-12 h-12 text-gray-400 mx-auto mb-4')
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Module verfügbar</h4>
                    <p class="text-gray-600">Es sind noch keine Module registriert.</p>
                </div>
            @endif
        </div>
    </div>
</div>