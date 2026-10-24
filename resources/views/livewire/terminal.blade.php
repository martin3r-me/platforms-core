<div
  x-data="terminalShell"
  x-on:toggle-terminal.window="toggle()"
  x-on:toggle-terminal-open.window="if(!open) toggle()"
  x-on:keydown.escape.window="if(fullscreen) toggleFullscreen()"
  style="height:44px;min-height:44px;max-height:44px"
  :class="[
    fullscreen ? 'fixed inset-0 z-[60]' : 'w-full flex-none relative',
    resizing ? '' : 'transition-[height,min-height,max-height] duration-300 ease-[cubic-bezier(0.33,1,0.68,1)]'
  ]"
  x-bind:style="fullscreen
    ? 'height:100vh;min-height:100vh;max-height:100vh'
    : (open ? 'height:' + panelHeight + 'px;min-height:' + panelHeight + 'px;max-height:' + panelHeight + 'px' : 'height:44px;min-height:44px;max-height:44px')"
  wire:ignore.self
  wire:key="terminal-root"
>
  <style>
    :root {
      /* nx: feste warme Konsole — env-UNABHÄNGIG (dunkles Gegenstück zur nx-Palette) */
      --t-glass: rgba(38,37,34,0.85);
      --t-glass-surface: #302f2b;
      --t-glass-hover: #3a3935;
      --t-blur: 24px;
      --t-border: rgba(255,255,255,0.08);
      --t-border-bright: rgba(255,255,255,0.14);
      --t-text: #e8e7e3;
      --t-text-muted: #a9a7a2;
      --t-accent: #d6d5d1;
      --t-glow: rgba(255,255,255,0.10);
      --t-unread-glow: rgba(var(--ui-danger-rgb), 0.3);
      /* Dock-Grund — fester warmer Near-Black, dezenter Verlauf */
      --t-sidebar-from: #2b2a26;
      --t-sidebar-to: #211f1c;
      --t-sidebar-hover: rgba(255,255,255,0.08);
      --t-sidebar-active: rgba(255,255,255,0.14);
    }
    /* Light scope — nx: neutrales Chrome wie die Sidebars (warmes Grau, Near-Black-Text) */
    .terminal-light {
      --t-text: var(--nx-text);
      --t-text-muted: var(--nx-muted);
      --t-border: rgba(55,53,47,0.10);
      --t-border-bright: rgba(55,53,47,0.12);
      --t-accent: var(--nx-accent);
      --t-glass: #eeede8;
      --t-glass-surface: #faf9f6;
      --t-glass-hover: rgba(55,53,47,0.05);
      --t-glow: rgba(55,53,47,0.06);
      --t-sidebar-from: #f4f3ee;
      --t-sidebar-to: #f0efe9;
      --t-sidebar-hover: rgba(55,53,47,0.055);
      --t-sidebar-active: rgba(55,53,47,0.09);
    }
    /* Override white-overlay utilities inside light scope (white-on-white → dark-on-white) */
    .terminal-light .bg-white\/5,
    .terminal-light .bg-white\/\[0\.03\] { background-color: rgba(0,0,0,0.03) !important; }
    .terminal-light .bg-white\/\[0\.06\] { background-color: rgba(0,0,0,0.05) !important; }
    .terminal-light .bg-white\/10 { background-color: rgba(0,0,0,0.06) !important; }
    .terminal-light .hover\:bg-white\/5:hover,
    .terminal-light .hover\:bg-white\/\[0\.04\]:hover,
    .terminal-light .hover\:bg-white\/\[0\.06\]:hover { background-color: rgba(0,0,0,0.04) !important; }
    .terminal-light .hover\:bg-white\/10:hover { background-color: rgba(0,0,0,0.06) !important; }
    .terminal-light .border-white\/5,
    .terminal-light .border-white\/10 { border-color: rgba(0,0,0,0.06) !important; }
    .terminal-light .divide-white\/5 > :not([hidden]) ~ :not([hidden]) { border-color: rgba(0,0,0,0.06) !important; }
    /* Aktiver Tab/Chip: bg-white/15 text-white -> dezenter Dunkel-Tint + Near-Black-Text.
       Nur die Kombination bg-white/15 + text-white flippen (farbige Badges bleiben weiss). */
    .terminal-light .bg-white\/15 { background-color: rgba(55,53,47,0.09) !important; }
    .terminal-light .bg-white\/15.text-white { color: var(--nx-text) !important; }
    .terminal-light .border-white\/\[0\.08\] { border-color: rgba(55,53,47,0.10) !important; }
    @keyframes t-spring-in { 0% { transform: translateY(16px); opacity: 0.5; } 60% { transform: translateY(-4px); opacity: 1; } 100% { transform: translateY(0); } }
    @keyframes t-badge-pop { 0%,100% { transform: scale(1); } 50% { transform: scale(1.08); } }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.05ms !important; } }
  </style>

  @php
    $pageContext = ($contextType && $contextId) ? $this->getContextBreadcrumb() : null;
  @endphp

  <!-- Fullscreen backdrop -->
  <div x-show="fullscreen" x-cloak x-transition:enter="transition-opacity duration-300" x-transition:leave="transition-opacity duration-200"
    class="fixed inset-0 bg-black/10" @click="toggleFullscreen()"></div>

  <!-- Single terminal container — status bar always peeks out -->
  <div
    class="terminal-light w-full h-full overflow-hidden flex flex-col relative z-[1]"
    :class="fullscreen ? 'shadow-2xl' : 'border-t border-[var(--t-border-bright)] shadow-[0_-4px_30px_rgba(0,0,0,0.15)]'"
    style="background: linear-gradient(165deg, var(--t-sidebar-from) 0%, var(--t-sidebar-to) 100%)"
    wire:key="terminal-slide"
  >
    <!-- Resize handle — only visible when open, hidden in fullscreen -->
    <div
      x-show="open && !fullscreen"
      @mousedown.prevent="startResize($event)"
      class="h-1 flex-shrink-0 cursor-ns-resize group/resize relative -mb-1 z-10"
    >
      <div class="absolute inset-x-0 top-0 h-px bg-white/5 group-hover/resize:bg-[var(--t-accent)]/50 transition"></div>
      <div class="absolute left-1/2 -translate-x-1/2 top-0 w-10 h-0.5 rounded-full bg-white/10 group-hover/resize:bg-[var(--t-accent)]/50 transition"></div>
    </div>

    <!-- Status bar — always visible (42px), top bar in fullscreen -->
    <div class="relative flex-shrink-0 border-b border-white/[0.08]" wire:key="terminal-statusbar"
    >
    <div
      @click.self="if(!fullscreen) toggle()"
      class="relative flex-shrink-0 px-4 flex items-center gap-1.5 overflow-x-auto scrollbar-none select-none group/bar"
      :class="fullscreen ? 'h-12 border-b border-[var(--t-border)]' : 'h-11 cursor-pointer'"
    >
      {{-- macOS traffic lights --}}
      <div class="flex items-center gap-1.5 mr-1 flex-shrink-0 group/dots" @click.stop>
        <button @click="if(fullscreen) toggleFullscreen(); if(open) toggle()" class="w-3 h-3 rounded-full bg-[#FF5F57] hover:brightness-110 transition cursor-pointer" title="Einfahren"></button>
        <button @click="if(fullscreen) toggleFullscreen(); if(!open) toggle()" class="w-3 h-3 rounded-full bg-[#FEBC2E] hover:brightness-110 transition cursor-pointer" title="Normal"></button>
        <button @click="if(!open) toggle(); if(!fullscreen) toggleFullscreen()" class="w-3 h-3 rounded-full bg-[#28C840] hover:brightness-110 transition cursor-pointer" title="Vollbild"></button>
      </div>

      {{-- App switcher tabs — always visible, click opens terminal + switches app --}}
      <div class="flex items-center gap-0.5 flex-shrink-0">
        <div class="w-px h-4 bg-[var(--t-border)] mr-0.5"></div>
          @if($this->availableApps['activity'])
            @php $activityCount = count($this->contextActivities); @endphp
            <button
              @click.stop="$wire.set('activeApp', 'activity'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'activity'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              <span class="hidden sm:inline">Aktivitäten</span>
              @if($activityCount > 0)
                <span class="min-w-[14px] h-[14px] px-0.5 rounded-full bg-white/5 border border-[var(--t-border)] text-[var(--t-text)] text-[9px] font-bold flex items-center justify-center">{{ $activityCount }}</span>
              @endif
            </button>
          @else
            <button
              @click.stop
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed"
              title="Aktivitäten — nur mit Kontext verfügbar"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              <span class="hidden sm:inline">Aktivitäten</span>
            </button>
          @endif
          @if($this->availableApps['files'])
            @php $filesCount = count($this->contextFiles); @endphp
            <button
              @click.stop="$wire.set('activeApp', 'files'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'files'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
              <span class="hidden sm:inline">Dateien</span>
              @if($filesCount > 0)
                <span class="min-w-[14px] h-[14px] px-0.5 rounded-full bg-white/5 border border-[var(--t-border)] text-[var(--t-text)] text-[9px] font-bold flex items-center justify-center">{{ $filesCount }}</span>
              @endif
            </button>
          @else
            <button
              @click.stop
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed"
              title="Dateien — nur bei Kontext verfügbar"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
              <span class="hidden sm:inline">Dateien</span>
            </button>
          @endif
          @if($this->availableApps['tags'])
            <button
              @click.stop="$wire.set('activeApp', 'tags'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'tags'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5"/></svg>
              <span class="hidden sm:inline">Tags</span>
            </button>
          @else
            <button
              @click.stop
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed"
              title="Tags — nur bei Kontext verfügbar"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5"/></svg>
              <span class="hidden sm:inline">Tags</span>
            </button>
          @endif
          <button
            @click.stop="$wire.set('activeApp', 'feature-request'); if(!open) toggle()"
            class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
            :class="$wire.activeApp === 'feature-request'
              ? 'bg-white/15 text-white'
              : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            title="Feature Request"
          >
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" /></svg>
            <span class="hidden sm:inline">Feature</span>
          </button>
          @if($this->availableApps['time'])
            <button
              @click.stop="$wire.set('activeApp', 'time'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'time'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              <span class="hidden sm:inline">Zeit</span>
            </button>
          @else
            <button
              @click.stop
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed"
              title="Zeit — nur bei Kontext verfügbar"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              <span class="hidden sm:inline">Zeit</span>
            </button>
          @endif
          @if($this->availableApps['okr'])
            <button
              @click.stop="$wire.set('activeApp', 'okr'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'okr'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
              <span class="hidden sm:inline">OKR</span>
            </button>
          @else
            <button
              @click.stop
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed"
              title="OKR — nur bei Kontext verfügbar"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
              <span class="hidden sm:inline">OKR</span>
            </button>
          @endif
          @if($this->availableApps['extrafields'])
            <button
              @click.stop="$wire.call('openExtraFieldsApp'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'extrafields'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
              <span class="hidden sm:inline">Felder</span>
            </button>
          @else
            <button
              @click.stop
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed"
              title="Felder — nur bei Kontext verfügbar"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
              <span class="hidden sm:inline">Felder</span>
            </button>
          @endif

          {{-- Comms Tab --}}
          @if($this->availableApps['comms'])
            <button
              wire:click="$set('activeApp', 'comms')"
              @click.stop="if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === 'comms'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
            >
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
              <span class="hidden sm:inline">Comms</span>
            </button>
          @else
            <button @click.stop class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium text-[var(--t-text-muted)]/30 cursor-not-allowed" title="Comms — nur bei Kontext verfügbar">
              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
              <span class="hidden sm:inline">Comms</span>
            </button>
          @endif

          {{-- Registry-contributed app tabs (TerminalAppRegistry) --}}
          @foreach($this->registryApps as $rkey => $rapp)
            <button
              @click.stop="$wire.set('activeApp', '{{ $rkey }}'); if(!open) toggle()"
              class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
              :class="$wire.activeApp === '{{ $rkey }}'
                ? 'bg-white/15 text-white'
                : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
              title="{{ $rapp->label() }}"
            >
              <span class="hidden sm:inline">{{ $rapp->label() }}</span>
            </button>
          @endforeach

        <div class="w-px h-4 bg-[var(--t-border)] ml-0.5"></div>
      </div>

      {{-- Spacer --}}
      <div class="ml-auto"></div>

      {{-- Algedonic-Signal: jetzt unten in der rechten Leiste (core.context-panel) --}}
    </div>
    </div>{{-- /status bar wrapper --}}

    <!-- Panel Content: Sidebar + Main -->
    <div class="flex-1 min-h-0 flex"
         wire:key="terminal-content">

      <!-- Sidebar (resizable) -->
      <div class="flex-shrink-0 overflow-y-auto overscroll-contain py-2 flex flex-col relative border-r border-white/[0.06]"
           :class="resizingSidebar ? '' : 'transition-[width] duration-0'"
           :style="'width:' + sidebarWidth + 'px'"
           wire:key="terminal-sidebar"
           x-show="$wire.activeApp === 'comms'"
      >
        <!-- Sidebar resize handle -->
        <div
          @mousedown.prevent="startSidebarResize($event)"
          class="absolute top-0 right-0 w-1 h-full cursor-ew-resize group/sresize z-10"
        >
          <div class="absolute inset-y-0 right-0 w-px bg-transparent group-hover/sresize:bg-[var(--t-accent)]/40 transition"></div>
          <div class="absolute top-1/2 -translate-y-1/2 right-0 h-8 w-1 rounded-full bg-transparent group-hover/sresize:bg-[var(--t-accent)]/30 transition"></div>
        </div>


        @include('platform::livewire.partials.terminal-comms-sidebar')

      </div>

      <!-- Main Content Area -->
      <div class="terminal-light flex-1 min-w-0 flex flex-col bg-[var(--ui-surface)]" wire:key="terminal-main">

        {{-- Global context header — always visible as first element --}}
        @include('platform::livewire.partials.terminal-context-header')

          <!-- ═══ App: Aktivitäten ═══ -->
          <div x-show="$wire.activeApp === 'activity'" class="flex-1 min-h-0 flex flex-col">
            @if($this->availableApps['activity'] ?? false)
              <livewire:core.terminal.activity lazy wire:key="t-app-activity"
                :context-type="$this->contextType"
                :context-id="$this->contextId"
                :context-subject="$this->contextSubject"
                :context-source="$this->contextSource"
                :context-url="$this->contextUrl"
                :context-meta="$this->contextMeta" />
            @endif
          </div>

          <!-- ═══ App: Dateien ═══ -->
          <div x-show="$wire.activeApp === 'files'" class="flex-1 min-h-0 flex flex-col">
            @if($this->availableApps['files'] ?? false)
              <livewire:core.terminal.files lazy wire:key="t-app-files"
                :context-type="$this->contextType"
                :context-id="$this->contextId"
                :context-subject="$this->contextSubject"
                :context-source="$this->contextSource"
                :context-url="$this->contextUrl"
                :context-meta="$this->contextMeta" />
            @endif
          </div>
          <!-- ═══ App: Tags ═══ -->
          <div x-show="$wire.activeApp === 'tags'" class="flex-1 min-h-0 flex flex-col">
            @if($this->availableApps['tags'] ?? false)
              <livewire:core.terminal.tags lazy wire:key="t-app-tags"
                :context-type="$this->contextType"
                :context-id="$this->contextId"
                :context-subject="$this->contextSubject"
                :context-source="$this->contextSource"
                :context-url="$this->contextUrl"
                :context-meta="$this->contextMeta" />
            @endif
          </div>

          <!-- ═══ App: Feature Request ═══ -->
          <div x-show="$wire.activeApp === 'feature-request'" class="flex-1 min-h-0 flex flex-col">
            @if($this->availableApps['feature-request'] ?? false)
              <livewire:core.terminal.feature-request lazy wire:key="t-app-feature-request"
                :context-type="$this->contextType"
                :context-id="$this->contextId"
                :context-subject="$this->contextSubject"
                :context-source="$this->contextSource"
                :context-url="$this->contextUrl"
                :context-meta="$this->contextMeta" />
            @endif
          </div>

          <!-- ═══ App: Zeit ═══ -->
          <div x-show="$wire.activeApp === 'time'" class="flex-1 min-h-0 flex flex-col">
            @if($this->availableApps['time'] ?? false)
              <livewire:core.terminal.time lazy wire:key="t-app-time"
                :context-type="$this->contextType"
                :context-id="$this->contextId"
                :context-subject="$this->contextSubject"
                :context-source="$this->contextSource"
                :context-url="$this->contextUrl"
                :context-meta="$this->contextMeta" />
            @endif
          </div>

          <!-- ═══ App: OKR ═══ -->
          <div x-show="$wire.activeApp === 'okr'" class="flex-1 min-h-0 flex flex-col">
            @if($this->availableApps['okr'] ?? false)
              <livewire:core.terminal.okr lazy wire:key="t-app-okr"
                :context-type="$this->contextType"
                :context-id="$this->contextId"
                :context-subject="$this->contextSubject"
                :context-source="$this->contextSource"
                :context-url="$this->contextUrl"
                :context-meta="$this->contextMeta" />
            @endif
          </div>

          {{-- ═══ Registry-contributed app panels (TerminalAppRegistry) ═══ --}}
          @foreach($this->registryApps as $rkey => $rapp)
            <div x-show="$wire.activeApp === '{{ $rkey }}'" class="flex-1 min-h-0 flex flex-col" wire:key="t-app-{{ $rkey }}-wrap">
              @livewire($rapp->livewireComponent(), [
                'contextType'    => $this->contextType,
                'contextId'      => $this->contextId,
                'contextSubject' => $this->contextSubject,
                'contextSource'  => $this->contextSource,
                'contextUrl'     => $this->contextUrl,
                'contextMeta'    => $this->contextMeta,
              ], 't-app-'.$rkey)
            </div>
          @endforeach

          <!-- ═══ App: ExtraFields ═══ -->
          <div x-show="$wire.activeApp === 'extrafields'" class="flex-1 min-h-0 flex flex-col overflow-y-auto">
            @if($this->availableApps['extrafields'] ?? false)
              <livewire:core.terminal.extra-fields
                lazy
                wire:key="t-app-extrafields"
                :ef-context-type="$this->efContextType"
                :ef-context-id="$this->efContextId"
                :context-breadcrumb="($this->efContextType && $this->efContextId) ? ($this->getContextBreadcrumb($this->efContextType, $this->efContextId) ?? []) : []" />
            @endif
          </div>


          <!-- ═══ App: Comms ═══ -->
          {{-- .visible: nur pollen, wenn Comms wirklich sichtbar ist. Sonst rendert
               sich (bei x-show/display:none) die GANZE Shell alle 5s komplett neu. --}}
          <div x-show="$wire.activeApp === 'comms'" class="flex-1 min-h-0 flex flex-col relative"
               wire:poll.10s.visible="refreshTimelines">
            {{-- Timeline is ALWAYS rendered --}}
            @include('platform::livewire.partials.terminal-comms-timeline')

            {{-- Settings Overlay (modal over timeline) --}}
            @if($commsShowSettings)
              <div class="absolute inset-0 z-30 flex flex-col bg-[var(--t-glass-surface)]/95 backdrop-blur-md">
                @include('platform::livewire.partials.terminal-comms-settings')
              </div>
            @endif
          </div>

      </div>
    </div>
  </div>

  <script>
    document.addEventListener('alpine:init', () => {
    Alpine.data('terminalShell', () => {
      const STORAGE_KEY = 'terminal_panel_height';
      const SIDEBAR_STORAGE_KEY = 'terminal_sidebar_width';
      const MIN_HEIGHT = 200;
      const MAX_RATIO = 0.7; // max 70% of viewport
      const DEFAULT_HEIGHT = 320;
      const MIN_SIDEBAR = 180;
      const MAX_SIDEBAR = 400;
      const DEFAULT_SIDEBAR = 240;

      return {
        panelHeight: parseInt(localStorage.getItem(STORAGE_KEY)) || DEFAULT_HEIGHT,
        sidebarWidth: parseInt(localStorage.getItem(SIDEBAR_STORAGE_KEY)) || DEFAULT_SIDEBAR,
        fullscreen: localStorage.getItem('terminal_fullscreen') === '1',
        resizing: false,
        resizingSidebar: false,
        _startY: 0,
        _startH: 0,

        toggleFullscreen() {
          const el = this.$el.querySelector('[wire\\:key="terminal-slide"]');
          if (!this.fullscreen && el) {
            el.style.transform = 'scale(0.98)';
            el.style.opacity = '0.85';
            el.style.transition = 'transform 300ms cubic-bezier(0.33,1,0.68,1), opacity 300ms ease-out';
            requestAnimationFrame(() => {
              this.fullscreen = true;
              localStorage.setItem('terminal_fullscreen', '1');
              if (Alpine?.store('ui') && !Alpine.store('ui').m('terminal', 'open')) {
                Alpine.store('ui').mSet('terminal', 'open', true);
              }
              document.body.style.overflow = 'hidden';
              requestAnimationFrame(() => {
                el.style.transform = 'scale(1)';
                el.style.opacity = '1';
                setTimeout(() => { el.style.transition = ''; el.style.transform = ''; el.style.opacity = ''; }, 320);
              });
            });
          } else if (el) {
            el.style.transform = 'scale(1)';
            el.style.opacity = '1';
            el.style.transition = 'transform 250ms ease-in, opacity 250ms ease-in';
            requestAnimationFrame(() => {
              el.style.transform = 'scale(0.98) translateY(8px)';
              el.style.opacity = '0.85';
              setTimeout(() => {
                this.fullscreen = false;
                localStorage.setItem('terminal_fullscreen', '0');
                document.body.style.overflow = '';
                el.style.transition = '';
                el.style.transform = '';
                el.style.opacity = '';
              }, 260);
            });
          } else {
            this.fullscreen = !this.fullscreen;
            localStorage.setItem('terminal_fullscreen', this.fullscreen ? '1' : '0');
            if (this.fullscreen) {
              if (Alpine?.store('ui') && !Alpine.store('ui').m('terminal', 'open')) {
                Alpine.store('ui').mSet('terminal', 'open', true);
              }
              document.body.style.overflow = 'hidden';
            } else {
              document.body.style.overflow = '';
            }
          }
          this.$nextTick(() => {
            const c = this.$refs.body;
            if (c) c.scrollTop = c.scrollHeight;
          });
        },

        get open(){ return Alpine?.store('ui')?.m('terminal', 'open') ?? false; },
        toggle(){ Alpine?.store('ui')?.mToggle('terminal', 'open'); },

        startResize(e) {
          this.resizing = true;
          this._startY = e.clientY;
          this._startH = this.panelHeight;

          const onMove = (ev) => {
            const delta = this._startY - ev.clientY; // dragging up = bigger
            const maxH = Math.floor(window.innerHeight * MAX_RATIO);
            this.panelHeight = Math.max(MIN_HEIGHT, Math.min(maxH, this._startH + delta));
          };

          const onUp = () => {
            this.resizing = false;
            localStorage.setItem(STORAGE_KEY, this.panelHeight);
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            this.$nextTick(() => {
              const c = this.$refs.body;
              if (c) c.scrollTop = c.scrollHeight;
            });
          };

          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUp);
        },

        startSidebarResize(e) {
          this.resizingSidebar = true;
          const startX = e.clientX;
          const startW = this.sidebarWidth;

          const onMove = (ev) => {
            const delta = ev.clientX - startX;
            this.sidebarWidth = Math.max(MIN_SIDEBAR, Math.min(MAX_SIDEBAR, startW + delta));
          };

          const onUp = () => {
            this.resizingSidebar = false;
            localStorage.setItem(SIDEBAR_STORAGE_KEY, this.sidebarWidth);
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
          };

          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUp);
        },

        init(){
          // Clamp stored height to current viewport
          const maxH = Math.floor(window.innerHeight * MAX_RATIO);
          if (this.panelHeight > maxH) this.panelHeight = maxH;
          if (this.panelHeight < MIN_HEIGHT) this.panelHeight = DEFAULT_HEIGHT;

          // Clamp stored sidebar width
          if (this.sidebarWidth > MAX_SIDEBAR) this.sidebarWidth = MAX_SIDEBAR;
          if (this.sidebarWidth < MIN_SIDEBAR) this.sidebarWidth = DEFAULT_SIDEBAR;

          // Restore fullscreen body lock
          if (this.fullscreen) {
            document.body.style.overflow = 'hidden';
            if (Alpine?.store('ui') && !Alpine.store('ui').m('terminal', 'open')) {
              Alpine.store('ui').mSet('terminal', 'open', true);
            }
          }

        },
      };
    });
    });
  </script>
</div>
