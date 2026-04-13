<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Modul-Matrix" icon="heroicon-o-table-cells" />
    </x-slot>

    <x-ui-page-container>
        <div class="p-4">
            @if(!empty($matrixUsers) && !empty($matrixModules))
                <div class="overflow-auto rounded-lg border border-[var(--ui-border)]/60 max-h-[calc(100vh-8rem)]">
                    <table class="border-collapse">
                        {{-- Header: sticky top + module names vertical --}}
                        <thead>
                            <tr>
                                <th class="sticky top-0 left-0 z-30 bg-[var(--ui-surface)] border-b border-r border-[var(--ui-border)]/60 px-3 py-2 text-left text-[11px] font-semibold text-[var(--ui-muted)] uppercase tracking-wider w-44 min-w-[11rem]">
                                    User
                                </th>
                                @foreach($matrixModules as $module)
                                    <th class="sticky top-0 z-20 bg-[var(--ui-surface)] border-b border-[var(--ui-border)]/60 px-1 pt-2 pb-2 text-center min-w-[2.5rem] w-10">
                                        <div class="flex justify-center">
                                            <span class="writing-mode-vertical text-[10px] font-medium text-[var(--ui-muted)] whitespace-nowrap [writing-mode:vertical-lr] rotate-180 max-h-24 overflow-hidden text-ellipsis">
                                                {{ $module->title ?? $module->key }}
                                            </span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrixUsers as $matrixUser)
                                <tr class="group hover:bg-[var(--ui-primary-5)]/40 transition-colors">
                                    {{-- User name: sticky left --}}
                                    <td class="sticky left-0 z-10 bg-[var(--ui-surface)] group-hover:bg-[var(--ui-primary-5)]/40 border-b border-r border-[var(--ui-border)]/30 px-3 py-1.5 text-xs font-medium text-[var(--ui-secondary)] truncate max-w-[11rem]">
                                        {{ $matrixUser->name }}
                                    </td>
                                    @foreach($matrixModules as $module)
                                        @php
                                            $hasModule = in_array($module->id, $userModuleMap[$matrixUser->id] ?? []);
                                            $isParentModule = $module->isRootScoped();
                                            $isReadOnly = $isParentModule && !$isRootTeam;
                                        @endphp
                                        <td class="border-b border-[var(--ui-border)]/30 px-1 py-1.5 text-center">
                                            @if($isReadOnly)
                                                <div class="flex justify-center" title="Nur im Root-Team veränderbar">
                                                    <span class="block w-3.5 h-3.5 rounded-full border {{ $hasModule ? 'bg-emerald-500/40 border-emerald-500/60' : 'bg-transparent border-[var(--ui-border)]/40' }} opacity-50 cursor-not-allowed"></span>
                                                </div>
                                            @else
                                                <button wire:click="toggleMatrix({{ $matrixUser->id }}, {{ $module->id }})" class="flex justify-center w-full group/dot">
                                                    <span class="block w-3.5 h-3.5 rounded-full border transition-all duration-150
                                                        {{ $hasModule
                                                            ? 'bg-emerald-500 border-emerald-600 shadow-[0_0_4px_rgba(16,185,129,0.4)] group-hover/dot:bg-emerald-400'
                                                            : 'bg-transparent border-[var(--ui-border)]/60 group-hover/dot:border-emerald-400 group-hover/dot:bg-emerald-500/20'
                                                        }}"></span>
                                                </button>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex items-center gap-4 text-[10px] text-[var(--ui-muted)]">
                    <span class="flex items-center gap-1.5"><span class="block w-2.5 h-2.5 rounded-full bg-emerald-500 border border-emerald-600"></span> Zugriff</span>
                    <span class="flex items-center gap-1.5"><span class="block w-2.5 h-2.5 rounded-full border border-[var(--ui-border)]/60"></span> Kein Zugriff</span>
                    <span class="flex items-center gap-1.5"><span class="block w-2.5 h-2.5 rounded-full border border-emerald-500/60 bg-emerald-500/40 opacity-50"></span> Root-Team (read-only)</span>
                </div>
            @else
                <div class="text-sm text-[var(--ui-muted)] p-6 text-center bg-[var(--ui-muted-5)] rounded-lg">Matrix-Daten nicht verfügbar.</div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
