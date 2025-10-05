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
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)]">

  @php
    $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
    $class = $currentModuleKey 
        ? "\\Platform\\".str_replace('-', '', ucwords($currentModuleKey, '-'))."\\Livewire\\Sidebar"
        : null;
  @endphp

  <div class="flex min-h-screen w-full">
    <!-- Sidebar -->
    <x-ui-sidebar>
        @if($class && class_exists($class))
            @livewire($currentModuleKey.'.sidebar')
        @endif
    </x-ui-sidebar>

    <!-- Main Content -->
    <main class="flex-1 min-w-0 overflow-y-auto p-3 bg-white">
        {{ $slot }}
    </main>
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