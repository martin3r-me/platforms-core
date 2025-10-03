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

  {{-- Navbar entfernt --}}
  

  <div class="layout d-flex h-full w-full min-h-0">
   
    <!-- Grid-Icon über der Sidebar -->
    <div class="fixed top-0 left-0 z-50" style="top: 50px;">
        <button 
            @click="$dispatch('open-modal-modules', { tab: 'modules' })" 
            class="w-14 h-14 d-flex items-center justify-center bg-black border border-muted hover:bg-gray-800 transition"
            title="Module öffnen"
        >
            @svg('heroicon-o-squares-2x2', 'w-5 h-5 text-white')
        </button>
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