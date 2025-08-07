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
    $sidebarClass = $currentModuleKey 
        ? "\\Platform\\".ucfirst($currentModuleKey)."\\Livewire\\Sidebar"
        : null;

    $optionalLivewireComponents = [
        'core.modal-team'     => \Platform\Core\Livewire\ModalTeam::class,
        'core.modal-user'     => \Platform\Core\Livewire\ModalUser::class,
        'core.modal-pricing'  => \Platform\Core\Livewire\ModalPricing::class,
        'core.modal-modules'  => \Platform\Core\Livewire\ModalModules::class,
        'comms.comms-modal'   => \Platform\Comms\Livewire\CommsModal::class,
        'notifications.notices.modal' => \Platform\Notifications\Livewire\Notices\Modal::class,
    ];
  @endphp

  {{-- Fixed Navbar --}}
  <livewire:core.navbar/>

  <div class="layout d-flex h-full pt-16">
    <x-ui-sidebar>
        @if($sidebarClass && class_exists($sidebarClass))
            @livewire($currentModuleKey.'.sidebar')
        @endif
    </x-ui-sidebar>

    {{-- Main Content --}}
    <main class="main flex-grow overflow-auto p-1 bg-white">
        {{ $slot }}
    </main>
  </div>

  @auth
    @foreach ($optionalLivewireComponents as $alias => $class)
        @if (class_exists($class))
            <livewire:{{ $alias }} />
        @endif
    @endforeach
  @endauth

  {{-- Immer sichtbare Benachrichtigungen --}}
  @if (class_exists(\Platform\Notifications\Livewire\Notices\Index::class))
    <livewire:notifications.notices.index />
  @endif

  @livewireScripts
</body>
</html>