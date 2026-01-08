@php
  $routeName = request()->route()?->getName();
  $routeModule = is_string($routeName) && str_contains($routeName, '.') ? strstr($routeName, '.', true) : null;
  $ctx = [
    'source_route' => $routeName,
    'source_module' => $routeModule,
    'source_url' => request()->fullUrl(),
  ];
@endphp

<x-ui-page>
  <x-slot name="navbar">
    <x-ui-page-navbar title="Simple Playground" icon="heroicon-o-sparkles" />
  </x-slot>

  <x-ui-page-container>
    <div class="max-w-3xl mx-auto py-10">
      <div class="rounded-xl border border-[var(--ui-border)] bg-[var(--ui-surface)] p-6">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 rounded-lg bg-[var(--ui-primary-10)] flex items-center justify-center flex-shrink-0">
            @svg('heroicon-o-sparkles', 'w-6 h-6 text-[var(--ui-primary)]')
          </div>
          <div class="min-w-0">
            <div class="text-lg font-semibold text-[var(--ui-secondary)]">Playground ist jetzt im Modal</div>
            <div class="mt-1 text-sm text-[var(--ui-muted)]">
              Diese Seite ist nur noch ein Alias. Das Modal öffnet sich automatisch (gleiche UI/JS wie im Navbar-Stern).
            </div>
            <div class="mt-4 flex items-center gap-2">
              <button
                type="button"
                class="px-3 py-2 rounded-md bg-[var(--ui-primary)] text-white text-sm hover:bg-opacity-90"
                onclick="window.dispatchEvent(new CustomEvent('playground:open', { detail: { context: @json($ctx) }, bubbles: true }))"
              >
                Modal öffnen
              </button>
              <div class="text-xs text-[var(--ui-muted)]">Tipp: oben rechts im Navbar über das ⭐︎-Icon.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      // Auto-open on direct navigation to /core/tools/simple to avoid having a second, divergent playground UI.
      (() => {
        const ctx = @json($ctx);
        const open = () => window.dispatchEvent(new CustomEvent('playground:open', { detail: { context: ctx }, bubbles: true }));
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', open, { once: true });
        else open();
      })();
    </script>
  </x-ui-page-container>
</x-ui-page>


