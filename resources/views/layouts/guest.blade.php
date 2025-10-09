<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'platform') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <x-ui-styles/>
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)] selection:bg-[rgba(var(--ui-primary-rgb),0.2)]">

    {{-- Fixed Top Navbar --}}
    <livewire:core.navbar />

    {{-- Auth-Container --}}
    <div class="pt-16 min-h-screen flex items-center justify-center">
        <main class="w-full max-w-5xl p-6">
            {{ $slot }}
        </main>
    </div>

    @auth 
        @livewire('core.modal-team')
        @livewire('core.modal-user')
        @livewire('core.modal-pricing')
        @livewire('core.modal-modules')
        @livewire('comms.comms-modal')
    @endauth
        
    <livewire:notifications.notices.index />
    @if(config('notifications.show_modal'))
            <livewire:notifications.notices.modal />
    @endif

    @livewireScripts
</body>
</html>