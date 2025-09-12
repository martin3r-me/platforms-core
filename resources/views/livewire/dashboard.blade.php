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
            :count="0"
            subtitle="Tools & Services"
            icon="cube"
            variant="primary"
            size="lg"
        />

        <x-ui-dashboard-tile
            title="Monatliche Kosten"
            :count="0"
            subtitle="Aktueller Monat"
            icon="banknotes"
            variant="info"
            size="lg"
        />

        <x-ui-dashboard-tile
            title="Team-Mitglieder"
            :count="0"
            subtitle="Aktive Nutzer"
            icon="users"
            variant="success"
            size="lg"
        />
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Verfügbare Module</h3>
            <p class="text-sm text-gray-600 mt-1">Keine Inhalte geladen</p>
        </div>
        <div class="p-6">
            <div class="text-center py-8">
                @svg('heroicon-o-cube', 'w-12 h-12 text-gray-400 mx-auto mb-4')
                <h4 class="text-lg font-medium text-gray-900 mb-2">Noch keine Daten</h4>
                <p class="text-gray-600">Dieses Dashboard zeigt aktuell nur die Oberfläche. Inhalte folgen schrittweise.</p>
            </div>
        </div>
    </div>
</div>