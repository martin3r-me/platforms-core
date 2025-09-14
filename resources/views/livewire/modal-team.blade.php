<x-ui-modal wire:model="modalShow">
    <x-slot name="header">
        Team-Verwaltung
    </x-slot>

    <div x-data="{ tab: 'settings' }">
        {{-- Tab Navigation --}}
        <div class="d-flex border-b mb-4">
            <button
                class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                :class="{'font-bold border-b-2 border-primary': tab === 'settings'}"
                @click="tab = 'settings'"
                type="button"
            >
                Einstellungen
            </button>
            <button
                class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                :class="{'font-bold border-b-2 border-primary': tab === 'members'}"
                @click="tab = 'members'"
                type="button"
            >
                Mitglieder
            </button>
            <button
                class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                :class="{'font-bold border-b-2 border-primary': tab === 'invites'}"
                @click="tab = 'invites'"
                type="button"
            >
                Einladungen
            </button>
            <button
                class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                :class="{'font-bold border-b-2 border-primary': tab === 'create'}"
                @click="tab = 'create'"
                type="button"
            >
                Neues Team erstellen
            </button>
        </div>

        {{-- Einstellungen --}}
        <div x-show="tab === 'settings'" class="space-y-6" x-cloak>
            {{-- Team wechseln --}}
            @if($allTeams && count($allTeams) > 1)
                <div class="mb-4">
                    <x-ui-input-select
                        name="user.current_team_id"
                        variant="danger"
                        label="Team wechseln"
                        :options="$allTeams"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="false"
                        wire:model.live="user.current_team_id"
                    />
                </div>
            @endif

            @if($team && $team->personal_team)
                <x-ui-badge variant="primary" size="xs">
                    Persönliches Team
                </x-ui-badge>
            @endif

            @can('update', $team)
                <x-ui-input-text
                    name="team.name"
                    label="Team-Name"
                    wire:model.live.debounce.500ms="team.name"
                    placeholder="Team-Name eingeben..."
                    required
                    :errorKey="'team.name'"
                />
            @else
                <div>
                    <strong>Team:</strong> {{ $team->name }}
                </div>
            @endcan

            <hr>

            <div>
                <strong>Mitglieder:</strong>
                <ul>
                    @foreach($team->users as $user)
                        <li>
                            {{ $user->fullname ?? $user->name }} 
                            <span class="text-xs text-gray-500">({{ $user->pivot->role }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Mitglieder --}}
        <div x-show="tab === 'members'" class="space-y-6" x-cloak>
            @if($team)
                <div>
                    <h3 class="text-lg font-semibold mb-4">Team-Mitglieder</h3>
                    <div class="space-y-3">
                        @foreach($team->users as $member)
                            <div class="d-flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="d-flex items-center gap-3">
                                    <div class="w-8 h-8 bg-primary text-on-primary rounded-full d-flex items-center justify-center">
                                        <span class="text-sm font-medium">
                                            {{ strtoupper(substr($member->name, 0, 2)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $member->name }}</div>
                                        <div class="text-sm text-gray-600">{{ $member->email }}</div>
                                    </div>
                                </div>
                                <div class="d-flex items-center gap-2">
                                    <x-ui-badge variant="primary" size="sm">
                                        {{ ucfirst($member->pivot->role) }}
                                    </x-ui-badge>
                                    @if($member->id === auth()->id())
                                        <x-ui-badge variant="success" size="sm">
                                            Du
                                        </x-ui-badge>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Einladungen --}}
        <div x-show="tab === 'invites'" x-cloak>
            @if($team && !$team->personal_team)
                @can('invite', $team)
                    <form wire:submit.prevent="inviteToTeam" class="space-y-4">
                        <x-ui-input-text
                            name="inviteEmail"
                            label="Mitglied einladen (E-Mail)"
                            wire:model.live="inviteEmail"
                            placeholder="E-Mail-Adresse"
                            required
                            :errorKey="'inviteEmail'"
                        />

                        <x-ui-input-select
                            name="inviteRole"
                            label="Rolle"
                            :options="$roles"
                            optionValue="value"
                            optionLabel="name"
                            :nullable="false"
                            wire:model.live="inviteRole"
                        />

                        <x-ui-button type="submit">
                            Einladung senden
                        </x-ui-button>
                    </form>
                @else
                    <div class="text-sm text-gray-500 mt-2">
                        <strong>Hinweis:</strong> Du hast keine Berechtigung, Einladungen zu versenden.
                    </div>
                @endcan
            @elseif($team && $team->personal_team)
                <div class="text-sm text-gray-500 mt-2">
                    <strong>Hinweis:</strong> Dies ist dein persönliches Team.<br>
                    Möchtest du mit anderen zusammenarbeiten, erstelle bitte ein neues Team.
                </div>
            @endif
        </div>

        {{-- Neues Team erstellen --}}
        <div x-show="tab === 'create'" x-cloak>
            <form wire:submit.prevent="createTeam" class="space-y-4">
                <x-ui-input-text
                    name="newTeamName"
                    label="Neues Team – Name"
                    wire:model.live="newTeamName"
                    placeholder="Team-Name eingeben..."
                    required
                    :errorKey="'newTeamName'"
                />
                <x-ui-button type="submit">
                    Team erstellen
                </x-ui-button>
            </form>
        </div>
    </div>

    <x-slot name="footer">
        <x-ui-button type="button" wire:click="closeModal">Schließen</x-ui-button>
    </x-slot>
</x-ui-modal>