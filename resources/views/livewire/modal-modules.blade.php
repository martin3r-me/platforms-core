<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        <div x-data="{ tab: 'modules' }" x-init="
            window.addEventListener('open-modal-modules', (e) => {
                tab = e?.detail?.tab || 'modules';
            });
        " class="w-full">
            <div class="d-flex items-center justify-between w-full">
                <div class="font-medium">Zentrale Steuerung</div>
                <div class="text-xs text-gray-500">Drücke ⌘K / M zum Öffnen</div>
            </div>

            <div class="d-flex gap-3 mt-3 border-b pb-1">
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer"
                        :class="{ 'font-bold border-b-2 border-primary' : tab === 'modules' }"
                        @click="tab = 'modules'">
                    Module
                </button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer"
                        :class="{ 'font-bold border-b-2 border-primary' : tab === 'team' }"
                        @click="tab = 'team'">
                    Team
                </button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer"
                        :class="{ 'font-bold border-b-2 border-primary' : tab === 'billing' }"
                        @click="tab = 'billing'">
                    Abrechnung
                </button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer"
                        :class="{ 'font-bold border-b-2 border-primary' : tab === 'account' }"
                        @click="tab = 'account'">
                    Konto
                </button>
                @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer ml-auto"
                        :class="{ 'font-bold border-b-2 border-primary' : tab === 'matrix' }"
                        @click="tab = 'matrix'">
                    Matrix
                </button>
                @endif
            </div>

            <div class="mt-4" x-show="tab === 'modules'" x-cloak>
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

            {{-- Team Tab (delegiert aktuell auf bestehendes Modal) --}}
            <div class="mt-4" x-show="tab === 'team'" x-cloak>
                <div class="p-4 bg-muted-5 rounded border">
                    <div class="d-flex items-center justify-between">
                        <div>
                            <div class="font-medium">Team verwalten</div>
                            <div class="text-sm text-gray-600">Öffnet die Team-Verwaltung im selben Dialog künftig. Bis dahin: eigenes Modal.</div>
                        </div>
                        <x-ui-button variant="secondary-outline" @click="$dispatch('open-modal-team')">
                            Öffnen
                        </x-ui-button>
                    </div>
                </div>
            </div>

            {{-- Billing Tab (delegiert auf bestehendes Modal) --}}
            <div class="mt-4" x-show="tab === 'billing'" x-cloak>
                <div class="p-4 bg-muted-5 rounded border">
                    <div class="d-flex items-center justify-between">
                        <div>
                            <div class="font-medium">Abrechnung & Kosten</div>
                            <div class="text-sm text-gray-600">Kostenübersicht und Zahlungsmethoden.</div>
                        </div>
                        <x-ui-button variant="secondary-outline" @click="$dispatch('open-modal-pricing')">
                            Öffnen
                        </x-ui-button>
                    </div>
                </div>
            </div>

            {{-- Account Tab (delegiert auf bestehendes Modal) --}}
            <div class="mt-4" x-show="tab === 'account'" x-cloak>
                <div class="p-4 bg-muted-5 rounded border">
                    <div class="d-flex items-center justify-between">
                        <div>
                            <div class="font-medium">Benutzerkonto</div>
                            <div class="text-sm text-gray-600">Profil und schnelle Aktionen.</div>
                        </div>
                        <x-ui-button variant="secondary-outline" @click="$dispatch('open-modal-user')">
                            Öffnen
                        </x-ui-button>
                    </div>
                </div>
            </div>

            {{-- Matrix Tab (bestehende Matrix-Ansicht) --}}
            <div class="mt-4" x-show="tab === 'matrix'" x-cloak>
                @if(true)
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
        </div>
    </x-slot>

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