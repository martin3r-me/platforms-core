<nav class="fixed top-0 right-0 z-[60]">
    <div class="flex items-center justify-end pt-3 px-3 pointer-events-none">
        <div class="flex items-center gap-2 px-3 py-1 rounded-full border border-[var(--ui-border)] bg-white shadow-sm pointer-events-auto w-80">
            @auth
                <button type="button" class="border-0 bg-transparent cursor-pointer flex items-center gap-2" @click="$dispatch('open-modal-modules')" title="Modulmenü (⌘K / M)">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-6">
                    @php
                        $modules = \Platform\Core\PlatformCore::getModules();
                        $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
                        $currentModule = $modules[$currentModuleKey] ?? null;
                        $teamName = auth()->user()?->currentTeam?->name;
                    @endphp
                    @if($currentModule)
                        <span class="text-sm text-[var(--ui-secondary)] truncate max-w-[10rem]">{{ $currentModule['title'] ?? '' }}</span>
                    @endif
                    @if($teamName)
                        <span class="text-xs text-[var(--ui-muted)] truncate max-w-[8rem]">{{ $teamName }}</span>
                    @endif
                </button>
            @endauth

            @guest
                <a href="{{ route('landing') }}" class="flex items-center gap-2" title="Startseite">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-6">
                </a>
                <a href="{{ route('login') }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)]" title="Tipp: ⌘K oder M öffnet das Modul-Menü">Login</a>
                <a href="{{ route('register') }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)]">Registrieren</a>
            @endguest
        </div>
    </div>
</nav>