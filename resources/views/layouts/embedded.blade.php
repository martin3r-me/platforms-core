<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'Platform') }}</title>

  <x-ui-styles />
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles
  <style>
    html, body { height: 100%; }
  </style>
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)] h-full overflow-hidden">
  <div class="flex h-full w-full">
    <main class="flex-1 min-w-0 h-full bg-white flex flex-col overflow-hidden">
      <div class="flex-1 min-h-0 h-full overflow-y-auto">
        @yield('content')
      </div>
    </main>
  </div>
  <script src="https://unpkg.com/@wotz/livewire-sortablejs@1.0.0/dist/livewire-sortable.js"></script>

  @livewireScripts
</body>
</html>


