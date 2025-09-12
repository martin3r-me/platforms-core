<div class="h-full overflow-y-auto p-6">
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Platform Dashboard</h1>
                <p class="text-gray-600">Übersicht</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-8">
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
            <h3 class="text-lg font-semibold text-gray-900">Module & Preise</h3>
            <p class="text-sm text-gray-600 mt-1">Registrierte Module aus der Registry inkl. aktueller Preise</p>
        </div>
        <div class="p-6">
            @if(count($modules) > 0)
                <div class="space-y-3">
                    @foreach($modules as $moduleKey => $module)
                        @php
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName ? route($routeName) : ($module['url'] ?? '#');
                            $pricings = $modulePricings[$moduleKey] ?? [];
                        @endphp
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="d-flex items-center gap-3">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    @if(!empty($module['navigation']['icon']))
                                        @svg($module['navigation']['icon'], 'w-5 h-5')
                                    @else
                                        @svg('heroicon-o-cube', 'w-5 h-5')
                                    @endif
                                </div>
                                <div>
                                    <div class="d-flex items-center gap-2">
                                        <h4 class="font-medium text-gray-900">{{ $module['title'] ?? ucfirst($moduleKey) }}</h4>
                                        @if($routeName)
                                            <a href="{{ $finalUrl }}" class="text-primary text-sm hover:underline" wire:navigate>Öffnen</a>
                                        @endif
                                    </div>
                                    @if(!empty($pricings))
                                        <div class="text-sm text-gray-700 mt-1">
                                            @foreach($pricings as $price)
                                                <div class="d-flex items-center gap-2">
                                                    <x-ui-badge size="sm" variant="neutral">{{ $price['type'] }}</x-ui-badge>
                                                    <span class="text-secondary">{{ $price['label'] }}</span>
                                                    @if($price['price'] !== null)
                                                        <span class="font-medium">{{ number_format((float)$price['price'], 2, ',', '.') }} € / Tag</span>
                                                    @else
                                                        <span class="text-gray-500">kein aktueller Preis</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-500 mt-1">Keine Preisangaben vorhanden</div>
                                    @endif
                                </div>
                            </div>
                            @if(isset($moduleCosts[$moduleKey]))
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Kosten (Monat)</div>
                                    <div class="text-lg font-semibold text-green-600">{{ number_format((float)$moduleCosts[$moduleKey]['cost'], 2, ',', '.') }} €</div>
                                </div>
                            @endif
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