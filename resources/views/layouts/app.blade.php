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
  @endphp

  {{-- Fixed Navbar --}}
  <livewire:core.navbar/>
  

  <div class="layout d-flex h-full pt-16">
    {{-- Sidebar --}}
    <livewire:core.sidebar 
      :module-key="$currentModuleKey" 
      class="sidebar bg-surface border-r border-border" />

    {{-- Main Content --}}
    <main class="main flex-grow overflow-auto p-1 bg-white">
        @hasSection('secondary-navbar')
            <div class="secondary-navbar w-full border-b bg-surface flex items-center px-4 py-2 mb-4 shadow-sm">
                @yield('secondary-navbar')
            </div>
        @endif
        {{ $slot }}
    </main>
  </div>

  @auth 
    <livewire:core.modal-team/>
    <livewire:core.modal-user/>
    <livewire:core.modal-pricing/>
  @endauth
    <livewire:core.modal-modules/>
    <livewire:notifications.notices.index />
    @if(config('notifications.show_modal'))
        <livewire:notifications.notices.modal />
    @endif

    @livewireScripts
</body>
</html>