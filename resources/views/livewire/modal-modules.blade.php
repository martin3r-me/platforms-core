<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        Module wechseln
    </x-slot>

    @if(!$showMatrix)
        <div x-data="{
                query: '',
                view: 'grid', // 'grid' | 'list'
                normalize(s){ return (s||'').toString().toLowerCase(); },
                matches(el){
                    if(!this.query) return true;
                    const q = this.normalize(this.query);
                    const hay = this.normalize(el.dataset.search || '');
                    return hay.includes(q);
                }
            }"
            class="space-y-4"
        >
            <div class="d-flex items-center gap-2">
                <div class="flex-grow-1">
                    <input
                        x-model.debounce.200ms="query"
                        type="text"
                        placeholder="Module suchen..."
                        class="w-full px-3 py-2 border rounded"
                        aria-label="Module suchen"
                    >
                </div>
                <div class="flex-shrink-0 d-flex items-center gap-1" role="group" aria-label="Ansicht umschalten">
                    <button type="button"
                            @click="view='grid'"
                            :class="view==='grid' ? 'bg-primary text-white' : 'bg-white text-secondary'"
                            class="px-3 py-2 rounded border">
                        Grid
                    </button>
                    <button type="button"
                            @click="view='list'"
                            :class="view==='list' ? 'bg-primary text-white' : 'bg-white text-secondary'"
                            class="px-3 py-2 rounded border">
                        Liste
                    </button>
                </div>
            </div>

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

            <div
                class="mt-2"
                :class="view==='grid' ? 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3' : 'flex flex-col gap-2'"
            >
                @foreach($modules as $module)
                    @php
                        $title = $module['title'] ?? $module['label'] ?? 'Modul';
                        $icon  = $module['icon'] ?? null;
                        $routeName = $module['navigation']['route'] ?? null;
                        $finalUrl = $routeName ? route($routeName) : ($module['url'] ?? '#');
                    @endphp

                    <a href="{{ $finalUrl }}"
                       x-show="matches($el)"
                       x-bind:data-search="'{{ Str::of($title.' '.($routeName ?? '').' '.($finalUrl ?? ''))->lower() }}'"
                       class="d-flex items-center gap-3 p-3 rounded-md border border-solid border-1 transition hover:border-primary hover:bg-primary-10"
                    >
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