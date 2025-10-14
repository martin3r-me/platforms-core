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
    // Livewire Navigate im Embedded-Kontext deaktivieren (verhindert weiße Seiten/History-Rewrites)
    try { window.Livewire?.navigate?.disable?.(); } catch (_) {}
  </script>
  <script>
    // Globaler, schlanker Teams-Auth-Bootstrap für alle embedded Seiten
    (function(){
      var MAX_RETRIES = 10;
      var RETRY_DELAY_MS = 300;
      function authOnce() {
        try {
          if (window.__laravelAuthed === true) return; // bereits eingeloggt
          if (sessionStorage.getItem('teams-auth-running') === 'true') return; // Guard gegen Doppelläufe
          if (sessionStorage.getItem('teams-auth-completed') === 'true') return; // schon erfolgreich in dieser Session
          if (!(window.microsoftTeams && window.microsoftTeams.app)) return scheduleRetry();

          sessionStorage.setItem('teams-auth-running', 'true');

          window.microsoftTeams.app.initialize()
            .then(function(){ return window.microsoftTeams.app.getContext(); })
            .then(function(ctx){
              var email = (ctx && ctx.user && ctx.user.userPrincipalName) || '';
              var name = (ctx && ctx.user && ctx.user.displayName) || '';
              function postAuth(e, n){
                if (!e) { cleanup(false); return; }
                fetch('/planner/embedded/teams/auth', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                  },
                  body: JSON.stringify({ email: e, name: n || '' })
                }).then(function(res){
                  if (res.ok) {
                    sessionStorage.setItem('teams-auth-completed', 'true');
                    setTimeout(function(){ location.reload(); }, 100);
                  } else {
                    cleanup(false);
                  }
                }).catch(function(){ cleanup(false); });
              }
              if (email) return postAuth(email, name);
              // Fallback: explizit User anfordern
              if (window.microsoftTeams.authentication && window.microsoftTeams.authentication.getUser) {
                return window.microsoftTeams.authentication.getUser()
                  .then(function(user){ postAuth(user && user.userPrincipalName, user && user.displayName); })
                  .catch(function(){ cleanup(false); });
              }
              cleanup(false);
            })
            .catch(function(){ cleanup(false); });

          function cleanup(success){
            sessionStorage.removeItem('teams-auth-running');
            if (!success) scheduleRetry();
          }
        } catch (_) { scheduleRetry(); }
      }
      function scheduleRetry(){
        var retries = parseInt(sessionStorage.getItem('teams-auth-retries') || '0', 10);
        if (retries >= MAX_RETRIES) return;
        retries += 1;
        sessionStorage.setItem('teams-auth-retries', String(retries));
        setTimeout(authOnce, RETRY_DELAY_MS);
      }
      // Start
      authOnce();
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