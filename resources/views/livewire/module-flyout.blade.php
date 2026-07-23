<div x-data="{ moduleFlyoutOpen: false, search: '' }"
     @open-module-flyout.window="moduleFlyoutOpen = true"
     class="relative">

    <button x-ref="trigger" @click="moduleFlyoutOpen = !moduleFlyoutOpen"
        class="inline-flex h-7 items-center gap-1.5 rounded-[6px] border border-[color:var(--nx-line-strong)] px-2 text-xs text-[color:var(--nx-text)] transition-colors hover:bg-[color:var(--nx-hover)]"
        title="Modul wechseln">
        @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5 text-[color:var(--nx-muted)]')
        <span class="max-w-[10rem] truncate">{{ $currentModule }}</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3 text-[color:var(--nx-muted)]">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>

    <template x-teleport="body">
        <div x-show="moduleFlyoutOpen" x-cloak x-transition x-ref="panel"
            @click.outside="moduleFlyoutOpen = false; search = ''"
            @keydown.escape.window="moduleFlyoutOpen = false; search = ''"
            x-effect="if(moduleFlyoutOpen){ $nextTick(() => { const r = $refs.trigger.getBoundingClientRect(); $el.style.top = (r.bottom + 8) + 'px'; $el.style.right = (window.innerWidth - r.right) + 'px'; $refs.moduleSearch?.focus(); }) }"
            class="fixed z-[99] max-h-[80vh] w-80 overflow-y-auto rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-2 shadow-[var(--nx-shadow-pop)]">

            {{-- Suche: Cursor liegt hier; Enter öffnet den ersten sichtbaren Treffer --}}
            <div class="mb-2 px-1">
                <input x-ref="moduleSearch" x-model="search" type="text" placeholder="Modul suchen…"
                    @keydown.enter.prevent="Array.from($refs.panel.querySelectorAll('a.mod-item')).find(a => a.offsetParent !== null)?.click()"
                    class="w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2.5 py-1.5 text-sm text-[color:var(--nx-text)] placeholder-[color:var(--nx-faint)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]" />
            </div>

            {{-- Dashboard --}}
            <a href="{{ route('platform.dashboard') }}"
                x-show="!search || 'dashboard haupt-dashboard'.includes(search.toLowerCase())"
                class="mod-item flex items-center gap-2.5 rounded-[6px] px-2 py-1.5 text-sm text-[color:var(--nx-text)] transition-colors hover:bg-[color:var(--nx-hover)]">
                @svg('heroicon-o-home', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                <span class="truncate">Haupt-Dashboard</span>
            </a>

            {{-- Gruppierte Module --}}
            @foreach($groupedModules as $groupKey => $group)
                <div x-data="{ hasVisible: false }"
                     x-effect="hasVisible = !search || Array.from($el.querySelectorAll('[data-module-title]')).some(el => el.dataset.moduleTitle.toLowerCase().includes(search.toLowerCase()))">
                    <h3 x-show="hasVisible" class="mb-1 mt-3 px-2 text-[11px] font-medium text-[color:var(--nx-faint)]">
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
                                $isCurrent = $currentModule === $title;
                            @endphp
                            <a href="{{ $finalUrl }}"
                                data-module-title="{{ $title }}"
                                @click="moduleFlyoutOpen = false; search = ''"
                                x-show="!search || '{{ strtolower($title) }}'.includes(search.toLowerCase())"
                                class="mod-item flex items-center gap-2.5 rounded-[6px] px-2 py-1.5 text-sm transition-colors hover:bg-[color:var(--nx-hover)] {{ $isCurrent ? 'bg-[color:var(--nx-accent-soft)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-text)]' }}">
                                @if(!empty($icon))
                                    <x-dynamic-component :component="$icon" class="w-4 h-4 shrink-0 text-[color:var(--nx-muted)]" />
                                @else
                                    @svg('heroicon-o-cube', 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                                @endif
                                <span class="truncate">{{ $title }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </template>
</div>
