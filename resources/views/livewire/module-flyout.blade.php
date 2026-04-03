<div x-data="{ moduleFlyoutOpen: false, search: '' }"
     @open-module-flyout.window="moduleFlyoutOpen = true; $nextTick(() => $refs.moduleSearch?.focus())"
     @click.away="moduleFlyoutOpen = false; search = ''"
     class="relative hidden sm:block">

    <button @click="moduleFlyoutOpen = !moduleFlyoutOpen"
        class="inline-flex items-center gap-1 px-2 py-1 h-7 rounded-md border transition text-xs
        text-[var(--ui-primary)] bg-[var(--ui-primary-5)] border-[var(--ui-primary)]/60"
        title="Modul wechseln">
        <span class="truncate max-w-[12rem]">{{ $currentModule }}</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>

    <div x-show="moduleFlyoutOpen" x-cloak x-transition
        class="absolute top-full right-0 mt-2 w-80 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 shadow-lg z-50 max-h-[80vh] overflow-y-auto">
        <div class="p-2">
            {{-- Suchfeld --}}
            <div class="px-2 mb-2">
                <input x-ref="moduleSearch" x-model="search" type="text" placeholder="Modul suchen..."
                    class="w-full px-2 py-1 text-xs rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:border-[var(--ui-primary)]/60"
                    @keydown.escape="moduleFlyoutOpen = false; search = ''" />
            </div>

            {{-- Dashboard --}}
            <a href="{{ route('platform.dashboard') }}"
                x-show="!search || 'dashboard haupt-dashboard'.includes(search.toLowerCase())"
                class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[var(--ui-muted-5)]">
                <div class="flex-shrink-0">
                    @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-primary)]')
                </div>
                <div class="min-w-0 flex-1 text-left">
                    <div class="font-medium text-[var(--ui-secondary)] text-xs truncate">Haupt-Dashboard</div>
                </div>
            </a>

            {{-- Gruppierte Module --}}
            @foreach($groupedModules as $groupKey => $group)
                <div x-data="{ hasVisible: false }"
                     x-effect="hasVisible = !search || Array.from($el.querySelectorAll('[data-module-title]')).some(el => el.dataset.moduleTitle.toLowerCase().includes(search.toLowerCase()))">
                    <h3 x-show="hasVisible"
                        class="text-[0.625rem] font-semibold text-[var(--ui-muted)] mt-3 mb-1 px-2 uppercase tracking-wider">
                        {{ $group['label'] }}
                    </h3>
                    <div class="space-y-0.5">
                        @foreach($group['modules'] as $module)
                            @php
                                $title = $module['title'] ?? $module['label'] ?? ucfirst($module['key'] ?? '');
                                $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                                $routeName = $module['navigation']['route'] ?? null;
                                $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                                    ? route($routeName)
                                    : ($module['url'] ?? '#');
                            @endphp
                            <a href="{{ $finalUrl }}"
                                data-module-title="{{ $title }}"
                                x-show="!search || '{{ strtolower($title) }}'.includes(search.toLowerCase())"
                                class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[var(--ui-muted-5)]">
                                <div class="flex-shrink-0">
                                    @if(!empty($icon))
                                        <x-dynamic-component :component="$icon" class="w-4 h-4 text-[var(--ui-primary)]" />
                                    @else
                                        @svg('heroicon-o-cube', 'w-4 h-4 text-[var(--ui-primary)]')
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 text-left">
                                    <div class="font-medium text-[var(--ui-secondary)] text-xs truncate">{{ $title }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
