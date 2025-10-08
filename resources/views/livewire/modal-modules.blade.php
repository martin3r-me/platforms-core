<div x-data="{ tab: 'modules' }" x-init="
    window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });
">
<x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Zentrale Steuerung</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">⌘K / M</span>
            </div>
        </div>
        <div class="flex gap-1 mt-4 border-b border-[var(--ui-border)]/60">
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : tab === 'modules', 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' : tab !== 'modules' }" @click="tab = 'modules'">Module</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : tab === 'team', 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' : tab !== 'team' }" @click="tab = 'team'">Team</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : tab === 'billing', 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' : tab !== 'billing' }" @click="tab = 'billing'">Abrechnung</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : tab === 'account', 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' : tab !== 'account' }" @click="tab = 'account'">Konto</button>
            @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors ml-auto" :class="{ 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] bg-[var(--ui-primary-5)]' : tab === 'matrix', 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' : tab !== 'matrix' }" @click="tab = 'matrix'">Matrix</button>
            @endif
        </div>
    </x-slot>
        
        {{-- Tabs: Inhalte --}}
        {{-- Module --}}
        <div class="mt-6" x-show="tab === 'modules'" x-cloak>
            @php
                $availableModules = $modules ?? [];
            @endphp
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @foreach($availableModules as $key => $module)
                        @php
                            $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                            $icon  = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                            $routeName = $module['navigation']['route'] ?? null;
                            $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                ? route($routeName)
                                : ($module['url'] ?? '#');
                        @endphp
                    <a href="{{ $finalUrl }}" class="group flex items-center gap-4 p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-all duration-200">
                        <div class="flex-shrink-0">
                            @if(!empty($icon))
                                <x-dynamic-component :component="$icon" class="w-8 h-8 text-[var(--ui-primary)] group-hover:scale-110 transition-transform" />
                            @else
                                @svg('heroicon-o-cube', 'w-8 h-8 text-[var(--ui-primary)] group-hover:scale-110 transition-transform')
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-[var(--ui-secondary)] truncate">{{ $title }}</div>
                            <div class="text-xs text-[var(--ui-muted)] truncate">
                                {{ $routeName ? $routeName : ($finalUrl ?? '') }}
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Matrix --}}
        <div class="mt-6" x-show="tab === 'matrix'" x-cloak>
            @if(!empty($matrixUsers) && !empty($matrixModules))
                <div class="overflow-auto rounded-lg border border-[var(--ui-border)]/60">
                    <table class="min-w-full bg-[var(--ui-surface)]">
                        <thead class="bg-[var(--ui-muted-5)]">
                            <tr>
                                <th class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-left font-semibold text-[var(--ui-secondary)]">User</th>
                                @foreach($matrixModules as $module)
                                    <th class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-center font-semibold text-[var(--ui-secondary)]">{{ $module->title ?? 'Modul' }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrixUsers as $user)
                                <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                    <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 font-medium text-[var(--ui-secondary)]">{{ $user->name }}</td>
                                    @foreach($matrixModules as $module)
                                        @php
                                            $hasModule = in_array($module->id, $userModuleMap[$user->id] ?? []);
                                            $variant = $hasModule ? 'success-outline' : 'danger-outline';
                                        @endphp
                                        <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-center">
                                            <x-ui-button :variant="$variant" size="sm" wire:click="toggleMatrix({{ $user->id }}, {{ $module->id }})">
                                                @if($hasModule)
                                                    @svg('heroicon-o-hand-thumb-up', 'w-4 h-4 text-[var(--ui-success)]')
                                                @else
                                                    @svg('heroicon-o-hand-thumb-down', 'w-4 h-4 text-[var(--ui-danger)]')
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
                <div class="text-sm text-[var(--ui-muted)] p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">Matrix-Daten nicht verfügbar.</div>
            @endif
        </div>

        {{-- Account --}}
        <div class="mt-6" x-show="tab === 'account'" x-cloak>
            <div class="space-y-6">
                <div class="flex items-center gap-4 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                    <div class="w-12 h-12 rounded-full bg-[var(--ui-primary)] text-[var(--ui-on-primary)] flex items-center justify-center">
                        <span class="font-semibold text-lg">{{ strtoupper(mb_substr((auth()->user()->fullname ?? auth()->user()->name ?? 'U'), 0, 2)) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-[var(--ui-secondary)] truncate">{{ auth()->user()->fullname ?? auth()->user()->name }}</div>
                        <div class="text-sm text-[var(--ui-muted)] truncate">{{ auth()->user()->email }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <x-ui-button variant="secondary-outline" @click="tab = 'team'" class="w-full">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-5 h-5')
                            Team verwalten
                        </div>
                    </x-ui-button>
                    <x-ui-button variant="secondary-outline" @click="tab = 'billing'" class="w-full">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-banknotes', 'w-5 h-5')
                            Abrechnung & Kosten
                        </div>
                    </x-ui-button>
                    <x-ui-button variant="secondary-outline" @click="tab = 'modules'" class="w-full">
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-squares-2x2', 'w-5 h-5')
                            Module wechseln
                        </div>
                    </x-ui-button>
                </div>

                <div class="flex items-center justify-between w-full pt-4 border-t border-[var(--ui-border)]/60">
                    <div class="text-xs text-[var(--ui-muted)]">Angemeldet als {{ auth()->user()->email }}</div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">@csrf
                        <x-ui-button variant="danger" type="submit">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-arrow-right-start-on-rectangle', 'w-5 h-5')
                                Logout
                            </div>
                        </x-ui-button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Billing --}}
        <div class="mt-6" x-show="tab === 'billing'" x-cloak>
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Kostenübersicht für diesen Monat</h2>
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
        </div>

        {{-- Team --}}
        <div class="mt-6" x-show="tab === 'team'" x-cloak>
            <div class="space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Aktuelles Team wechseln</h3>
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
                        <div class="text-sm text-[var(--ui-muted)] p-4 bg-[var(--ui-muted-5)] rounded-lg">Nur ein Team vorhanden. Lege unten ein neues Team an.</div>
                    @endif
                </div>

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
                        <x-ui-button type="submit">Team erstellen</x-ui-button>
                    </form>
                </div>

                @isset($team)
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Team-Mitglieder</h3>
                    <div class="space-y-3">
                        @foreach($team->users ?? [] as $member)
                            <div class="flex items-center justify-between p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[var(--ui-primary)] text-[var(--ui-on-primary)] rounded-full flex items-center justify-center">
                                        <span class="text-sm font-semibold">{{ strtoupper(mb_substr(($member->fullname ?? $member->name), 0, 2)) }}</span>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-[var(--ui-secondary)]">{{ $member->fullname ?? $member->name }}</div>
                                        <div class="text-sm text-[var(--ui-muted)]">{{ $member->email }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if(($team->user_id ?? null) === auth()->id() && ($member->id ?? null) !== auth()->id())
                                        <select name="member_role_{{ $member->id }}" class="min-w-[9rem] px-3 py-2 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-[var(--ui-primary)]" wire:change="updateMemberRole({{ $member->id }}, $event.target.value)">
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
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Mitglied einladen</h3>
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
                <x-ui-button
                    wire:click="$toggle('showMatrix')"
                    variant="primary"
                >
                    @if($showMatrix)
                        Zurück zur Modulauswahl
                    @else
                        Modul-Matrix anzeigen
                    @endif
                </x-ui-button>
            @endif
        </div>
    </x-slot>
</x-ui-modal>
</div>