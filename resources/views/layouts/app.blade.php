{{-- resources/views/vendor/platform/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'Platform') }}</title>

  {{-- UI Token & Utility CSS --}}
  <link rel="stylesheet" href="{{ asset('vendor/ui/ui.css') }}">
  <x-ui-styles />

  {{-- optional: eigenes JS / Livewire --}}
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles

  <script src="https://unpkg.com/@wotz/livewire-sortablejs@1.0.0/dist/livewire-sortable.js"></script>
</head>

<body class="bg-body-bg text-body-color d-flex flex-col h-full">

  @php
    $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
    $class = $currentModuleKey 
        ? "\\Platform\\".str_replace('-', '', ucwords($currentModuleKey, '-'))."\\Livewire\\Sidebar"
        : null;
  @endphp

  {{-- Fixed Navbar --}}
  <livewire:core.navbar/>
  

  <div class="layout d-flex h-full w-full min-h-0">
   
    <!-- Grid-Icon über der Sidebar -->
    <div class="fixed top-0 left-0 z-50" style="top: 50px;">
        <div x-data="{ showDropdown: false }" class="relative">
            <button 
                @click="$dispatch('toggle-sidebar')" 
                class="w-14 h-14 d-flex items-center justify-center bg-white border border-muted hover:bg-gray-50 transition"
                title="Sidebar umschalten"
            >
                @svg('heroicon-o-squares-2x2', 'w-5 h-5 text-primary')
            </button>
            
            <!-- Quick Actions Dropdown -->
            <div 
                x-show="showDropdown" 
                @click.away="showDropdown = false"
                x-transition
                class="absolute top-full left-0 mt-1 w-48 bg-white border border-muted rounded shadow-lg z-50"
            >
                <div class="p-2">
                    <button 
                        @click="$dispatch('open-modal-modules', { tab: 'modules' }); showDropdown = false"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 rounded text-sm"
                    >
                        @svg('heroicon-o-cube', 'w-4 h-4 inline mr-2') Module
                    </button>
                    <button 
                        @click="$dispatch('open-modal-modules', { tab: 'team' }); showDropdown = false"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 rounded text-sm"
                    >
                        @svg('heroicon-o-users', 'w-4 h-4 inline mr-2') Team
                    </button>
                    <button 
                        @click="$dispatch('open-modal-modules', { tab: 'account' }); showDropdown = false"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 rounded text-sm"
                    >
                        @svg('heroicon-o-user', 'w-4 h-4 inline mr-2') Konto
                    </button>
                </div>
            </div>
            
            <!-- Right-click für Dropdown -->
            <button 
                @contextmenu.prevent="showDropdown = !showDropdown"
                @click="$dispatch('open-modal-modules', { tab: 'modules' })"
                class="w-14 h-14 d-flex items-center justify-center bg-white border border-muted hover:bg-gray-50 transition"
                title="Module öffnen (Rechtsklick für Menü)"
            >
                @svg('heroicon-o-squares-2x2', 'w-5 h-5 text-primary')
            </button>
        </div>
    </div>

    <x-ui-sidebar>
        @if($class && class_exists($class))
            @livewire($currentModuleKey.'.sidebar')
        @endif
    </x-ui-sidebar>

        


    {{-- Main Content --}}
    <main class="main flex-grow-1 min-w-0 overflow-auto p-1 bg-white">
        {{ $slot }}
    </main>
    {{-- Rechte Cursor-Sidebar entfernt --}}
  </div>

  @auth 
    <livewire:core.modal-team/>
    <livewire:core.modal-user/>
    <livewire:core.modal-pricing/>
    <livewire:comms.comms-modal/>
    <livewire:core.modal-modules/>
  @endauth
    
    <livewire:notifications.notices.index />
    @if(config('notifications.show_modal'))
        <livewire:notifications.notices.modal />
    @endif

    @livewireScripts

</body>
</html>