<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Glowkit') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <x-ui-styles/>
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white selection:bg-indigo-100 dark:selection:bg-indigo-900">

    {{ $slot }}

    @auth 
        @livewire('core.modal-team')
        @livewire('core.modal-user')
        @livewire('core.modal-pricing')
        @livewire('core.modal-modules')
    @endauth
        
    <livewire:notifications.notices.index />
    @if(config('notifications.show_modal'))
            <livewire:notifications.notices.modal />
    @endif

    @livewireScripts
</body>
</html>