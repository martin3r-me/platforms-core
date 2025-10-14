{{-- resources/views/vendor/platform/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script>
    window.__laravelAuthed = {{ auth()->check() ? 'true' : 'false' }};
  </script>

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

  <div class="flex h-screen w-full">
    <!-- Main Content only (ohne Sidebar) -->
    <main class="flex-1 min-w-0 h-screen bg-white flex flex-col overflow-hidden">
      <div class="flex-1 min-h-0 overflow-y-auto">
        @hasSection('content')
          @yield('content')
        @else
          {{ $slot }}
        @endif
      </div>
    </main>
  </div>

  @livewireScripts
  <script>
    // Globaler, schlanker Teams-Auth-Bootstrap für alle embedded Seiten
    (function(){
      try {
        if (window.__laravelAuthed === true) return; // bereits eingeloggt
        if (sessionStorage.getItem('teams-auth-attempted') === 'true') return; // nur einmal pro Session versuchen
        if (!(window.microsoftTeams && window.microsoftTeams.app)) return; // nur in Teams
        sessionStorage.setItem('teams-auth-attempted', 'true');

        window.microsoftTeams.app.initialize()
          .then(function(){ return window.microsoftTeams.app.getContext(); })
          .then(function(ctx){
            var email = (ctx && ctx.user && ctx.user.userPrincipalName) || '';
            var name = (ctx && ctx.user && ctx.user.displayName) || '';
            if (!email) { return; }
            return fetch('/planner/embedded/teams/auth', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
              },
              body: JSON.stringify({ email: email, name: name })
            }).then(function(res){
              if (res.ok) { setTimeout(function(){ location.reload(); }, 100); }
            }).catch(function(){ /* noop: nicht erneut versuchen in dieser Session */ });
          })
          .catch(function(){ /* noop */ });
      } catch (_) { /* noop */ }
    })();
  </script>
  
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

  {{-- Zusätzliche Scripts von Komponenten --}}
  @stack('scripts')

</body>
</html>