<nav x-data="{ mobileMenuOpen: false }"
     class="w-full h-12 border-b border-[var(--ui-border)]/60 bg-[var(--ui-surface)] flex items-center px-3 gap-2 shrink-0">

    {{-- ═══ ZONE 1: Logo + Favorites (left) ═══ --}}
    <div class="flex items-center gap-1 min-w-0">
        {{-- Logo --}}
        <a href="{{ route('platform.dashboard') }}" class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-md hover:bg-[var(--ui-muted-5)] transition" title="Dashboard">
            <img src="/logo.png" alt="Home" class="h-5 w-5 rounded object-contain" />
        </a>

        {{-- Mobile hamburger --}}
        <button @click="mobileMenuOpen = !mobileMenuOpen"
            class="md:hidden flex items-center justify-center w-8 h-8 rounded-md hover:bg-[var(--ui-muted-5)] transition text-[var(--ui-secondary)]">
            <svg x-show="!mobileMenuOpen" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <svg x-show="mobileMenuOpen" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>

        {{-- Favorites (desktop/tablet) --}}
        <div class="hidden md:flex items-center gap-0.5 ml-1">
            @foreach($favorites as $fav)
                <a href="{{ $fav['url'] }}"
                    class="inline-flex items-center gap-1.5 px-2 py-1 h-7 rounded-md text-xs transition
                    {{ $currentModuleKey === $fav['key']
                        ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-medium'
                        : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}"
                    title="{{ $fav['title'] }}">
                    @if(!empty($fav['icon']))
                        <x-dynamic-component :component="$fav['icon']" class="w-4 h-4 flex-shrink-0" />
                    @else
                        @svg('heroicon-o-cube', 'w-4 h-4 flex-shrink-0')
                    @endif
                    <span class="hidden lg:inline truncate max-w-[6rem]">{{ $fav['title'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ═══ Spacer ═══ --}}
    <div class="flex-1"></div>

    {{-- ═══ ZONE 2: Module + Admin Switcher (center-right, desktop only) ═══ --}}
    <div class="hidden md:flex items-center gap-1">
        @livewire('core.module-flyout')

        @if($isAdmin)
            @livewire('core.admin-flyout')
        @endif
    </div>

    {{-- ═══ ZONE 3: Actions + Team + User (right) ═══ --}}
    <div class="flex items-center gap-1">
        {{-- Action buttons (desktop/tablet) --}}
        <div class="hidden md:flex items-center gap-1">
            {{-- Left Sidebar Toggle --}}
            <button x-data
                @click="Alpine.store('page') && (Alpine.store('page').sidebarOpen = !Alpine.store('page').sidebarOpen)"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition"
                :class="Alpine.store('page')?.sidebarOpen
                    ? 'text-[var(--ui-primary)] bg-[var(--ui-muted-5)]'
                    : 'text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]'"
                title="Linke Sidebar umschalten">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                    <rect x="3" y="5" width="8" height="14" rx="2" class="opacity-90" />
                    <rect x="11" y="5" width="10" height="14" rx="2" class="opacity-40" />
                </svg>
            </button>

            {{-- Check-in --}}
            <button x-data
                @click="$dispatch('open-modal-checkin')"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]"
                title="Täglicher Check-in">
                @svg('heroicon-o-sun', 'w-4 h-4')
            </button>

            {{-- Comms --}}
            <button x-data
                @click="$dispatch('open-modal-comms')"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]"
                title="Kommunikation">
                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
            </button>

            {{-- Help --}}
            @php
                $routeName = request()->route()?->getName();
                $routeModule = is_string($routeName) && str_contains($routeName, '.') ? strstr($routeName, '.', true) : null;
            @endphp
            <button x-data
                @click="$dispatch('open-help', { module: @js($routeModule) })"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]"
                title="Hilfe">
                @svg('heroicon-o-question-mark-circle', 'w-4 h-4')
            </button>

            {{-- Playground --}}
            <button x-data
                @click="$dispatch('playground:open', { context: { source_route: @js($routeName), source_module: @js($routeModule), source_url: window.location.href } })"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]"
                title="Playground öffnen">
                @svg('heroicon-o-sparkles', 'w-4 h-4')
            </button>

            {{-- Terminal Toggle --}}
            <button x-data
                @click="window.dispatchEvent(new CustomEvent('toggle-terminal'))"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition"
                :class="Alpine.store('page')?.terminalOpen
                    ? 'text-[var(--ui-primary)] bg-[var(--ui-muted-5)]'
                    : 'text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]'"
                title="Terminal umschalten">
                @svg('heroicon-o-command-line', 'w-4 h-4')
            </button>

            {{-- Activity Sidebar Toggle --}}
            <button x-data
                @click="Alpine.store('page') && (Alpine.store('page').activityOpen = !Alpine.store('page').activityOpen)"
                class="inline-flex items-center justify-center w-8 h-8 rounded-md transition"
                :class="Alpine.store('page')?.activityOpen
                    ? 'text-[var(--ui-primary)] bg-[var(--ui-muted-5)]'
                    : 'text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)]'"
                title="Aktivitäten-Sidebar umschalten">
                @svg('heroicon-o-bell-alert', 'w-4 h-4')
            </button>

            <div class="h-6 w-px bg-[var(--ui-border)]/60 mx-0.5"></div>
        </div>

        {{-- Team Flyout (always visible) --}}
        @livewire('core.team-flyout')

        {{-- User avatar --}}
        <button type="button"
            @click="$dispatch('open-modal-user')"
            class="flex items-center justify-center w-8 h-8 rounded-full hover:ring-2 hover:ring-[var(--ui-primary)]/30 transition overflow-hidden"
            title="{{ $userName }}">
            @if($userAvatar)
                <img src="{{ $userAvatar }}" alt="{{ $userName }}" class="w-7 h-7 rounded-full object-cover" />
            @else
                <div class="w-7 h-7 rounded-full bg-[var(--ui-primary-5)] flex items-center justify-center text-xs font-medium text-[var(--ui-primary)]">
                    {{ strtoupper(substr($userName ?? '?', 0, 1)) }}
                </div>
            @endif
        </button>
    </div>

    {{-- ═══ Mobile Menu Panel ═══ --}}
    <template x-teleport="body">
        <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
             @click.outside="mobileMenuOpen = false"
             class="fixed top-12 left-0 right-0 z-[98] bg-[var(--ui-surface)] border-b border-[var(--ui-border)]/60 shadow-lg md:hidden">
            <div class="p-3 space-y-1">
                {{-- Favorite links --}}
                @foreach($favorites as $fav)
                    <a href="{{ $fav['url'] }}" @click="mobileMenuOpen = false"
                        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm transition
                        {{ $currentModuleKey === $fav['key']
                            ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-medium'
                            : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @if(!empty($fav['icon']))
                            <x-dynamic-component :component="$fav['icon']" class="w-4 h-4 flex-shrink-0" />
                        @else
                            @svg('heroicon-o-cube', 'w-4 h-4 flex-shrink-0')
                        @endif
                        <span>{{ $fav['title'] }}</span>
                    </a>
                @endforeach

                <div class="border-t border-[var(--ui-border)]/60 my-2"></div>

                {{-- Module button --}}
                <button type="button" @click="$dispatch('open-modal-modules'); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4 flex-shrink-0')
                    <span>Alle Module</span>
                </button>

                {{-- Admin button --}}
                @if($isAdmin)
                    <button type="button" @click="$dispatch('open-modal-modules', { tab: 'matrix' }); mobileMenuOpen = false"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 flex-shrink-0')
                        <span>Administration</span>
                    </button>
                @endif

                <div class="border-t border-[var(--ui-border)]/60 my-2"></div>

                {{-- Mobile action buttons --}}
                <button type="button" @click="$dispatch('open-modal-checkin'); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition">
                    @svg('heroicon-o-sun', 'w-4 h-4 flex-shrink-0')
                    <span>Check-in</span>
                </button>

                <button type="button" @click="$dispatch('open-modal-comms'); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition">
                    @svg('heroicon-o-paper-airplane', 'w-4 h-4 flex-shrink-0')
                    <span>Kommunikation</span>
                </button>

                <button type="button" @click="$dispatch('open-help', { module: @js($routeModule) }); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition">
                    @svg('heroicon-o-question-mark-circle', 'w-4 h-4 flex-shrink-0')
                    <span>Hilfe</span>
                </button>
            </div>
        </div>
    </template>
</nav>
