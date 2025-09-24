<nav class="position-fixed top-0 left-0 right-0 z-50">
    <div class="d-flex items-center justify-center pt-3 px-3" x-data x-init="
        document.addEventListener('keydown', (e) => {
            const metaOrCtrl = e.metaKey || e.ctrlKey;
            const key = e.key?.toLowerCase();
            if ((metaOrCtrl && key === 'k') || key === 'm') {
                e.preventDefault();
                $dispatch('open-modal-modules');
            }
        });
    ">
        <div class="d-flex items-center gap-2 px-3 py-1 rounded-full border border-solid border-1 bg-white shadow-sm">
            @auth
                <button type="button" class="border-0 bg-transparent cursor-pointer d-flex items-center gap-2" @click="$dispatch('open-modal-modules')" title="Modulmenü (⌘K / M)">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-6">
                    @php
                        $modules = \Platform\Core\PlatformCore::getModules();
                        $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
                        $currentModule = $modules[$currentModuleKey] ?? null;
                    @endphp
                    @if($currentModule)
                        <span class="text-sm text-secondary truncate max-w-[10rem]">{{ $currentModule['title'] ?? '' }}</span>
                    @endif
                </button>

                @if(isset($monthlyTotal) && $monthlyTotal > 0)
                    <x-ui-button variant="info-outline" size="sm" icon-only @click="$dispatch('open-modal-modules', { tab: 'billing' })" title="Kosten & Abrechnung">
                        <x-heroicon-o-banknotes class="w-5 h-5" />
                    </x-ui-button>
                @endif

                <x-ui-button variant="secondary-outline" size="sm" icon-only @click="$dispatch('open-modal-modules', { tab: 'team' })" title="Team verwalten">
                    <x-heroicon-o-users class="w-5 h-5" />
                </x-ui-button>

                <x-ui-button variant="primary-outline" size="sm" icon-only @click="$dispatch('cursor-sidebar-toggle')" title="Cursor-Sidebar">
                    <x-heroicon-o-bolt class="w-5 h-5" />
                </x-ui-button>

                <x-ui-button variant="secondary-outline" size="sm" icon-only @click="$dispatch('open-modal-modules', { tab: 'account' })" title="Benutzerkonto">
                    <x-heroicon-o-user />
                </x-ui-button>
            @endauth

            @guest
                <a href="{{ route('landing') }}" class="d-flex items-center gap-2" title="Startseite">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-6">
                </a>
                <a href="{{ route('login') }}" class="text-sm font-medium text-secondary hover:text-primary" title="Tipp: ⌘K oder M öffnet das Modul-Menü">Login</a>
                <a href="{{ route('register') }}" class="text-sm font-medium text-secondary hover:text-primary">Registrieren</a>
            @endguest
        </div>
    </div>
</nav>