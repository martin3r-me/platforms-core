{{-- resources/views/vendor/platform/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'Platform') }}</title>

  {{-- UI Token & Utility CSS --}}
  <x-ui-styles />

  {{-- optional: eigenes JS / Livewire --}}
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles

  <script src="https://unpkg.com/@wotz/livewire-sortablejs@1.0.0/dist/livewire-sortable.js"></script>
  <!-- Microsoft Teams JavaScript SDK (für Tabs/Settings) -->
  <script src="https://res.cdn.office.net/teams-js/2.0.0/js/MicrosoftTeams.min.js" crossorigin="anonymous"></script>
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)] overflow-hidden" data-embedded="1">

  @php
    $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
    $class = $currentModuleKey 
        ? "\\Platform\\".str_replace('-', '', ucwords($currentModuleKey, '-'))."\\Livewire\\Sidebar"
        : null;
  @endphp

  <div class="h-screen w-full overflow-hidden">
    <div class="h-full overflow-y-auto">
      @yield('content')
    </div>
  </div>

  @livewireScripts
  
  {{-- Wichtige Livewire-Komponenten für embedded Kontext --}}
  @auth 
    @livewire('core.modal-team')
    @livewire('core.modal-user')
    @livewire('core.modal-pricing')
    @livewire('comms.comms-modal')
    @livewire('core.modal-modules')
  @endauth
    
  <livewire:notifications.notices.index />
  @if(config('notifications.show_modal'))
      <livewire:notifications.notices.modal />
  @endif

</body>
</html>