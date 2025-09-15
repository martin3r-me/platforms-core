<x-ui-modal wire:model="modalShow">
    <x-slot name="header">
        Team-Verwaltung
        @if($team)
            <div class="text-sm font-normal text-gray-600 mt-1">
                {{ $team->name }}
            </div>
        @endif
    </x-slot>

    <div x-data="{ tab: 'switch' }">
        {{-- Tab Navigation --}}
        <div class="d-flex border-b mb-4">
            <button
                class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                :class="{'font-bold border-b-2 border-primary': tab === 'switch'}"
                @click="tab = 'switch'"
                type="button"
            >
                Team wechseln
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
            @if($team && $team->user_id === auth()->id())
                <button
                    class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                    :class="{'font-bold border-b-2 border-primary': tab === 'payment'}"
                    @click="tab = 'payment'"
                    type="button"
                >
                    Zahlungsdetails
                </button>
            @endif
            <button
                class="px-4 py-2 border-0 bg-transparent cursor-pointer"
                :class="{'font-bold border-b-2 border-primary': tab === 'create'}"
                @click="tab = 'create'"
                type="button"
            >
                Neues Team erstellen
            </button>
        </div>

        {{-- Team wechseln --}}
        <div x-show="tab === 'switch'" class="space-y-6" x-cloak>
            @if($allTeams && count($allTeams) > 1)
                <div class="mb-4">
                    <x-ui-input-select
                        name="user.current_team_id"
                        variant="danger"
                        label="Aktuelles Team wechseln"
                        :options="$allTeams"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="false"
                        wire:model.live="user.current_team_id"
                    />
                </div>
            @else
                <div class="text-center py-8">
                    <p class="text-gray-600">Du bist nur in einem Team. Erstelle ein neues Team, um zwischen Teams wechseln zu können.</p>
                </div>
            @endif

            @if($team && $team->personal_team)
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                        <span class="text-blue-800 font-medium">Persönliches Team</span>
                    </div>
                    <p class="text-blue-700 text-sm mt-2">
                        Dies ist dein persönliches Team. Erstelle ein neues Team, um mit anderen zusammenzuarbeiten.
                    </p>
                </div>
            @endif
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
                                            {{ strtoupper(mb_substr(($member->fullname ?? $member->name), 0, 2)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $member->fullname ?? $member->name }}</div>
                                        <div class="text-sm text-gray-600">{{ $member->email }}</div>
                                    </div>
                                </div>
                                <div class="d-flex items-center gap-2">
                                    @if($team->user_id === auth()->id() && $member->id !== auth()->id())
                                        <select
                                            name="member_role_{{ $member->id }}"
                                            class="min-w-[9rem] px-2 py-1 border rounded"
                                            wire:change="updateMemberRole({{ $member->id }}, $event.target.value)"
                                        >
                                            <option value="owner" {{ $member->pivot->role === 'owner' ? 'selected' : '' }}>Owner</option>
                                            <option value="admin" {{ $member->pivot->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                            <option value="member" {{ $member->pivot->role === 'member' ? 'selected' : '' }}>Member</option>
                                            <option value="viewer" {{ $member->pivot->role === 'viewer' ? 'selected' : '' }}>Viewer</option>
                                        </select>
                                    @else
                                        <x-ui-badge variant="primary" size="sm">
                                            {{ ucfirst($member->pivot->role) }}
                                        </x-ui-badge>
                                        @if($member->id === auth()->id())
                                            <x-ui-badge variant="success" size="sm">
                                                Du
                                            </x-ui-badge>
                                        @endif
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

        {{-- Zahlungsdetails --}}
        <div x-show="tab === 'payment'" class="space-y-6" x-cloak>
            @if($team && $team->user_id === auth()->id())
                <div>
                    <h3 class="text-lg font-semibold mb-4">Zahlungsmethode</h3>

                    @if($team->mollie_payment_method_id)
                        <div class="p-4 bg-gray-50 rounded-lg mb-4">
                            <div class="d-flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-gray-900">
                                        {{ ucfirst($team->payment_method_brand) }} **** {{ $team->payment_method_last_4 }}
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Ablaufdatum: {{ $team->payment_method_expires_at?->format('m/Y') }}
                                    </div>
                                </div>
                                <div class="d-flex items-center gap-2">
                                    <x-ui-button variant="secondary-outline" size="sm" wire:click="updatePaymentMethod">Bearbeiten</x-ui-button>
                                    <x-ui-button variant="danger-outline" size="sm" wire:click="removePaymentMethod">Löschen</x-ui-button>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8" x-data>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Zahlungsmethode</h4>
                            <p class="text-gray-600 mb-4">Füge eine Kreditkarte hinzu, um Abrechnungen zu ermöglichen.</p>
                            <template x-if="!$wire.addingPayment">
                                <x-ui-button variant="primary" wire:click="addPaymentMethod">Kreditkarte hinzufügen</x-ui-button>
                            </template>

                            <template x-if="$wire.addingPayment">
                                <div class="max-w-md mx-auto text-left">
                                    <div class="mb-2 text-sm text-gray-700">Kartendaten</div>
                                    <div id="mollie-card"></div>
                                    <div class="d-flex items-center gap-2 mt-4">
                                        <x-ui-button variant="secondary-outline" size="sm" wire:click="cancelAddPayment">Abbrechen</x-ui-button>
                                        <x-ui-button id="mollie-card-submit" variant="primary" size="sm">Speichern</x-ui-button>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <script>
                            document.addEventListener('livewire:init', () => {
                                Livewire.hook('message.processed', (message, component) => {
                                    const container = document.getElementById('mollie-card');
                                    if (!container) return;
                                    if (container.dataset.mollieInitialized === '1') return;
                                    if (!window.Mollie) {
                                        const s = document.createElement('script');
                                        s.src = 'https://js.mollie.com/v1/mollie.js';
                                        s.onload = initMollie;
                                        document.body.appendChild(s);
                                    } else {
                                        initMollie();
                                    }

                                    function initMollie() {
                                        if (!window.Mollie) return;
                                        const mollie = Mollie($wire.mollieKey || 'test_xxxxx', { locale: 'de_DE' });
                                        const card = mollie.createComponent('card');
                                        card.mount('#mollie-card');
                                        container.dataset.mollieInitialized = '1';

                                        const btn = document.getElementById('mollie-card-submit');
                                        if (btn && !btn.dataset.bound) {
                                            btn.dataset.bound = '1';
                                            btn.addEventListener('click', async () => {
                                                const { token, error } = await mollie.createToken();
                                                if (error) { alert(error.message); return; }
                                                Livewire.dispatch('invoke-save-payment', { token });
                                            });
                                        }
                                    }
                                });
                            });
                        </script>
                        <script>
                            document.addEventListener('livewire:load', () => {
                                Livewire.on('invoke-save-payment', (e) => {
                                    const token = e?.token;
                                    if (!token) return;
                                    Livewire.find(@this.__instance.id).call('savePaymentMethod', token);
                                });
                            });
                        </script>
                    @endif
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