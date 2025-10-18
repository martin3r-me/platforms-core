<div x-data="{ moduleFlyoutOpen: false }" 
     @open-module-flyout.window="moduleFlyoutOpen = true"
     @click.away="moduleFlyoutOpen = false"
     class="relative hidden sm:block">
    
    <button @click="moduleFlyoutOpen = !moduleFlyoutOpen" 
        class="inline-flex items-center gap-1 px-3 py-1.5 h-8 rounded-md border transition
        text-[var(--ui-primary)] bg-[var(--ui-primary-5)] border-[var(--ui-primary)]/60
        hover:text-[var(--ui-muted)] hover:bg-transparent hover:border-[var(--ui-border)]/60"
        title="Modul wechseln">
        <span class="truncate max-w-[8rem]">{{ $currentModule }}</span>
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>
    
    <div x-show="moduleFlyoutOpen" x-cloak x-transition
        class="absolute top-full left-0 mt-2 w-80 bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 shadow-lg z-50">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-[var(--ui-muted)] mb-3">Module</h3>
            <div class="space-y-2">
                {{-- Dashboard --}}
                <a href="{{ route('platform.dashboard') }}"
                    class="w-full group flex items-center gap-3 p-3 rounded-lg transition hover:bg-[var(--ui-muted-5)]">
                    <div class="flex-shrink-0">
                        @svg('heroicon-o-home', 'w-5 h-5 text-[var(--ui-primary)]')
                    </div>
                    <div class="min-w-0 flex-1 text-left">
                        <div class="font-medium text-[var(--ui-secondary)]">Haupt-Dashboard</div>
                        <div class="text-xs text-[var(--ui-muted)]">Übersicht & Start</div>
                    </div>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)]')
                </a>

                @foreach($modules as $key => $module)
                    @php
                        $title = $module['title'] ?? $module['label'] ?? ucfirst($key);
                        $icon = $module['navigation']['icon'] ?? ($module['icon'] ?? null);
                        $routeName = $module['navigation']['route'] ?? null;
                        $finalUrl = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
                            ? route($routeName)
                            : ($module['url'] ?? '#');
                    @endphp
                    <a href="{{ $finalUrl }}"
                        class="w-full group flex items-center gap-3 p-3 rounded-lg transition hover:bg-[var(--ui-muted-5)]">
                        <div class="flex-shrink-0">
                            @if(!empty($icon))
                                <x-dynamic-component :component="$icon" class="w-5 h-5 text-[var(--ui-primary)]" />
                            @else
                                @svg('heroicon-o-cube', 'w-5 h-5 text-[var(--ui-primary)]')
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 text-left">
                            <div class="font-medium text-[var(--ui-secondary)]">{{ $title }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ $module['description'] ?? 'Modul' }}</div>
                        </div>
                        @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)]')
                    </a>
                @endforeach
            </div>
            <div class="mt-3 pt-3 border-t border-[var(--ui-border)]/60">
                <button type="button" wire:click="openModal" 
                    class="w-full flex items-center justify-center gap-2 p-2 text-sm font-medium text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition">
                    Alle Module anzeigen
                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                </button>
            </div>
        </div>
    </div>
</div>
