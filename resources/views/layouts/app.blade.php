{{-- resources/views/vendor/platform/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'Platform') }}</title>

  {{-- UI Token & Utility CSS --}}
  {{-- entfernt: <link rel="stylesheet" href="{{ asset('vendor/ui/ui.css') }}"> --}}
  <x-ui-styles />

  {{-- optional: eigenes JS / Livewire --}}
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles

  <script src="https://unpkg.com/@wotz/livewire-sortablejs@1.0.0/dist/livewire-sortable.js"></script>
</head>

<body class="bg-[color:var(--ui-body-bg)] text-[color:var(--ui-body-color)] flex flex-col h-full">

  @if((bool) (env('UI_TW_DEBUG', app()->environment('local'))))
    <div class="fixed z-[9999] top-2 right-2 text-xs px-2 py-1 rounded bg-[rgb(var(--ui-primary-rgb))] text-[var(--ui-on-primary)] shadow">
      Tailwind active
    </div>
  @endif

  @php
    $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
    $class = $currentModuleKey 
        ? "\\Platform\\".str_replace('-', '', ucwords($currentModuleKey, '-'))."\\Livewire\\Sidebar"
        : null;
  @endphp

  {{-- Navbar entfernt --}}
  

  <div class="layout flex h-full w-full min-h-0">
   
    <!-- Grid-Icon über der Sidebar -->
    <div class="h-full bg-black p-2" x-data>
        <button 
            @click="$dispatch('open-modal-modules')"
            class="flex items-center justify-center border border-[color:var(--ui-border)] hover:bg-gray-800 transition"
            title="Module öffnen"
        >
            <div class="w-3 h-3 bg-white rounded-full"></div>
        </button>
    </div>

    <x-ui-sidebar>
        @if($class && class_exists($class))
            @livewire($currentModuleKey.'.sidebar')
        @endif
    </x-ui-sidebar>

    {{-- Main Content --}}
    <main class="main flex-1 min-w-0 overflow-auto p-1 bg-white">
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