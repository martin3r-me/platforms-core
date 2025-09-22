<nav class="position-fixed top-0 left-0 right-0 z-50 bg-white border-bottom-1 border-bottom-solid border-muted">
    <div class="container mx-auto px-5 d-flex items-center justify-between h-16">

        {{-- Links: Logo + ggf. Modul-Titel --}}
        <div class="d-flex items-center">
            @auth
                <div @click="$dispatch('open-modal-modules')" class="cursor-pointer" title="Module wechseln">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-8">
                </div>

                @php
                    $modules = \Platform\Core\PlatformCore::getModules();
                    $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
                    $currentModule = $modules[$currentModuleKey] ?? null;
                @endphp

                    @if($currentModule)
                    <x-ui-badge variant="secondary" size="sm" class="cursor-pointer">
                        {{ $currentModule['title'] ?? '' }}
                    </x-ui-badge>
                    @endif
            @endauth

            @guest
                <a href="{{ route('landing') }}">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="h-8">
                </a>
            @endguest
        </div>

        {{-- Rechts: Team/User oder Login/Register --}}
        <div class="d-flex items-center gap-3 ml-auto">
            @auth
                @if(isset($monthlyTotal) && $monthlyTotal > 0)
                    <x-ui-button
                        variant="info-outline"
                        size="md"
                        @click="$dispatch('open-modal-pricing')"
                    >
                        <span class="d-flex items-center gap-1">
                            <x-heroicon-o-banknotes class="w-5 h-5" />
                            <span>{{ number_format((float)($monthlyTotal ?? 0), 2, ',', '.') }} €</span>
                        </span>
                    </x-ui-button>
                @endif

                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    @click="$dispatch('open-modal-commands')"
                >
                    <span class="d-flex items-center gap-1">
                        <x-heroicon-o-command-line class="w-5 h-5" />
                        <span>Befehl</span>
                    </span>
                </x-ui-button>

                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    @click="$dispatch('open-modal-team')"
                >
                    <span class="d-flex items-center gap-1">
                        <x-heroicon-o-users class="w-5 h-5" />
                        <span>{{ $currentTeamName }}</span>
                    </span>
                </x-ui-button>

                <x-ui-button 
                    variant="primary-outline" 
                    size="md" 
                    @click="$dispatch('open-modal-cursor')"
                >
                    <span class="d-flex items-center gap-1">
                        <x-heroicon-o-bolt class="w-5 h-5" />
                        <span>Cursor</span>
                    </span>
                </x-ui-button>

                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    icon-only 
                    @click="$dispatch('open-modal-user')"
                >
                    <x-heroicon-o-user />
                </x-ui-button>
            @endauth

            @guest
                <a href="{{ route('login') }}" class="text-sm font-medium text-secondary hover:text-primary">
                    Login
                </a>
                <a href="{{ route('register') }}" class="text-sm font-medium text-secondary hover:text-primary">
                    Registrieren
                </a>
            @endguest
        </div>
    </div>
</nav>