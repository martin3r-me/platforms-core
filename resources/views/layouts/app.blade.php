{{-- resources/views/vendor/platform/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @if(config('broadcasting.default') === 'reverb')
  <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
  <meta name="reverb-host" content="{{ parse_url(config('app.url'), PHP_URL_HOST) }}">
  <meta name="reverb-port" content="443">
  @endif

  <title>{{ config('app.name', 'Platform') }}</title>

  {{-- Favicons --}}
  <link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/favicon/android-chrome-192x192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="/favicon/android-chrome-512x512.png">
  <link rel="manifest" href="/favicon/site.webmanifest">

  {{-- UI Token & Utility CSS --}}
  <x-ui-styles />

  {{-- optional: eigenes JS / Livewire --}}
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @livewireStyles

  <script src="https://unpkg.com/@wotz/livewire-sortablejs@1.0.0/dist/livewire-sortable.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
  <script src="/_platform/assets/platform-tiptap.iife.js?v={{ config('platform.tiptap_hash', '0') }}" defer></script>
  <script src="/_platform/assets/platform-workshop.iife.js?v={{ config('platform.workshop_hash', '0') }}" defer></script>
  @if(config('broadcasting.default') === 'reverb')
  <script src="/_platform/assets/platform-echo.iife.js?v={{ config('platform.echo_hash', '0') }}" defer></script>
  @endif
</head>

<body class="bg-[var(--ui-body-bg)] text-[var(--ui-body-color)] overflow-hidden">

  @auth
  @php
    $uiState = \Platform\Core\Models\UserUiPreference::where('user_id', auth()->id())->value('state') ?? new \stdClass();
    $uiModule = explode('.', request()->route()?->getName() ?? '')[0] ?: 'core';
  @endphp
  <script>
    window.__UI_PREFS__ = @json($uiState);
    window.__UI_MODULE__ = @json($uiModule);
    window.__UI_PREFS_URL__ = @json(route('platform.ui-preferences.update'));
  </script>
  <script>
    document.addEventListener('alpine:init', () => {
      const DEFAULTS = {
        main_sidebar: { collapsed: false, width: 288 },
        page_sidebar: { open: true, width: 320 },
        activity: { open: false, width: 320 },
        terminal: { open: false },
      };

      Alpine.store('ui', {
        state: (window.__UI_PREFS__ && typeof window.__UI_PREFS__ === 'object') ? window.__UI_PREFS__ : {},
        module: window.__UI_MODULE__ || 'core',
        _syncTimer: null,

        g(scope, field) {
          return this.state?.[scope]?.[field] ?? DEFAULTS[scope]?.[field];
        },
        gSet(scope, field, value) {
          if (!this.state[scope]) this.state[scope] = {};
          this.state[scope][field] = value;
          this._scheduleSync();
        },

        m(scope, field) {
          const mod = this.state?.modules?.[this.module];
          return mod?.[scope]?.[field] ?? DEFAULTS[scope]?.[field];
        },
        mSet(scope, field, value) {
          if (!this.state.modules) this.state.modules = {};
          if (!this.state.modules[this.module]) this.state.modules[this.module] = {};
          if (!this.state.modules[this.module][scope]) this.state.modules[this.module][scope] = {};
          this.state.modules[this.module][scope][field] = value;
          this._scheduleSync();
        },
        mToggle(scope, field) {
          this.mSet(scope, field, !this.m(scope, field));
        },

        _scheduleSync() {
          clearTimeout(this._syncTimer);
          this._syncTimer = setTimeout(() => this._sync(), 300);
        },
        _sync() {
          const token = document.querySelector('meta[name="csrf-token"]')?.content;
          fetch(window.__UI_PREFS_URL__, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': token || '',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ state: this.state }),
          }).catch(() => { /* silent — UI bleibt intakt */ });
        },
      });
    });
  </script>
  @endauth

  {{-- Modals früh laden, damit sie Events empfangen können --}}
  @auth
    @livewire('core.modal-team')
    @livewire('core.modal-user')
    @livewire('core.modal-checkin')
    @livewire('core.modal-algedonic')
    @livewire('core.modal-pricing')
    @livewire('core.modal-modules')
    @livewire('core.modal-help')
    @if(class_exists(\Platform\Crm\Livewire\ModalComms::class))
      @livewire('crm.modal-comms')
    @endif
    @livewire('core.modal-simple-tool-playground')
    @livewire('organization.modal-organization')
  @endauth

  @php
    $currentModuleKey = explode('.', request()->route()?->getName())[0] ?? null;
    $class = $currentModuleKey 
        ? "\\Platform\\".str_replace('-', '', ucwords($currentModuleKey, '-'))."\\Livewire\\Sidebar"
        : null;
  @endphp

  <div class="flex h-screen w-full">
    <!-- Sidebar -->
    <x-ui-sidebar>
        @if($class && class_exists($class))
            @livewire($currentModuleKey.'.sidebar')
        @endif
    </x-ui-sidebar>

    <!-- Main Content -->
    <main class="flex-1 min-w-0 h-screen bg-white flex flex-col overflow-hidden">
      @livewire('core.terminal')
      <div class="flex-1 min-h-0 overflow-y-auto order-first">
        {{ $slot }}
      </div>
    </main>
  </div>
    
  <livewire:notifications.notices.index />
  @if(config('notifications.show_modal'))
      <livewire:notifications.notices.modal />
  @endif

  @livewireScripts

</body>
</html>