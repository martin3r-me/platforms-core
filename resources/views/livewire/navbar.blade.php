<nav x-data="{ mobileMenuOpen: false }"
     class="w-full h-11 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] flex items-center px-3 gap-2 shrink-0">

    {{-- ═══ ZONE 1: Logo + Favorites (left) ═══ --}}
    <div class="flex items-center gap-1 min-w-0">
        {{-- Logo --}}
        <a href="{{ route('platform.dashboard') }}" class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-md hover:bg-[color:var(--nx-hover)] transition" title="Dashboard">
            <img src="/logo.png" alt="Home" class="h-5 w-5 rounded object-contain" />
        </a>

        {{-- Mobile hamburger --}}
        <button @click="mobileMenuOpen = !mobileMenuOpen"
            class="md:hidden flex items-center justify-center w-8 h-8 rounded-md hover:bg-[color:var(--nx-hover)] transition text-[color:var(--nx-text)]">
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
                        ? 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-text)] font-medium'
                        : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}"
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
            {{-- Actions: Check-in, Comms, Terminal --}}
            @livewire('core.navbar-checkin')

            <button x-data
                @click="$dispatch('open-modal-comms')"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md transition text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]"
                title="Kommunikation">
                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
            </button>

            {{-- Terminal (bottom) — einziges Panel ohne eigenen Griff (kollabiert vollständig) --}}
            <button x-data
                @click="window.dispatchEvent(new CustomEvent('toggle-terminal'))"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md transition"
                :class="$store.ui?.m('terminal', 'open')
                    ? 'text-[color:var(--nx-text)] bg-[color:var(--nx-accent-soft)]'
                    : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]'"
                title="Terminal">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4">
                    <rect x="3" y="5" width="18" height="8.5" rx="1.5" />
                    <rect x="3" y="15" width="18" height="4" rx="1.5" fill="currentColor" opacity="0.9" stroke="none" />
                </svg>
            </button>

            <div class="h-6 w-px bg-[color:var(--nx-line)] mx-0.5"></div>

            {{-- Algedonic-Signal — Eskalation direkt an die oberste Ebene (Stafford Beer).
                 Bewusst isoliert zwischen zwei Hairlines, warm-rot wie der Team-Warnzustand. --}}
            <button x-data
                @click="$dispatch('open-modal-algedonic')"
                class="relative inline-flex items-center justify-center w-7 h-7 rounded-md transition text-[color:var(--nx-warning)] hover:text-white hover:bg-[color:var(--nx-warning)] group"
                title="Algedonic-Signal — direkt an die oberste Ebene (Stafford Beer)">
                <span class="absolute inset-0 rounded-md bg-[color:var(--nx-warning)] opacity-0 group-hover:opacity-20 animate-pulse"></span>
                @svg('heroicon-o-bell-alert', 'w-4 h-4 relative')
            </button>

            <div class="h-6 w-px bg-[color:var(--nx-line)] mx-0.5"></div>

            {{-- Page Presence: who else is on this page --}}
            @livewire('core.page-presence')
        </div>

        {{-- Team Flyout (always visible) --}}
        @livewire('core.team-flyout')

        {{-- User avatar --}}
        <button type="button"
            @click="$dispatch('open-modal-user')"
            class="flex items-center justify-center rounded-[6px] hover:ring-2 hover:ring-[color:var(--nx-line-strong)] transition"
            title="{{ $userName }}">
            <x-nx-avatar :name="$userName" :src="$userAvatar" size="md" />
        </button>
    </div>

    {{-- ═══ Mobile Menu Panel ═══ --}}
    <template x-teleport="body">
        <div x-show="mobileMenuOpen" x-cloak x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2"
             @click.outside="mobileMenuOpen = false"
             class="fixed top-11 left-0 right-0 z-[98] bg-[color:var(--nx-surface)] border-b border-[color:var(--nx-line)] shadow-lg md:hidden">
            <div class="p-3 space-y-1">
                {{-- Favorite links --}}
                @foreach($favorites as $fav)
                    <a href="{{ $fav['url'] }}" @click="mobileMenuOpen = false"
                        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm transition
                        {{ $currentModuleKey === $fav['key']
                            ? 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-text)] font-medium'
                            : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]' }}">
                        @if(!empty($fav['icon']))
                            <x-dynamic-component :component="$fav['icon']" class="w-4 h-4 flex-shrink-0" />
                        @else
                            @svg('heroicon-o-cube', 'w-4 h-4 flex-shrink-0')
                        @endif
                        <span>{{ $fav['title'] }}</span>
                    </a>
                @endforeach

                <div class="border-t border-[color:var(--nx-line)] my-2"></div>

                {{-- Module button --}}
                <button type="button" @click="$dispatch('open-modal-modules'); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4 flex-shrink-0')
                    <span>Alle Module</span>
                </button>

                {{-- Admin button --}}
                @if($isAdmin)
                    <button type="button" @click="$dispatch('open-modal-modules', { tab: 'matrix' }); mobileMenuOpen = false"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition">
                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 flex-shrink-0')
                        <span>Administration</span>
                    </button>
                @endif

                <div class="border-t border-[color:var(--nx-line)] my-2"></div>

                {{-- Mobile action buttons --}}
                <button type="button" @click="$dispatch('open-modal-checkin'); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition">
                    @svg('heroicon-o-sun', 'w-4 h-4 flex-shrink-0')
                    <span>Check-in</span>
                </button>

                <button type="button" @click="$dispatch('open-modal-comms'); mobileMenuOpen = false"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)] transition">
                    @svg('heroicon-o-paper-airplane', 'w-4 h-4 flex-shrink-0')
                    <span>Kommunikation</span>
                </button>
            </div>
        </div>
    </template>
</nav>
