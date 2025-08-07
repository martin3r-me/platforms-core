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

    <link rel="stylesheet" href="{{ asset('vendor/ui/ui.css') }}">
    <x-ui-styles/>
    
    
</head>

<body class="bg-gray-100 text-gray-800 selection:bg-purple-200/60">

    {{-- Fixed Top Navbar --}}
    <livewire:core.navbar />

    {{-- Flex Container --}}
    <div class="pt-16 flex h-screen overflow-hidden">
        

        {{-- Main Content --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>

    @auth 
        <livewire:core.modal-team/>
        <livewire:core.modal-user/>
        <livewire:core.modal-pricing/>
        <livewire:core.modal-modules/>
        <livewire:comms.comms-modal/>
    @endauth
        
    

    @livewireScripts
</body>
</html>