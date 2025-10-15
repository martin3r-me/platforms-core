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
      function authOnce(cb) {
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
                    try { if (typeof cb === 'function') cb(); } catch(_) {}
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
      // Global verfügbar machen, damit andere Skripte triggern können
      window.__teamsAuthOnce = authOnce;
      function scheduleRetry(){
        var retries = parseInt(sessionStorage.getItem('teams-auth-retries') || '0', 10);
        if (retries >= MAX_RETRIES) return;
        retries += 1;
        sessionStorage.setItem('teams-auth-retries', String(retries));
        setTimeout(authOnce, RETRY_DELAY_MS);
      }
      // Start
      authOnce();

      // Heartbeat: prüfe regelmäßig Auth-Status und triggere Re-Auth bei Bedarf
      function pingAuth(){
        fetch('/planner/embedded/teams/ping', { credentials: 'include' })
          .then(function(r){ return r.json().catch(function(){ return {}; }); })
          .then(function(data){
            var authed = data && data.auth && data.auth.checked;
            if (!authed) {
              sessionStorage.removeItem('teams-auth-completed');
              try { window.__teamsAuthOnce(); } catch(_) {}
            }
          }).catch(function(){});
      }
      setInterval(pingAuth, 60000);
      document.addEventListener('visibilitychange', function(){ if (!document.hidden) pingAuth(); });
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

  <script>
    // Globaler Request-Wrapper: sendet Teams-Header + Credentials mit
    (function(){
      const nativeFetch = window.fetch;
      window.fetch = function(input, init){
        init = init || {};
        init.credentials = init.credentials || 'include';
        init.headers = init.headers || {};
        try {
          // Teams-User aus sessionStorage an Header hängen (falls vorhanden)
          const tUser = JSON.parse(sessionStorage.getItem('teams-user') || 'null');
          if (tUser && tUser.email) {
            init.headers['X-User-Email'] = tUser.email;
            if (tUser.name) init.headers['X-User-Name'] = tUser.name;
          }
          init.headers['X-Teams-Embedded'] = '1';
        } catch(_) {}
        return nativeFetch(input, init).then(function(res){
          // Bei 401/419 einmalig re-authen und Request wiederholen
          if ((res.status === 401 || res.status === 419) && !init.__retried) {
            return new Promise(function(resolve){
              try {
                (window.__teamsAuthOnce || function(cb){ cb && cb(); })(function(){
                  const retryInit = Object.assign({}, init, { __retried: true });
                  resolve(nativeFetch(input, retryInit));
                });
              } catch(_) { resolve(res); }
            });
          }
          return res;
        });
      };

      // Versuche Teams-User in sessionStorage zu schreiben, wenn SDK verfügbar
      try {
        if (window.microsoftTeams?.app) {
          window.microsoftTeams.app.initialize().then(function(){
            return window.microsoftTeams.app.getContext();
          }).then(function(ctx){
            const email = ctx?.user?.userPrincipalName || '';
            const name = ctx?.user?.displayName || '';
            if (email) sessionStorage.setItem('teams-user', JSON.stringify({ email: email, name: name }));
          }).catch(function(){});
        }
      } catch(_) {}
    })();
  </script>

</body>
</html>