<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Modul-Matrix" icon="heroicon-o-table-cells" />
    </x-slot>

    <x-ui-page-container>
        <div class="p-6">
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
                            @foreach($matrixUsers as $matrixUser)
                                <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                    <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 font-medium text-[var(--ui-secondary)]">{{ $matrixUser->name }}</td>
                                    @foreach($matrixModules as $module)
                                        @php
                                            $hasModule = in_array($module->id, $userModuleMap[$matrixUser->id] ?? []);
                                            $isParentModule = $module->isRootScoped();
                                            $isReadOnly = $isParentModule && !$isRootTeam;
                                        @endphp
                                        <td class="py-3 px-4 border-b border-[var(--ui-border)]/60 text-center">
                                            @if($isReadOnly)
                                                <div class="inline-flex items-center justify-center w-8 h-8 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] cursor-not-allowed opacity-50" title="Nur im Root-Team veränderbar">
                                                    @if($hasModule)
                                                        @svg('heroicon-o-check', 'w-4 h-4 text-[var(--ui-secondary)]')
                                                    @else
                                                        @svg('heroicon-o-minus', 'w-4 h-4 text-[var(--ui-muted)]')
                                                    @endif
                                                </div>
                                            @else
                                                <x-ui-button variant="secondary-outline" size="sm" wire:click="toggleMatrix({{ $matrixUser->id }}, {{ $module->id }})">
                                                    @if($hasModule)
                                                        @svg('heroicon-o-check', 'w-4 h-4 text-[var(--ui-secondary)]')
                                                    @else
                                                        @svg('heroicon-o-minus', 'w-4 h-4 text-[var(--ui-muted)]')
                                                    @endif
                                                </x-ui-button>
                                            @endif
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
    </x-ui-page-container>
</x-ui-page>
