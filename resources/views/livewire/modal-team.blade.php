<div x-data="{ tab: 'team' }" x-init="
    window.addEventListener('open-modal-team', (e) => { tab = e?.detail?.tab || 'team'; });
">
<x-nx-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[color:var(--nx-text)] m-0">Team verwalten</h2>
                <span class="text-xs text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] px-2 py-1 rounded-full">TEAM</span>
            </div>
        </div>
        <div class="flex gap-1 mt-4 border-b border-[color:var(--nx-line)]">
            <button type="button" class="px-3 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'team' ? 'text-[color:var(--nx-text)] border-[color:var(--nx-accent)]' : 'text-[color:var(--nx-muted)] border-transparent hover:text-[color:var(--nx-text)]'"
                x-on:click="tab = 'team'">Team</button>
        </div>
    </x-slot>

    {{-- Team Tab --}}
    <div class="mt-6" x-show="tab === 'team'" x-cloak>
        <div class="space-y-6">
            {{-- Team Switch --}}
            <div>
                <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Aktuelles Team wechseln</h3>
                @if(!empty($allTeams) && count($allTeams) > 1)
                    <x-nx-input-select
                        name="user.current_team_id"
                        label="Team auswählen"
                        :options="$allTeams"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="false"
                        wire:model.live="user.current_team_id"
                        x-data
                        x-on:change="$wire.changeCurrentTeam($event.target.value)"
                    />
                @else
                    <div class="text-sm text-[color:var(--nx-muted)] p-4 bg-[color:var(--nx-bg)] rounded-lg">Nur ein Team vorhanden. Lege unten ein neues Team an.</div>
                @endif
            </div>

            {{-- Neues Team anlegen --}}
            <div>
                <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Neues Team anlegen</h3>
                <form wire:submit.prevent="createTeam" class="space-y-4 p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    <x-nx-input-text
                        name="newTeamName"
                        label="Team-Name"
                        wire:model.live="newTeamName"
                        placeholder="z.B. Marketing, Entwicklung, ..."
                        required
                    />

                    @if(!empty($availableParentTeams) && count($availableParentTeams) > 0)
                        <x-nx-input-select
                            name="newParentTeamId"
                            label="Parent-Team (optional)"
                            :options="$availableParentTeams"
                            :nullable="true"
                            wire:model="newParentTeamId"
                        />
                        <p class="text-xs text-[color:var(--nx-muted)]">
                            Optional: Kind-Teams erben Zugriff auf root-scoped Module (z.B. CRM, Organization).
                        </p>
                    @endif

                    @if(!empty($availableUsersForTeam))
                        <div>
                            <label class="block text-sm font-medium text-[color:var(--nx-text)] mb-2">Mitglieder hinzufügen (optional)</label>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                @foreach($availableUsersForTeam as $availableUser)
                                    @php
                                        $isSelected = collect($newInitialMembers)->contains(fn($m) => ($m['user_id'] ?? null) == $availableUser->id);
                                    @endphp
                                    <label class="flex items-center gap-3 p-2 rounded-lg cursor-pointer hover:bg-[color:var(--nx-hover)] transition-colors {{ $isSelected ? 'bg-[color:var(--nx-accent-soft)] border border-[color:var(--nx-line-strong)]' : '' }}">
                                        <input type="checkbox" wire:click="toggleInitialMember({{ $availableUser->id }})" {{ $isSelected ? 'checked' : '' }} class="rounded border-[color:var(--nx-line-strong)] text-[color:var(--nx-accent)] focus:ring-[color:var(--nx-accent)]" />
                                        <div class="flex items-center gap-2">
                                            <x-nx-avatar :name="$availableUser->fullname ?? $availableUser->name" :src="$availableUser->avatar ?? null" size="md" />
                                            <span class="text-sm text-[color:var(--nx-text)]">{{ $availableUser->fullname ?? $availableUser->name }}</span>
                                            @if($availableUser->isAiUser())
                                                <x-nx-badge variant="info">AI</x-nx-badge>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-nx-button type="submit" variant="primary">Team erstellen</x-nx-button>
                </form>
            </div>

            {{-- Team Settings (nur für Owner) --}}
            @if(isset($team) && ($team->user_id ?? null) === auth()->id())
            <div>
                <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Team-Einstellungen</h3>
                <div class="space-y-4 p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                    @if(!empty($availableParentTeams) && count($availableParentTeams) > 0)
                        <div>
                            <x-nx-input-select
                                name="currentParentTeamId"
                                label="Parent-Team"
                                :options="$availableParentTeams"
                                :nullable="true"
                                wire:model="currentParentTeamId"
                                wire:change="updateParentTeam"
                            />
                            <p class="text-xs text-[color:var(--nx-muted)] mt-2">
                                Optional: Wähle ein Root-Team als Parent-Team. Kind-Teams erben Zugriff auf root-scoped Module (z.B. CRM, Organization).
                            </p>
                        </div>
                    @else
                        <div class="text-sm text-[color:var(--nx-muted)]">
                            Keine verfügbaren Parent-Teams. Erstelle zuerst ein Root-Team.
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Team Members --}}
            @if(isset($team))
            <div>
                <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Team-Mitglieder</h3>
                <div class="space-y-3">
                    @foreach($team->users ?? [] as $member)
                        <div class="flex items-center justify-between p-4 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                            <div class="flex items-center gap-3">
                                <x-nx-avatar :name="$member->fullname ?? $member->name" :src="$member->avatar ?? null" size="lg" />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="font-semibold text-[color:var(--nx-text)]">{{ $member->fullname ?? $member->name }}</div>
                                        @if($member->isAiUser())
                                            <x-nx-badge variant="info">AI</x-nx-badge>
                                        @endif
                                    </div>
                                    @if($member->email)
                                        <div class="text-sm text-[color:var(--nx-muted)]">{{ $member->email }}</div>
                                    @elseif($member->isAiUser())
                                        <div class="text-sm text-[color:var(--nx-muted)]">AI-User</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if(($team->user_id ?? null) === auth()->id())
                                    <x-nx-input-select
                                        name="member_role_{{ $member->id }}"
                                        :options="['owner' => 'Owner', 'admin' => 'Admin', 'member' => 'Member', 'viewer' => 'Viewer']"
                                        :nullable="false"
                                        x-data
                                        x-on:change="$wire.updateMemberRole({{ $member->id }}, $event.target.value)"
                                        wire:model.defer="memberRoles.{{ $member->id }}"
                                    />

                                    @if(($member->id ?? null) !== auth()->id())
                                        <x-nx-button variant="danger" wire:click="removeMember({{ $member->id }})" wire:confirm="Mitglied wirklich entfernen?">
                                            Entfernen
                                        </x-nx-button>
                                    @else
                                        <x-nx-badge variant="success">Du</x-nx-badge>
                                    @endif
                                @else
                                    <x-nx-badge>{{ ucfirst($member->pivot->role ?? 'member') }}</x-nx-badge>
                                    @if(($member->id ?? null) === auth()->id())
                                        <x-nx-badge variant="success">Du</x-nx-badge>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Invite Member --}}
            @if(isset($team) && !($team->personal_team ?? true))
            <div>
                <h3 class="text-lg font-semibold text-[color:var(--nx-text)] mb-4">Mitglied einladen</h3>
                @php($roles = \Platform\Core\Enums\TeamRole::cases())
                <form wire:submit.prevent="inviteToTeam" class="space-y-4">
                    <x-nx-input-text
                        name="inviteEmails"
                        label="E-Mail(s)"
                        wire:model.live="inviteEmails"
                        placeholder="Mehrere Adressen mit Komma oder Zeilenumbruch trennen"
                        required
                        :errorKey="'inviteEmails'"
                    />
                    <p class="text-xs text-[color:var(--nx-muted)]">Beispiel: alice@example.com, bob@example.com</p>
                    <x-nx-input-select name="inviteRole" label="Rolle" :options="$roles" optionValue="value" optionLabel="name" :nullable="false" wire:model.live="inviteRole" />
                    <x-nx-button type="submit" variant="primary">Einladung senden</x-nx-button>
                </form>

                {{-- Offene Einladungen --}}
                <div class="mt-6">
                    <h4 class="text-md font-semibold text-[color:var(--nx-text)] mb-3">Offene Einladungen</h4>
                    @php($pending = $this->pendingInvitations)
                    @if($pending && count($pending))
                        <div class="space-y-2">
                            @foreach($pending as $inv)
                                <div class="flex items-center justify-between p-3 bg-[color:var(--nx-bg)] rounded-lg border border-[color:var(--nx-line)]">
                                    <div>
                                        <div class="font-medium text-[color:var(--nx-text)]">{{ $inv->email }}</div>
                                        <div class="text-xs text-[color:var(--nx-muted)]">Rolle: {{ ucfirst($inv->role ?? 'member') }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-nx-button variant="secondary" x-data x-on:click="navigator.clipboard.writeText('{{ url('/invitations/accept/'.$inv->token) }}'); $dispatch('notify', { type: 'success', message: 'Einladungslink kopiert' })">
                                            Link kopieren
                                        </x-nx-button>
                                        <x-nx-button variant="danger" wire:click="revokeInvitation({{ $inv->id }})">
                                            Widerrufen
                                        </x-nx-button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-sm text-[color:var(--nx-muted)]">Keine offenen Einladungen.</div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-nx-button variant="secondary" x-on:click="modalShow = false">
                Schließen
            </x-nx-button>
        </div>
    </x-slot>
</x-nx-modal>
</div>
