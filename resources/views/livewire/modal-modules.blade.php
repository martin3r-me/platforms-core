<x-ui-modal size="lg" wire:model="modalShow">
    <div x-data="{ tab: 'modules' }" x-init="
        window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });
    ">
        <x-slot name="header">
            <div class="d-flex items-center justify-between w-full">
                <div class="font-medium">Zentrale Steuerung</div>
                <div class="text-xs text-gray-500">Tipp: ⌘K / M öffnet dieses Menü</div>
            </div>
            <div class="d-flex gap-3 mt-3 border-b pb-1">
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'modules' }" @click="tab = 'modules'">Module</button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'team' }" @click="tab = 'team'">Team</button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'billing' }" @click="tab = 'billing'">Abrechnung</button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'account' }" @click="tab = 'account'">Konto</button>
                @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                    <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer ml-auto" :class="{ 'font-bold border-b-2 border-primary' : tab === 'matrix' }" @click="tab = 'matrix'">Matrix</button>
                @endif
            </div>
        </x-slot>

        {{-- Body: Modules Tab --}}
        <div class="mt-2" x-show="tab === 'modules'" x-cloak>
            @if(!$showMatrix)
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4">
            <a href="{{ route('platform.dashboard') }}"
               class="d-flex items-center gap-2 p-4 rounded-md border border-solid border-1 transition hover:border-primary hover:bg-primary-10">

                <div class="text-xs text-gray-500">
                    Dashboard
                </div>

                @if(!empty($module['icon']))
                    <x-dynamic-component :component="$module['icon']" class="w-5 h-5 text-primary" />
                @else
                    <x-heroicon-o-cube class="w-5 h-5 text-primary" />
                @endif

                <span class="font-medium text-secondary">
                    Dashboard
                </span>
            </a>
            </div>

            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($modules as $module)
                    @php
                        $title = $module['title'] ?? $module['label'] ?? 'Modul';
                        $icon  = $module['icon'] ?? null;
                        $routeName = $module['navigation']['route'] ?? null;
                        $finalUrl = $routeName ? route($routeName) : ($module['url'] ?? '#');
                    @endphp

                    <a href="{{ $finalUrl }}" class="d-flex items-center gap-3 p-3 rounded-md border border-solid border-1 transition hover:border-primary hover:bg-primary-10">
                        <div class="flex-shrink-0">
                            @if(!empty($icon))
                                <x-dynamic-component :component="$icon" class="w-6 h-6 text-primary" />
                            @else
                                @svg('heroicon-o-cube', 'w-6 h-6 text-primary')
                            @endif
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="font-medium text-secondary truncate">{{ $title }}</div>
                            <div class="text-xs text-gray-500 truncate">
                                {{ $routeName ? $routeName : ($finalUrl ?? '') }}
                            </div>
                        </div>
                        <div class="flex-shrink-0 text-xs text-gray-400 hidden md:block">Öffnen</div>
                    </a>
                @endforeach
            </div>
        </div>
            @endif
        </div>

        {{-- Team Tab (Teil 1: Wechsel & Erstellen) --}}
        <div class="mt-2" x-show="tab === 'team'" x-cloak>
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold mb-3">Aktuelles Team wechseln</h3>
                    @if($allTeams && count($allTeams) > 1)
                        <x-ui-input-select
                            name="user.current_team_id"
                            variant="danger"
                            label="Team auswählen"
                            :options="$allTeams"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="false"
                            wire:model.live="user.current_team_id"
                        />
                    @else
                        <div class="text-sm text-gray-600">Nur ein Team vorhanden. Lege unten ein neues Team an.</div>
                    @endif
                </div>

                @if($team && $team->personal_team)
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                            <span class="text-blue-800 font-medium">Persönliches Team</span>
                        </div>
                        <p class="text-blue-700 text-sm mt-2">Dies ist dein persönliches Team. Erstelle ein neues Team, um mit anderen zusammenzuarbeiten.</p>
                    </div>
                @endif

                <div>
                    <h3 class="text-lg font-semibold mb-3">Neues Team erstellen</h3>
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

                @if($team)
                <div>
                    <h3 class="text-lg font-semibold mb-3">Team-Mitglieder</h3>
                    <div class="space-y-3">
                        @foreach($team->users as $member)
                            <div class="d-flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="d-flex items-center gap-3">
                                    <div class="w-8 h-8 bg-primary text-on-primary rounded-full d-flex items-center justify-center">
                                        <span class="text-sm font-medium">{{ strtoupper(mb_substr(($member->fullname ?? $member->name), 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $member->fullname ?? $member->name }}</div>
                                        <div class="text-sm text-gray-600">{{ $member->email }}</div>
                                    </div>
                                </div>
                                <div class="d-flex items-center gap-2">
                                    @if($team->user_id === auth()->id() && $member->id !== auth()->id())
                                        <select name="member_role_{{ $member->id }}" class="min-w-[9rem] px-2 py-1 border rounded" wire:change="updateMemberRole({{ $member->id }}, $event.target.value)">
                                            <option value="owner" {{ $member->pivot->role === 'owner' ? 'selected' : '' }}>Owner</option>
                                            <option value="admin" {{ $member->pivot->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                            <option value="member" {{ $member->pivot->role === 'member' ? 'selected' : '' }}>Member</option>
                                            <option value="viewer" {{ $member->pivot->role === 'viewer' ? 'selected' : '' }}>Viewer</option>
                                        </select>
                                    @else
                                        <x-ui-badge variant="primary" size="sm">{{ ucfirst($member->pivot->role) }}</x-ui-badge>
                                        @if($member->id === auth()->id())
                                            <x-ui-badge variant="success" size="sm">Du</x-ui-badge>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($team && !$team->personal_team)
                <div>
                    <h3 class="text-lg font-semibold mb-3">Mitglied einladen</h3>
                    @php($roles = \Platform\Core\Enums\TeamRole::cases())
                    <form wire:submit.prevent="inviteToTeam" class="space-y-4">
                        <x-ui-input-text name="inviteEmail" label="E-Mail" wire:model.live="inviteEmail" placeholder="E-Mail-Adresse" required :errorKey="'inviteEmail'" />
                        <x-ui-input-select name="inviteRole" label="Rolle" :options="$roles" optionValue="value" optionLabel="name" :nullable="false" wire:model.live="inviteRole" />
                        <x-ui-button type="submit">Einladung senden</x-ui-button>
                    </form>
                </div>
                @endif

                @if($team && $team->user_id === auth()->id())
                <div>
                    <h3 class="text-lg font-semibold mb-3">Zahlungsmethode</h3>
                    @if($team->mollie_payment_method_id)
                        <div class="p-4 bg-gray-50 rounded-lg mb-4">
                            <div class="d-flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-gray-900">{{ ucfirst($team->payment_method_brand) }} **** {{ $team->payment_method_last_4 }}</div>
                                    <div class="text-sm text-gray-600">Ablaufdatum: {{ $team->payment_method_expires_at?->format('m/Y') }}</div>
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
                                    } else { initMollie(); }
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
        </div>

        {{-- Billing Tab (vollständig) --}}
        <div class="mt-2" x-show="tab === 'billing'" x-cloak>
            <div class="p-4">
                <h2 class="text-lg font-semibold mb-2">Kostenübersicht für diesen Monat</h2>
                @if($monthlyUsages && count($monthlyUsages))
                    <table class="w-full text-sm border rounded">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-2 py-1 text-left">Datum</th>
                                <th class="px-2 py-1 text-left">Modul</th>
                                <th class="px-2 py-1 text-left">Typ</th>
                                <th class="px-2 py-1 text-right">Anzahl</th>
                                <th class="px-2 py-1 text-right">Einzelpreis</th>
                                <th class="px-2 py-1 text-right">Gesamt</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($monthlyUsages as $usage)
                            <tr>
                                <td class="px-2 py-1">{{ \Illuminate\Support\Carbon::parse($usage->usage_date)->format('d.m.Y') }}</td>
                                <td class="px-2 py-1">{{ $usage->label }}</td>
                                <td class="px-2 py-1">{{ $usage->billable_type }}</td>
                                <td class="px-2 py-1 text-right">{{ $usage->count }}</td>
                                <td class="px-2 py-1 text-right">{{ number_format($usage->cost_per_unit, 4, ',', '.') }} €</td>
                                <td class="px-2 py-1 text-right">{{ number_format($usage->total_cost, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="mt-4 font-bold text-right">Monatssumme: {{ number_format($monthlyTotal, 2, ',', '.') }} €</div>
                @else
                    <div class="text-gray-500 text-sm py-4">Für diesen Monat liegen noch keine Nutzungsdaten vor.</div>
                @endif
            </div>
        </div>

        {{-- Account Tab (vollständig) --}}
        <div class="mt-2" x-show="tab === 'account'" x-cloak>
            <div class="space-y-6">
                <div class="d-flex items-center gap-3 p-3 bg-muted-5 rounded">
                    <div class="w-10 h-10 rounded-full bg-primary text-on-primary d-flex items-center justify-center">
                        <span class="font-semibold">{{ strtoupper(mb_substr((auth()->user()->fullname ?? auth()->user()->name ?? 'U'), 0, 2)) }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="font-medium text-secondary truncate">{{ auth()->user()->fullname ?? auth()->user()->name }}</div>
                        <div class="text-sm text-gray-600 truncate">{{ auth()->user()->email }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-ui-button variant="secondary-outline" @click="tab = 'team'"><div class="d-flex items-center gap-2">@svg('heroicon-o-users', 'w-5 h-5') Team verwalten</div></x-ui-button>
                    <x-ui-button variant="secondary-outline" @click="tab = 'billing'"><div class="d-flex items-center gap-2">@svg('heroicon-o-banknotes', 'w-5 h-5') Abrechnung & Kosten</div></x-ui-button>
                    <x-ui-button variant="secondary-outline" @click="tab = 'modules'"><div class="d-flex items-center gap-2">@svg('heroicon-o-squares-2x2', 'w-5 h-5') Module wechseln</div></x-ui-button>
                </div>

                <div class="d-flex items-center justify-between w-full">
                    <div class="text-xs text-gray-500">Angemeldet als {{ auth()->user()->email }}</div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                        <x-ui-button variant="danger" type="submit"><div class="d-flex items-center gap-2">@svg('heroicon-o-arrow-right-start-on-rectangle', 'w-5 h-5') Logout</div></x-ui-button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Matrix Tab --}}
        <div class="mt-2" x-show="tab === 'matrix'" x-cloak>
        {{-- Leere Matrix-Seite --}}
        <div class="flex flex-col justify-center items-center h-64">
            @if($showMatrix)
                <div class="overflow-auto">
                    <table class="min-w-full border bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b text-left">User</th>
                                @foreach($matrixModules as $module)
                                    <th class="py-2 px-4 border-b text-center">
                                        {{ $module->title ?? 'Modul' }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrixUsers as $user)
                                <tr>
                                    <td class="py-2 px-4 border-b font-medium">{{ $user->name }}</td>
                                    @foreach($matrixModules as $module)
                                        @php
                                            $hasModule = in_array($module->id, $userModuleMap[$user->id] ?? []);
                                            $variant = $hasModule ? 'success-outline' : 'danger-outline';
                                        @endphp
                                        <td class="py-2 px-4 border-b text-center">
                                            <x-ui-button
                                                :variant="$variant"
                                                size="sm"
                                                wire:click="toggleMatrix({{ $user->id }}, {{ $module->id }})"
                                            >
                                                @if($hasModule)
                                                    @svg('heroicon-o-hand-thumb-up', 'w-4 h-4 text-success')
                                                @else
                                                    @svg('heroicon-o-hand-thumb-down', 'w-4 h-4 text-danger')
                                                @endif
                                            </x-ui-button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
            @endif
        </div>

        <x-slot name="footer">
            <div class="flex justify-start">
                @if(auth()->user()->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                    <button
                        wire:click="$toggle('showMatrix')"
                        class="px-4 py-2 rounded bg-primary text-white hover:bg-primary-700 transition"
                    >
                        @if($showMatrix)
                            Zurück zur Modulauswahl
                        @else
                            Modul-Matrix anzeigen
                        @endif
                    </button>
                @endif
            </div>
        </x-slot>
    </div>
</x-ui-modal>