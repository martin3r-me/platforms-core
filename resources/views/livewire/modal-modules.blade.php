<div x-data="{ tab: 'modules' }" x-init="
    window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });
">
<x-ui-modal size="lg" wire:model="modalShow">
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
        
        {{-- Tabs: Inhalte --}}
        {{-- Module --}}
        <div class="mt-2" x-show="tab === 'modules'" x-cloak>
            @php($availableModules = \\Platform\\Core\\PlatformCore::getModules())
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($availableModules as $key => $module)
                        @php
                            $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                            $icon  = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
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
        </div>

        {{-- Matrix --}}
        <div class="mt-2" x-show="tab === 'matrix'" x-cloak>
            @if(!empty($matrixUsers) && !empty($matrixModules))
                <div class="overflow-auto">
                    <table class="min-w-full border bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b text-left">User</th>
                                @foreach($matrixModules as $module)
                                    <th class="py-2 px-4 border-b text-center">{{ $module->title ?? 'Modul' }}</th>
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
                                            <x-ui-button :variant="$variant" size="sm" wire:click="toggleMatrix({{ $user->id }}, {{ $module->id }})">
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
            @else
                <div class="text-sm text-gray-600 p-4">Matrix-Daten nicht verfügbar.</div>
            @endif
        </div>

        {{-- Account --}}
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

        {{-- Billing --}}
        <div class="mt-2" x-show="tab === 'billing'" x-cloak>
            <div class="p-4">
                <h2 class="text-lg font-semibold mb-2">Kostenübersicht für diesen Monat</h2>
                @if(!empty($monthlyUsages) && count($monthlyUsages))
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
                                    <td class="px-2 py-1">{{ \\Illuminate\\Support\\Carbon::parse($usage->usage_date)->format('d.m.Y') }}</td>
                                    <td class="px-2 py-1">{{ $usage->label }}</td>
                                    <td class="px-2 py-1">{{ $usage->billable_type }}</td>
                                    <td class="px-2 py-1 text-right">{{ $usage->count }}</td>
                                    <td class="px-2 py-1 text-right">{{ number_format($usage->cost_per_unit, 4, ',', '.') }} €</td>
                                    <td class="px-2 py-1 text-right">{{ number_format($usage->total_cost, 2, ',', '.') }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-4 font-bold text-right">Monatssumme: {{ number_format((float)($monthlyTotal ?? 0), 2, ',', '.') }} €</div>
                @else
                    <div class="text-gray-500 text-sm py-4">Für diesen Monat liegen noch keine Nutzungsdaten vor.</div>
                @endif
            </div>
        </div>

        {{-- Team --}}
        <div class="mt-2" x-show="tab === 'team'" x-cloak>
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold mb-3">Aktuelles Team wechseln</h3>
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
                        <div class="text-sm text-gray-600">Nur ein Team vorhanden. Lege unten ein neues Team an.</div>
                    @endif
                </div>

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

                @isset($team)
                <div>
                    <h3 class="text-lg font-semibold mb-3">Team-Mitglieder</h3>
                    <div class="space-y-3">
                        @foreach($team->users ?? [] as $member)
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
                                    @if(($team->user_id ?? null) === auth()->id() && ($member->id ?? null) !== auth()->id())
                                        <select name="member_role_{{ $member->id }}" class="min-w-[9rem] px-2 py-1 border rounded" wire:change="updateMemberRole({{ $member->id }}, $event.target.value)">
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

                @if(isset($team) && !($team->personal_team ?? true))
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
            </div>
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
</x-ui-modal>
</div>