<div x-data="{ tab: 'team' }" x-init="
    window.addEventListener('open-modal-team', (e) => { tab = e?.detail?.tab || 'team'; });
">
<x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Team verwalten</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">TEAM</span>
            </div>
        </div>
        <div class="flex gap-1 mt-4 border-b border-gray-200">
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'team', 'text-gray-500 hover:text-gray-700' : tab !== 'team' }" @click="tab = 'team'">Team</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'ai-user', 'text-gray-500 hover:text-gray-700' : tab !== 'ai-user' }" @click="tab = 'ai-user'">AI User</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'create', 'text-gray-500 hover:text-gray-700' : tab !== 'create' }" @click="tab = 'create'">Anlegen</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'billing', 'text-gray-500 hover:text-gray-700' : tab !== 'billing' }" @click="tab = 'billing'">Abrechnung</button>
        </div>
    </x-slot>

    {{-- Team Tab --}}
    <div class="mt-6" x-show="tab === 'team'" x-cloak>
        <div class="space-y-6">
            {{-- Team Switch --}}
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Aktuelles Team wechseln !</h3>
                @if(!empty($allTeams) && count($allTeams) > 1)
                    <x-ui-input-select
                        name="user.current_team_id"
                        label="Team auswählen"
                        :options="$allTeams"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="false"
                        wire:model.live="user.current_team_id"
                        x-data
                        @change="$wire.changeCurrentTeam($event.target.value)"
                    />
                @else
                    <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">Nur ein Team vorhanden. Lege unten ein neues Team an.</div>
                @endif
            </div>

            {{-- Team Settings (nur für Owner) --}}
            @isset($team)
            @if(($team->user_id ?? null) === auth()->id())
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Team-Einstellungen</h3>
                <div class="space-y-4 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                    @if(!empty($availableParentTeams) && count($availableParentTeams) > 0)
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Parent-Team
                            </label>
                            <x-ui-input-select
                                name="currentParentTeamId"
                                :options="$availableParentTeams"
                                :nullable="true"
                                wire:model="currentParentTeamId"
                                wire:change="updateParentTeam"
                            />
                            <p class="text-xs text-[var(--ui-muted)] mt-2">
                                Optional: Wähle ein Root-Team als Parent-Team. Kind-Teams erben Zugriff auf root-scoped Module (z.B. CRM, Organization).
                            </p>
                        </div>
                    @else
                        <div class="text-sm text-[var(--ui-muted)]">
                            Keine verfügbaren Parent-Teams. Erstelle zuerst ein Root-Team.
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Team Members --}}
            @if(isset($team))
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Team-Mitglieder</h3>
                <div class="space-y-3">
                    @foreach($team->users ?? [] as $member)
                        <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center gap-3">
                                @if(!empty($member->avatar))
                                    <img src="{{ $member->avatar }}" alt="{{ $member->fullname ?? $member->name }}" class="w-10 h-10 rounded-full object-cover" />
                                @else
                                <div class="w-10 h-10 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-full flex items-center justify-center">
                                    <span class="text-sm font-semibold">{{ strtoupper(mb_substr(($member->fullname ?? $member->name), 0, 2)) }}</span>
                                </div>
                                @endif
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="font-semibold text-[var(--ui-secondary)]">{{ $member->fullname ?? $member->name }}</div>
                                        @if($member->isAiUser())
                                            <x-ui-badge variant="purple" size="sm">AI</x-ui-badge>
                                        @endif
                                    </div>
                                    @if($member->email)
                                        <div class="text-sm text-[var(--ui-muted)]">{{ $member->email }}</div>
                                    @elseif($member->isAiUser())
                                        <div class="text-sm text-[var(--ui-muted)]">AI-User</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if(($team->user_id ?? null) === auth()->id())
                                    <x-ui-input-select
                                        name="member_role_{{ $member->id }}"
                                        :options="['owner' => 'Owner', 'admin' => 'Admin', 'member' => 'Member', 'viewer' => 'Viewer']"
                                        :nullable="false"
                                        x-data
                                        @change="$wire.updateMemberRole({{ $member->id }}, $event.target.value)"
                                        wire:model.defer="memberRoles.{{ $member->id }}"
                                    />

                                    @if(($member->id ?? null) !== auth()->id())
                                        <x-ui-confirm-button 
                                            action="removeMember"
                                            :value="$member->id"
                                            text="Entfernen"
                                            confirmText="Mitglied wirklich entfernen?"
                                            variant="danger"
                                        />
                                    @else
                                        <x-ui-badge variant="success" size="sm">Du</x-ui-badge>
                                    @endif
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
            @endif
            @endisset

            {{-- Invite Member --}}
            @if(isset($team) && !($team->personal_team ?? true))
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Mitglied einladen</h3>
                @php($roles = \Platform\Core\Enums\TeamRole::cases())
                <form wire:submit.prevent="inviteToTeam" class="space-y-4">
                    <x-ui-input-text
                        name="inviteEmails"
                        label="E-Mail(s)"
                        wire:model.live="inviteEmails"
                        placeholder="Mehrere Adressen mit Komma oder Zeilenumbruch trennen"
                        required
                        :errorKey="'inviteEmails'"
                    />
                    <p class="text-xs text-[var(--ui-muted)]">Beispiel: alice@example.com, bob@example.com</p>
                    <x-ui-input-select name="inviteRole" label="Rolle" :options="$roles" optionValue="value" optionLabel="name" :nullable="false" wire:model.live="inviteRole" />
                    <x-ui-button type="submit">Einladung senden</x-ui-button>
                </form>

                {{-- Offene Einladungen --}}
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-[var(--ui-secondary)] mb-3">Offene Einladungen</h4>
                    @php($pending = $this->pendingInvitations)
                    @if($pending && count($pending))
                        <div class="space-y-2">
                            @foreach($pending as $inv)
                                <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                    <div>
                                        <div class="font-medium text-[var(--ui-secondary)]">{{ $inv->email }}</div>
                                        <div class="text-xs text-[var(--ui-muted)]">Rolle: {{ ucfirst($inv->role ?? 'member') }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-ui-button variant="secondary-outline" x-data @click="navigator.clipboard.writeText('{{ url('/invitations/accept/'.$inv->token) }}'); $dispatch('notify', { type: 'success', message: 'Einladungslink kopiert' })">
                                            Link kopieren
                                        </x-ui-button>
                                        <x-ui-button variant="danger" wire:click="revokeInvitation({{ $inv->id }})">
                                            Widerrufen
                                        </x-ui-button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-sm text-[var(--ui-muted)]">Keine offenen Einladungen.</div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- AI User Tab --}}
    <div class="mt-6" x-show="tab === 'ai-user'" x-cloak>
        @if(isset($team) && !($team->personal_team ?? true))
        <div class="space-y-6" x-data="{ showCreateForm: false }">
            {{-- Create AI User Button --}}
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">AI-User in diesem Team</h3>
                <button 
                    type="button"
                    class="px-4 py-2 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-lg hover:opacity-90 transition-opacity font-medium"
                    @click="showCreateForm = !showCreateForm"
                    x-text="showCreateForm ? 'Abbrechen' : 'Neuen AI-User erstellen'"
                >
                    Neuen AI-User erstellen
                </button>
            </div>

            {{-- Create AI User Form (inline) --}}
            <div x-show="showCreateForm" x-cloak class="mb-6 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                <h4 class="text-md font-semibold text-[var(--ui-secondary)] mb-4">Neuen AI-User erstellen</h4>
                <form wire:submit.prevent="createAiUser" class="space-y-4">
                    <x-ui-input-text
                        name="aiUserForm.name"
                        label="Name"
                        wire:model.live="aiUserForm.name"
                        placeholder="Name des AI-Users"
                        required
                        :errorKey="'aiUserForm.name'"
                    />

                    @if(!empty($availableAiModels) && count($availableAiModels) > 0)
                        <x-ui-input-select
                            name="aiUserForm.core_ai_model_id"
                            label="AI-Model (optional)"
                            :options="$availableAiModels"
                            :nullable="true"
                            wire:model.live="aiUserForm.core_ai_model_id"
                            :errorKey="'aiUserForm.core_ai_model_id'"
                        />
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                            Anweisung / Beschreibung (optional)
                        </label>
                        <textarea
                            name="aiUserForm.instruction"
                            class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-[var(--ui-primary)] bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                            rows="4"
                            wire:model.live="aiUserForm.instruction"
                            placeholder="Beschreibe, wer dieser AI-User ist und welche Rolle er hat..."
                        ></textarea>
                        @error('aiUserForm.instruction')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-2">
                        <x-ui-button type="submit" variant="primary" wire:loading.attr="disabled">
                            AI-User erstellen
                        </x-ui-button>
                        <x-ui-button 
                            type="button" 
                            variant="secondary-outline" 
                            @click="showCreateForm = false"
                        >
                            Abbrechen
                        </x-ui-button>
                    </div>
                </form>
            </div>

            {{-- AI Users List --}}
            <div>
                @if(!empty($aiUsers) && count($aiUsers) > 0)
                    <div class="space-y-3">
                        @foreach($aiUsers as $aiUser)
                            <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-purple-500 text-white rounded-full flex items-center justify-center">
                                        <span class="text-sm font-semibold">AI</span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[var(--ui-secondary)]">{{ $aiUser->name }}</div>
                                        @if($aiUser->instruction)
                                            <div class="text-sm text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($aiUser->instruction, 60) }}</div>
                                        @endif
                                        @if($aiUser->coreAiModel)
                                            <div class="text-xs text-[var(--ui-muted)]">Model: {{ $aiUser->coreAiModel->name }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if(($team->user_id ?? null) === auth()->id())
                                        <x-ui-confirm-button 
                                            action="removeAiUser"
                                            :value="$aiUser->id"
                                            text="Entfernen"
                                            confirmText="AI-User wirklich entfernen?"
                                            variant="danger"
                                        />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">
                        Noch keine AI-User in diesem Team.
                    </div>
                @endif
            </div>

            {{-- Add Existing AI Users to Team (z.B. aus Parent-Team ins Kind-Team) --}}
            @if($canAddUsers && !empty($availableAiUsersToAdd) && count($availableAiUsersToAdd) > 0)
                <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Verfügbare AI-User hinzufügen</h3>
                    <p class="text-sm text-[var(--ui-muted)] mb-4">
                        Diese AI-User können zu diesem Team hinzugefügt werden (Home-Team oder Kind-Teams).
                    </p>
                    <div class="space-y-3">
                        @foreach($availableAiUsersToAdd as $aiUserToAdd)
                            <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-purple-500 text-white rounded-full flex items-center justify-center">
                                        <span class="text-sm font-semibold">AI</span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[var(--ui-secondary)]">{{ $aiUserToAdd->name }}</div>
                                        @if($aiUserToAdd->instruction)
                                            <div class="text-sm text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($aiUserToAdd->instruction, 60) }}</div>
                                        @endif
                                        @if($aiUserToAdd->team)
                                            <div class="text-xs text-[var(--ui-muted)]">Home-Team: {{ $aiUserToAdd->team->name }}</div>
                                        @endif
                                    </div>
                                </div>
                                <x-ui-button
                                    variant="primary"
                                    wire:click="addAiUserToTeam({{ $aiUserToAdd->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    Zum Team hinzufügen
                                </x-ui-button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @else
        <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">
            Kein Team ausgewählt oder Personal-Team. AI-User können nur in Teams erstellt werden.
        </div>
        @endif
    </div>

    {{-- Create Tab --}}
    <div class="mt-6" x-show="tab === 'create'" x-cloak>
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Neues Team anlegen</h3>
                <form wire:submit.prevent="createTeam" class="space-y-4">
                    {{-- Team Name --}}
                    <x-ui-input-text
                        name="newTeamName"
                        label="Team-Name"
                        wire:model.live="newTeamName"
                        required
                        placeholder="Name des Teams"
                        :errorKey="'newTeamName'"
                    />

                    {{-- Parent Team Selection --}}
                    @if(!empty($availableParentTeams) && count($availableParentTeams) > 0)
                        <div>
                            <x-ui-input-select
                                name="newParentTeamId"
                                label="Eltern-Team (optional)"
                                :options="$availableParentTeams"
                                :nullable="true"
                                nullLabel="Kein Eltern-Team"
                                wire:model.live="newParentTeamId"
                                :errorKey="'newParentTeamId'"
                            />
                            <p class="text-xs text-[var(--ui-muted)] mt-2">
                                Optional: Wähle ein Root-Team als Parent-Team. Kind-Teams erben Zugriff auf root-scoped Module (z.B. CRM, Organization).
                            </p>
                        </div>
                    @else
                        <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">
                            Keine verfügbaren Parent-Teams. Erstelle zuerst ein Root-Team.
                        </div>
                    @endif

                    {{-- Initial Members --}}
                    @if(!empty($availableUsersForTeam) && count($availableUsersForTeam) > 0)
                        <div x-data="{ 
                            selectedUsers: @entangle('newInitialMembers'),
                            toggleUser(userId) {
                                const index = this.selectedUsers.findIndex(u => u.user_id == userId);
                                if (index >= 0) {
                                    this.selectedUsers.splice(index, 1);
                                } else {
                                    this.selectedUsers.push({ user_id: userId, role: 'member' });
                                }
                            },
                            updateRole(userId, role) {
                                const index = this.selectedUsers.findIndex(u => u.user_id == userId);
                                if (index >= 0) {
                                    this.selectedUsers[index].role = role;
                                }
                            },
                            isSelected(userId) {
                                return this.selectedUsers.some(u => u.user_id == userId);
                            },
                            getRole(userId) {
                                const user = this.selectedUsers.find(u => u.user_id == userId);
                                return user ? user.role : 'member';
                            }
                        }">
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                Initiale Mitarbeiter hinzufügen (optional)
                            </label>
                            <p class="text-xs text-[var(--ui-muted)] mb-3">
                                Wähle Mitarbeiter und AI-User aus, die direkt beim Erstellen zum Team hinzugefügt werden sollen. Du kannst für jeden eine Rolle festlegen.
                            </p>
                            <div class="space-y-2 max-h-96 overflow-y-auto border border-[var(--ui-border)]/60 rounded-md p-3 bg-[var(--ui-muted-5)]">
                                @foreach($availableUsersForTeam as $availableUser)
                                    <div class="p-3 rounded-md border border-[var(--ui-border)]/40" :class="{ 'bg-[var(--ui-primary)]/10 border-[var(--ui-primary)]/40': isSelected({{ $availableUser->id }}) }">
                                        <div class="flex items-center gap-3">
                                            <input 
                                                type="checkbox" 
                                                :checked="isSelected({{ $availableUser->id }})"
                                                @change="toggleUser({{ $availableUser->id }})"
                                                class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                            />
                                            <div class="flex items-center gap-3 flex-1">
                                                @if($availableUser->isAiUser())
                                                    <div class="w-8 h-8 bg-purple-500 text-white rounded-full flex items-center justify-center">
                                                        <span class="text-xs font-semibold">AI</span>
                                                    </div>
                                                @elseif(!empty($availableUser->avatar))
                                                    <img src="{{ $availableUser->avatar }}" alt="{{ $availableUser->fullname ?? $availableUser->name }}" class="w-8 h-8 rounded-full object-cover" />
                                                @else
                                                    <div class="w-8 h-8 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-full flex items-center justify-center">
                                                        <span class="text-xs font-semibold">{{ strtoupper(mb_substr(($availableUser->fullname ?? $availableUser->name), 0, 2)) }}</span>
                                                    </div>
                                                @endif
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                            {{ $availableUser->fullname ?? $availableUser->name }}
                                                        </div>
                                                        @if($availableUser->isAiUser())
                                                            <x-ui-badge variant="purple" size="sm">AI</x-ui-badge>
                                                        @endif
                                                    </div>
                                                    @if($availableUser->email)
                                                        <div class="text-xs text-[var(--ui-muted)]">
                                                            {{ $availableUser->email }}
                                                        </div>
                                                    @elseif($availableUser->isAiUser())
                                                        <div class="text-xs text-[var(--ui-muted)]">AI-User</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Rollen-Auswahl (nur wenn User ausgewählt) --}}
                                        <div x-show="isSelected({{ $availableUser->id }})" x-cloak class="mt-3 pt-3 border-t border-[var(--ui-border)]/40">
                                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-2">
                                                Rolle für diesen Benutzer:
                                            </label>
                                            @php
                                                // Beim Erstellen eines Teams ist der Ersteller automatisch Owner, also kann er alle Rollen vergeben
                                                $canAssignOwner = true; // Beim Erstellen ist der User automatisch Owner
                                            @endphp
                                            <select 
                                                :value="getRole({{ $availableUser->id }})"
                                                @change="updateRole({{ $availableUser->id }}, $event.target.value)"
                                                class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-[var(--ui-primary)] bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                                            >
                                                <option value="member">Member</option>
                                                <option value="admin">Admin</option>
                                                <option value="viewer">Viewer</option>
                                                @if(!$availableUser->isAiUser() && $canAssignOwner)
                                                    <option value="owner">Owner</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('newInitialMembers')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">
                            Keine verfügbaren Mitarbeiter zum Hinzufügen. Du kannst später Mitglieder einladen.
                        </div>
                    @endif

                    {{-- Submit Button --}}
                    <div class="flex gap-2 pt-4">
                        <x-ui-button type="submit" variant="primary" wire:loading.attr="disabled">
                            @svg('heroicon-o-user-group', 'w-4 h-4 mr-2')
                            Team erstellen
                        </x-ui-button>
                        <x-ui-button 
                            type="button" 
                            variant="secondary-outline" 
                            @click="tab = 'team'"
                        >
                            Abbrechen
                        </x-ui-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Billing Tab --}}
    <div class="mt-6" x-show="tab === 'billing'" x-cloak>
        <p>Billing Tab - Inhalt entfernt zum Debuggen</p>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>
