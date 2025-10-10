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
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)] min-h-screen">
  <main class="min-h-screen">
    {{ $slot }}
  </main>

  @livewireScripts
</body>
</html>


