<nav class="w-full">
    <div class="flex items-center justify-end py-3 px-3">
        <div class="flex items-center gap-2 px-3 py-1 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
            @auth
                <button type="button" class="border-0 bg-transparent cursor-pointer flex items-center gap-2" @click="$dispatch('open-modal-modules')" title="Modulmenü (⌘K / M)">
                    @php
                        $modules = \Platform\Core\PlatformCore::getModules();
                        $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
                        $currentModule = $modules[$currentModuleKey] ?? null;
                        $teamName = auth()->user()?->currentTeam?->name;
                    @endphp
                    @if($currentModule)
                        <span class="text-sm text-gray-600 dark:text-gray-400 truncate max-w-[10rem]">{{ $currentModule['title'] ?? '' }}</span>
                    @endif
                    @if($teamName)
                        <span class="text-xs text-gray-500 dark:text-gray-500 truncate max-w-[8rem]">{{ $teamName }}</span>
                    @endif
                </button>
            @endauth

            @guest
                <a href="{{ route('landing') }}" class="flex items-center gap-2" title="Startseite">
                    <img src="/logo.png" alt="Glowkit" class="h-6 w-auto rounded shadow object-contain" />
                </a>
                <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Anmelden">Login</a>
                <a href="{{ route('register') }}" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">Registrieren</a>
            @endguest
        </div>
    </div>
</nav>