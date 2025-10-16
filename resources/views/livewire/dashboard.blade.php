<div class="h-full overflow-y-auto p-6">
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Platform Dashboard</h1>
                <p class="text-gray-600">Übersicht</p>
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
                <div class="mt-3">
                    <x-ui-button variant="primary" x-data @click="$dispatch('open-modal-team', { tab: 'team' })">
                        Team verwalten / Mitglieder einladen
                    </x-ui-button>
                </div>
            </div>
        </div>
    @endif

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
            :count="$monthlyTotal"
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
</div>