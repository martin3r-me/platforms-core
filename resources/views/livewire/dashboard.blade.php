<div class="h-full overflow-y-auto p-6">
    <!-- Header mit Datum -->
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
<div>
                <h1 class="text-2xl font-bold text-gray-900">Platform Dashboard</h1>
                <p class="text-gray-600">{{ $currentDay }}, {{ $currentDate }}</p>
            </div>
        </div>
    </div>

    <!-- Team-Info Banner -->
    @if($currentTeam)
        <div class="mb-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-building-office', 'w-5 h-5 text-blue-600')
                    <h3 class="text-lg font-semibold text-blue-900">Team-Übersicht</h3>
                </div>
                <p class="text-blue-700 text-sm">
                    Willkommen im {{ $currentTeam->name }} Team. 
                    {{ count($teamMembers) }} Mitglieder, {{ count($modules) }} verfügbare Module.
                </p>
            </div>
        </div>
    @endif

    <!-- Haupt-Statistiken (4er Grid) -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <!-- Verfügbare Module -->
        <x-ui-dashboard-tile
            title="Verfügbare Module"
            :count="count($modules)"
            subtitle="Tools & Services"
            icon="cube"
            variant="primary"
            size="lg"
        />
        
        <!-- Monatliche Kosten -->
        <x-ui-dashboard-tile
            title="Monatliche Kosten"
            :count="$monthlyTotal < 1 ? number_format($monthlyTotal, 2, ',', '.') : number_format($monthlyTotal, 0, ',', '.')"
            subtitle="Aktueller Monat"
            icon="banknotes"
            variant="info"
            size="lg"
        />
        
        <!-- Team-Mitglieder -->
        <x-ui-dashboard-tile
            title="Team-Mitglieder"
            :count="count($teamMembers)"
            subtitle="Aktive Nutzer"
            icon="users"
            variant="success"
            size="lg"
        />
        
        <!-- Aktive Module -->
        <x-ui-dashboard-tile
            title="Aktive Module"
            :count="count($allowedModuleKeys)"
            subtitle="Freigeschaltet"
            icon="check-circle"
            variant="success"
            size="lg"
        />
    </div>

    <!-- Modul-Übersicht -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Verfügbare Module</h3>
            <p class="text-sm text-gray-600 mt-1">Sortiert nach monatlicher Belastung (absteigend)</p>
        </div>
        
        <div class="p-6">
            @if(count($sortedModules) > 0)
                <div class="grid grid-cols-4 gap-4">
                    @foreach($sortedModules as $module)
                        @php
                            $key = $module['key'] ?? null;
                            $allowed = in_array($key, $allowedModuleKeys ?? []);
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName ? route($routeName) : ($module['url'] ?? '#');
                            $isLink = $allowed && $finalUrl && $finalUrl !== '#';
                            $cost = (float)($moduleCosts[$key]['cost'] ?? 0.0);
                            $title = $module['title'] ?? ucfirst($key);
                            $iconRaw = $module['navigation']['icon'] ?? 'cube';
                            $icon = preg_replace('/^(heroicon-[os]-)/', '', $iconRaw);
                            
                            // Farb-Logik wie im Planner-Dashboard
                            $variant = 'neutral';
                            if ($cost === 0.0) { $variant = 'neutral'; }
                            elseif ($cost < 25) { $variant = 'success'; }
                            elseif ($cost < 100) { $variant = 'warning'; }
                            else { $variant = 'danger'; }
                        @endphp

                        @if($isLink)
                            <a href="{{ $finalUrl }}" class="block">
                                <x-ui-dashboard-tile
                                    :title="$title"
                                    :count="$cost < 1 ? number_format($cost, 2, ',', '.') : number_format($cost, 0, ',', '.')"
                                    subtitle="Monat"
                                    :icon="$icon"
                                    :variant="$variant"
                                    size="lg"
                                />
                            </a>
                        @else
                            <div class="opacity-60">
                                <x-ui-dashboard-tile
                                    :title="$title"
                                    :count="$cost < 1 ? number_format($cost, 2, ',', '.') : number_format($cost, 0, ',', '.')"
                                    subtitle="Monat"
                                    :icon="$icon"
                                    :variant="$variant"
                                    size="lg"
                                />
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    @svg('heroicon-o-cube', 'w-12 h-12 text-gray-400 mx-auto mb-4')
                    <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Module verfügbar</h4>
                    <p class="text-gray-600">Es sind noch keine Module registriert oder für dein Team freigeschaltet.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Team-Mitglieder-Übersicht -->
    @if(count($teamMembers) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Team-Mitglieder</h3>
                <p class="text-sm text-gray-600 mt-1">{{ count($teamMembers) }} aktive Mitglieder</p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($teamMembers as $member)
                        <div class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition">
                            <div class="d-flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-full d-flex items-center justify-center">
                                    <span class="text-sm font-medium">
                                        {{ strtoupper(substr($member->name, 0, 2)) }}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 truncate">{{ $member->name }}</h4>
                                    <p class="text-sm text-gray-600 truncate">{{ $member->email }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>