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
        <div class="space-y-6" x-data="{ showCreateForm: false }" 
             x-init="
                $watch('showCreateForm', value => {
                    if (!value) {
                        // Formular zurücksetzen wenn geschlossen
                        @this.set('aiUserForm.name', '');
                        @this.set('aiUserForm.core_ai_model_id', null);
                        @this.set('aiUserForm.instruction', '');
                    }
                });
                window.addEventListener('ai-user-created', () => {
                    showCreateForm = false;
                });
             ">
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

            {{-- Add Existing AI Users to Team --}}
            @php
                $userRole = isset($team) ? ($team->users()->where('user_id', auth()->id())->first()?->pivot->role ?? null) : null;
                $canAddUsers = $userRole && in_array($userRole, [\Platform\Core\Enums\TeamRole::OWNER->value, \Platform\Core\Enums\TeamRole::ADMIN->value]);
            @endphp
            @if($canAddUsers && !empty($availableAiUsersToAdd) && count($availableAiUsersToAdd) > 0)
            <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Verfügbare AI-User hinzufügen</h3>
                <p class="text-sm text-[var(--ui-muted)] mb-4">
                    Diese AI-User können zu diesem Team hinzugefügt werden (Home-Team oder Kind-Teams).
                </p>
                <div class="space-y-3">
                    @foreach($availableAiUsersToAdd as $aiUser)
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
                                    @if($aiUser->team)
                                        <div class="text-xs text-[var(--ui-muted)]">Home-Team: {{ $aiUser->team->name }}</div>
                                    @endif
                                    @if($aiUser->coreAiModel)
                                        <div class="text-xs text-[var(--ui-muted)]">Model: {{ $aiUser->coreAiModel->name }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-ui-button 
                                    variant="primary" 
                                    wire:click="addAiUserToTeam({{ $aiUser->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    Zum Team hinzufügen
                                </x-ui-button>
                            </div>
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
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Neues Team erstellen</h3>
                <form wire:submit.prevent="createTeam" class="space-y-4">
                    <x-ui-input-text
                        name="newTeamName"
                        label="Team-Name"
                        wire:model.live="newTeamName"
                        placeholder="Team-Name eingeben..."
                        required
                        :errorKey="'newTeamName'"
                    />
                    @if(!empty($availableParentTeams) && count($availableParentTeams) > 0)
                        <x-ui-input-select
                            name="newParentTeamId"
                            label="Parent-Team (optional)"
                            :options="$availableParentTeams"
                            :nullable="true"
                            wire:model.live="newParentTeamId"
                            :errorKey="'newParentTeamId'"
                        />
                        <p class="text-xs text-[var(--ui-muted)]">
                            Optional: Wähle ein Root-Team als Parent-Team. Kind-Teams erben Zugriff auf root-scoped Module (z.B. CRM, Organization).
                        </p>
                    @endif
                    <x-ui-button type="submit">Team erstellen</x-ui-button>
                </form>
            </div>
        </div>
    </div>

    {{-- Billing Tab --}}
    <div class="mt-6" x-show="tab === 'billing'" x-cloak>
        <div class="space-y-6">
            {{-- Billing Overview --}}
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Abrechnungsübersicht</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <div class="text-sm text-[var(--ui-muted)] font-medium">Aktueller Monat</div>
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ number_format(($monthlyTotal ?? 0), 2, ',', '.') }} €</div>
                    </div>
                    <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <div class="text-sm text-[var(--ui-muted)] font-medium">Letzter Monat</div>
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ number_format(($lastMonthTotal ?? 0), 2, ',', '.') }} €</div>
                    </div>
                    <div class="p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                        <div class="text-sm text-[var(--ui-muted)] font-medium">Jahr gesamt</div>
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ number_format(($yearlyTotal ?? 0), 2, ',', '.') }} €</div>
                    </div>
                </div>
            </div>

            {{-- Detailed Billing Table --}}
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Kostenübersicht für diesen Monat</h3>
                @if(!empty($monthlyUsages) && count($monthlyUsages))
                    <div class="overflow-auto rounded-lg border border-[var(--ui-border)]/60">
                        <table class="w-full text-sm bg-[var(--ui-surface)]">
                            <thead class="bg-[var(--ui-muted-5)]">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-[var(--ui-secondary)]">Datum</th>
                                    <th class="px-4 py-3 text-left font-semibold text-[var(--ui-secondary)]">Modul</th>
                                    <th class="px-4 py-3 text-left font-semibold text-[var(--ui-secondary)]">Typ</th>
                                    <th class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">Anzahl</th>
                                    <th class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">Einzelpreis</th>
                                    <th class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">Gesamt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyUsages as $usage)
                                    <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                        <td class="px-4 py-3 text-[var(--ui-secondary)]">{{ \Illuminate\Support\Carbon::parse($usage->usage_date)->format('d.m.Y') }}</td>
                                        <td class="px-4 py-3 text-[var(--ui-secondary)]">{{ $usage->label }}</td>
                                        <td class="px-4 py-3 text-[var(--ui-muted)]">{{ $usage->billable_type }}</td>
                                        <td class="px-4 py-3 text-right text-[var(--ui-secondary)]">{{ $usage->count }}</td>
                                        <td class="px-4 py-3 text-right text-[var(--ui-muted)]">{{ number_format($usage->cost_per_unit, 4, ',', '.') }} €</td>
                                        <td class="px-4 py-3 text-right font-semibold text-[var(--ui-secondary)]">{{ number_format($usage->total_cost, 2, ',', '.') }} €</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex justify-end">
                        <div class="px-4 py-2 bg-[var(--ui-primary-5)] rounded-lg border border-[var(--ui-primary)]/20">
                            <span class="font-bold text-[var(--ui-primary)]">Monatssumme: {{ number_format((float)($monthlyTotal ?? 0), 2, ',', '.') }} €</span>
                        </div>
                    </div>
                @else
                    <div class="text-[var(--ui-muted)] text-sm p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">Für diesen Monat liegen noch keine Nutzungsdaten vor.</div>
                @endif
            </div>

            {{-- Billing Details --}}
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Rechnungsdetails</h3>
                <div class="space-y-4">
                    <x-ui-input-text
                        name="billing.company_name"
                        label="Firmenname"
                        wire:model.live="billing.company_name"
                        placeholder="Firmenname für Rechnungen"
                    />
                    <x-ui-input-text
                        name="billing.tax_id"
                        label="Steuernummer"
                        wire:model.live="billing.tax_id"
                        placeholder="Steuernummer (optional)"
                    />
                    <x-ui-input-text
                        name="billing.vat_id"
                        label="USt-IdNr."
                        wire:model.live="billing.vat_id"
                        placeholder="Umsatzsteuer-Identifikationsnummer (optional)"
                    />
                </div>
            </div>

            {{-- Payment Details --}}
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zahlungsdetails</h3>
                <div class="space-y-4">
                    <x-ui-input-text
                        name="billing.billing_email"
                        label="Rechnungs-E-Mail"
                        wire:model.live="billing.billing_email"
                        placeholder="E-Mail für Rechnungen"
                        type="email"
                    />
                    <x-ui-input-text
                        name="billing.billing_address"
                        label="Rechnungsadresse"
                        wire:model.live="billing.billing_address"
                        placeholder="Vollständige Rechnungsadresse"
                    />
                    <x-ui-input-select
                        name="billing.payment_method"
                        label="Zahlungsmethode"
                        :options="$paymentMethodOptions"
                        :nullable="false"
                        wire:model.live="billing.payment_method"
                    />
                </div>
            </div>

            {{-- Save Billing --}}
            <div class="pt-4 border-t border-[var(--ui-border)]/60">
                <x-ui-button wire:click="saveBillingDetails" variant="primary">
                    Rechnungsdaten speichern
                </x-ui-button>
            </div>
        </div>
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