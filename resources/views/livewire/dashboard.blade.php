<div class="h-full overflow-y-auto p-6">
    <!-- Header mit Datum -->
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Platform Dashboard</h1>
                <p class="text-gray-600">{{ $currentDay }}, {{ $currentDate }}</p>
            </div>
            <div class="d-flex items-center gap-4">
                @if($currentTeam)
                    <x-ui-badge variant="secondary" size="md">
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-4 h-4')
                            <span>{{ $currentTeam->name }}</span>
                        </div>
                    </x-ui-badge>
                @endif
            </div>
        </div>
    </div>

    <!-- Team-Info Banner -->
    @if($currentTeam)
        <div class="mb-6">
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

    <!-- Hauptstatistiken (3er Grid) -->
    <div class="grid grid-cols-3 gap-4 mb-8">
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
            :count="number_format($monthlyTotal, 2, ',', '.') . ' €'"
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
    </div>

    <!-- Kosten-Übersicht -->
    @if(count($moduleCosts) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="d-flex items-center gap-2">
                    @svg('heroicon-o-banknotes', 'w-5 h-5 text-green-600')
                    <h3 class="text-lg font-semibold text-gray-900">Kosten pro Modul</h3>
                    <x-ui-badge variant="info" size="sm">{{ count($moduleCosts) }} Module</x-ui-badge>
                </div>
                <p class="text-sm text-gray-600 mt-1">Aufschlüsselung der monatlichen Kosten nach Modulen</p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    @foreach($moduleCosts as $moduleKey => $module)
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="d-flex items-center gap-3">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    @svg($module['icon'], 'w-5 h-5')
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $module['title'] }}</h4>
                                    <p class="text-sm text-gray-600">{{ $moduleKey }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-semibold text-green-600">
                                    {{ number_format($module['cost'], 2, ',', '.') }} €
                                </div>
                                <div class="text-xs text-gray-500">diesen Monat</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Modul-Übersicht -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Verfügbare Module</h3>
            <p class="text-sm text-gray-600 mt-1">Alle registrierten Tools und Services</p>
        </div>
        
        <div class="p-6">
            @if(count($modules) > 0)
                <div class="grid grid-cols-3 gap-4">
                    @foreach($modules as $moduleKey => $module)
                        @php
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName ? route($routeName) : ($module['url'] ?? '#');
                            $hasCosts = isset($moduleCosts[$moduleKey]);
                            $hasUsage = isset($usageStats[$moduleKey]);
                        @endphp
                        
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                            <div class="d-flex items-center gap-3">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    @if(!empty($module['navigation']['icon']))
                                        @svg($module['navigation']['icon'], 'w-5 h-5')
                                    @else
                                        @svg('heroicon-o-cube', 'w-5 h-5')
                                    @endif
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">
                                        {{ $module['title'] ?? $module['label'] ?? ucfirst($moduleKey) }}
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        @if($hasCosts)
                                            {{ number_format($moduleCosts[$moduleKey]['cost'], 2, ',', '.') }} €
                                        @elseif($hasUsage)
                                            {{ $usageStats[$moduleKey]['usage'] }} Nutzungen
                                        @else
                                            Verfügbar
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @if($routeName)
                                <a href="{{ $finalUrl }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-on-primary rounded-md hover:bg-primary-dark transition text-sm"
                                   wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Öffnen</span>
                                    </div>
                                </a>
                            @else
                                <x-ui-badge variant="neutral" size="sm">Nicht verfügbar</x-ui-badge>
                            @endif
                        </div>
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

    <!-- Team-Mitglieder -->
    @if(count($teamMembers) > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Team-Mitglieder</h3>
                <p class="text-sm text-gray-600 mt-1">{{ count($teamMembers) }} aktive Mitglieder</p>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    @foreach($teamMembers as $member)
                        <div class="d-flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-primary text-on-primary rounded-full d-flex items-center justify-center">
                                <span class="text-sm font-medium">
                                    {{ strtoupper(substr($member->name, 0, 2)) }}
                                </span>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900">{{ $member->name }}</h4>
                                <p class="text-sm text-gray-600">{{ $member->email }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>