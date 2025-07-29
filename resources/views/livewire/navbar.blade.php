{{-- resources/views/vendor/ui/navbar.blade.php --}}
<nav class="position-fixed top-0 left-0 right-0 z-50 bg-white border-bottom-1 border-bottom-solid border-muted">

    <div class="container mx-auto px-5 d-flex justify-between items-center h-16">

        {{-- Links: Modul-Wechsler + Aktuelles Modul --}}
        <div @click="$dispatch('open-modal-modules')" class="d-flex items-center gap-5">
            <button type="button"
                    class="text-secondary hover:text-primary bg-transparent border-none p-0"
                    title="Module wechseln">
                <x-heroicon-o-bars-4 class="w-6 h-6" />
            </button>


            @php
                use Platform\Core\PlatformCore;

                $modules = PlatformCore::getModules();

                // Modul-Key aus aktueller Route ermitteln (z. B. "planner" aus "planner.dashboard")
                $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;

                // Direkt aus Registry holen
                $currentModule = $modules[$currentModuleKey] ?? null;
            @endphp

            <span class="text-xl tracking-wide text-primary">
                {{ $currentModule['title'] ?? '' }}
            </span>

            <div>
                <a class = "text-muted text-xl" href = "{{route('platform.dashboard')}}">
                    Home
                </a>
            </div>
        </div>

        {{-- Rechts: Team und User --}}

        <div class = "d-flex gap-3">
            @auth
            @if(isset($monthlyTotal) && $monthlyTotal > 0)
            <x-ui-button
                variant="info-outline"
                size="md"
                @click="$dispatch('open-modal-pricing')"
            >
                <span class="d-flex items-center space-x-2 gap-1">
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                    
                        <span>{{ number_format($monthlyTotal, 2, ',', '.') }} €</span>
                    
                </span>
            </x-ui-button>
            @endif


            
            <x-ui-button 
                variant="secondary-outline" 
                size="md" 
                @click="$dispatch('open-modal-team')"
            >
                <span class="d-flex items-center space-x-2 gap-1">
                    <x-heroicon-o-users class="w-5 h-5" />
                    <span>{{ $currentTeamName }}</span>
                </span>
            </x-ui-button>
        



            <div class = "d-flex items-center gap-3" @click="$dispatch('open-modal-user')">
                
                <x-ui-button variant="secondary-outline" size="md" icon-only>
                    <x-heroicon-o-user />
                </x-ui-button>
            </div>
            @endauth

            @guest
                <a href="{{ route('login') }}" class="text-sm font-medium text-secondary hover:text-primary">Login</a>
                <a href="{{ route('register') }}" class="text-sm font-medium text-secondary hover:text-primary">Registrieren</a>
            @endguest
        </div>
    </div>
</nav>