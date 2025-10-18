<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Platform Dashboard" />
    </x-slot>

    <div class="p-6 space-y-6">
        <!-- Team-Info Banner -->
        @if($currentTeam)
            <div class="bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/60 rounded-lg p-4">
                <div class="flex items-center gap-2 mb-2">
                    @svg('heroicon-o-building-office', 'w-5 h-5 text-[var(--ui-primary)]')
                    <h3 class="text-lg font-semibold text-[var(--ui-primary)]">Team-Übersicht</h3>
                </div>
                <p class="text-[var(--ui-secondary)] text-sm">
                    Willkommen im {{ $currentTeam->name }} Team. 
                    {{ count($teamMembers) }} Mitglieder, {{ count($modules) }} verfügbare Module.
                </p>
                <div class="mt-3">
                    <x-ui-button variant="primary" x-data @click="$dispatch('open-modal-team', { tab: 'team' })">
                        Team verwalten / Mitglieder einladen
                    </x-ui-button>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
</x-ui-page>