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

    {{-- entfernt: <link rel="stylesheet" href="{{ asset('vendor/ui/ui.css') }}"> --}}
    <x-ui-styles/>
    
    
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)] selection:bg-[rgba(var(--ui-primary-rgb),0.2)]">

    <div style="position:fixed;z-index:99999;top:6px;right:6px;font-size:10px;padding:3px 6px;border-radius:4px;background:#111;color:#fff;opacity:0.85">
        CORE GUEST LAYOUT ACTIVE
    </div>

    @if((bool) (env('UI_TW_DEBUG', app()->environment('local'))))
        <div class="fixed z-[9999] top-2 right-2 text-xs px-2 py-1 rounded bg-red-600 text-white shadow">
            Tailwind active
        </div>
    @endif

    {{-- Fixed Top Navbar vorübergehend deaktiviert für klares Auth-Layout --}}
    {{-- <livewire:core.navbar /> --}}

    {{-- Auth-Container --}}
    <div class="min-h-screen flex items-start justify-center pt-16">
        <main class="w-full max-w-5xl px-4">
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
        
    <livewire:notifications.notices.index />
    @if(config('notifications.show_modal'))
            <livewire:notifications.notices.modal />
    @endif

    @livewireScripts
</body>
</html>