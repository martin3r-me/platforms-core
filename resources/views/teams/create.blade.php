<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Neues Team erstellen" icon="heroicon-o-users" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl">
            <div class="p-6 bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60">
                <form method="POST" action="{{ route('platform.teams.store') }}" class="space-y-4">
                    @csrf

                    <x-ui-input-text
                        name="name"
                        label="Team-Name"
                        value="{{ old('name') }}"
                        placeholder="Team-Name eingeben..."
                        required
                        :errorKey="'name'"
                    />

                    @if(!empty($availableParentTeams) && count($availableParentTeams) > 0)
                        <x-ui-input-select
                            name="parent_team_id"
                            label="Parent-Team (optional)"
                            :options="$availableParentTeams"
                            :nullable="true"
                            :errorKey="'parent_team_id'"
                        />
                        <p class="text-xs text-[var(--ui-muted)]">
                            Optional: Wähle ein Root-Team als Parent-Team. Kind-Teams erben Zugriff auf root-scoped Module (z.B. CRM, Organization).
                        </p>
                    @else
                        <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">
                            Keine verfügbaren Parent-Teams (Owner/Admin auf Root-Team erforderlich).
                        </div>
                    @endif

                    <div class="flex gap-2 pt-2">
                        <x-ui-button type="submit" variant="primary">
                            Team erstellen
                        </x-ui-button>
                        <x-ui-button type="button" variant="secondary-outline" x-data @click="window.close()">
                            Schließen
                        </x-ui-button>
                    </div>
                </form>
            </div>

            @if($errors->any())
                <div class="mt-4 text-sm text-red-600">
                    Bitte korrigiere die markierten Felder.
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>


