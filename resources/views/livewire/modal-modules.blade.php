<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        Module wechseln
    </x-slot>

    @if(!$showMatrix)
        <div class="grid grid-cols-2 gap-4">
            @foreach($modules as $module)
                @php
                    $routeName = $module['navigation']['route'] ?? null;
                    $finalUrl = $routeName
                        ? route($routeName)
                        : ($module['url'] ?? '#');
                @endphp

                <a href="{{ $finalUrl }}"
                   class="d-flex items-center gap-2 p-4 rounded-md border border-solid border-1 transition hover:border-primary hover:bg-primary-10">

                    <div class="text-xs text-gray-500">
                        <strong>Route Name:</strong> {{ $routeName ?? 'NULL' }}<br>
                        <strong>Final URL:</strong> {{ $finalUrl }}
                    </div>

                    @if(!empty($module['icon']))
                        <x-dynamic-component :component="$module['icon']" class="w-5 h-5 text-primary" />
                    @else
                        <x-heroicon-o-cube class="w-5 h-5 text-primary" />
                    @endif

                    <span class="font-medium text-secondary">
                        {{ $module['title'] ?? $module['label'] ?? 'Modul' }}
                    </span>
                </a>
            @endforeach
        </div>
    @else
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

    <x-slot name="footer">
        <div class="flex justify-start">
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
        </div>
    </x-slot>
</x-ui-modal>