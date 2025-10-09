<x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-gray-900 m-0">Team Management</h2>
                <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">Team</span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        {{-- Team Switch --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Aktuelles Team wechseln</h3>
            @if(!empty($allTeams) && count($allTeams) > 1)
                <x-ui-input-select
                    name="user.current_team_id"
                    label="Team auswählen"
                    :options="$allTeams"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="false"
                    wire:model.live="user.current_team_id"
                />
            @else
                <div class="text-sm text-gray-500 p-4 bg-gray-50 rounded-lg">Nur ein Team vorhanden. Lege unten ein neues Team an.</div>
            @endif
        </div>

        {{-- Create Team --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Neues Team erstellen</h3>
            <form wire:submit.prevent="createTeam" class="space-y-4">
                <x-ui-input-text
                    name="newTeamName"
                    label="Team-Name"
                    wire:model.live="newTeamName"
                    placeholder="Team-Name eingeben..."
                    required
                    :errorKey="'newTeamName'"
                />
                <x-ui-button type="submit">Team erstellen</x-ui-button>
            </form>
        </div>

        {{-- Team Members --}}
        @isset($team)
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Team-Mitglieder</h3>
            <div class="space-y-3">
                @foreach($team->users ?? [] as $member)
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center">
                                <span class="text-sm font-semibold">{{ strtoupper(mb_substr(($member->fullname ?? $member->name), 0, 2)) }}</span>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">{{ $member->fullname ?? $member->name }}</div>
                                <div class="text-sm text-gray-500">{{ $member->email }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(($team->user_id ?? null) === auth()->id() && ($member->id ?? null) !== auth()->id())
                                <select name="member_role_{{ $member->id }}" class="min-w-[9rem] px-3 py-2 border border-gray-300 rounded-lg bg-white text-gray-900 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" wire:change="updateMemberRole({{ $member->id }}, $event.target.value)">
                                    <option value="owner" {{ ($member->pivot->role ?? '') === 'owner' ? 'selected' : '' }}>Owner</option>
                                    <option value="admin" {{ ($member->pivot->role ?? '') === 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="member" {{ ($member->pivot->role ?? '') === 'member' ? 'selected' : '' }}>Member</option>
                                    <option value="viewer" {{ ($member->pivot->role ?? '') === 'viewer' ? 'selected' : '' }}>Viewer</option>
                                </select>
                            @else
                                <x-ui-badge variant="primary" size="sm">{{ ucfirst($member->pivot->role ?? 'member') }}</x-ui-badge>
                                @if(($member->id ?? null) === auth()->id())
                                    <x-ui-badge variant="success" size="sm">Du</x-ui-badge>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endisset

        {{-- Invite Member --}}
        @if(isset($team) && !($team->personal_team ?? true))
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mitglied einladen</h3>
            @php($roles = \Platform\Core\Enums\TeamRole::cases())
            <form wire:submit.prevent="inviteToTeam" class="space-y-4">
                <x-ui-input-text name="inviteEmail" label="E-Mail" wire:model.live="inviteEmail" placeholder="E-Mail-Adresse" required :errorKey="'inviteEmail'" />
                <x-ui-input-select name="inviteRole" label="Rolle" :options="$roles" optionValue="value" optionLabel="name" :nullable="false" wire:model.live="inviteRole" />
                <x-ui-button type="submit">Einladung senden</x-ui-button>
            </form>
        </div>
        @endif
    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>