<div
  x-data="terminalShell()"
  x-init="init()"
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
    $contextItems = collect($this->channels['context_groups'])->flatMap(fn($g) => $g['items']);
    $allChannels = collect($this->channels['dms'])->map(fn($c) => array_merge($c, ['_type' => 'dm']))
      ->merge(collect($this->channels['channels'])->map(fn($c) => array_merge($c, ['_type' => 'channel'])))
      ->merge($contextItems->map(fn($c) => array_merge($c, ['_type' => 'context'])))
      ->filter(fn($c) => $c['unread'] > 0)
      ->sortByDesc('unread');
    $totalUnread = $allChannels->sum('unread');
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

      {{-- Unread badge --}}
      @if($totalUnread > 0)
        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-gradient-to-r from-rose-500 to-pink-500 text-white text-[10px] font-bold flex items-center justify-center shadow-lg shadow-rose-500/30 animate-[t-badge-pop_2s_ease-in-out_infinite]">{{ $totalUnread > 99 ? '99+' : $totalUnread }}</span>
      @endif

      {{-- App switcher tabs — always visible, click opens terminal + switches app --}}
      <div class="flex items-center gap-0.5 flex-shrink-0">
        <div class="w-px h-4 bg-[var(--t-border)] mr-0.5"></div>
        <button
          @click.stop="$wire.set('activeApp', 'chat'); if(!open) toggle()"
          class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
          :class="$wire.activeApp === 'chat'
            ? 'bg-white/15 text-white'
            : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
        >
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
          <span class="hidden sm:inline">Chat</span>
        </button>
        <button
          @click.stop="$wire.set('activeApp', 'agenda'); $wire.dispatch('terminal-agenda-open-my-day'); if(!open) toggle()"
          class="flex items-center gap-1 px-2 py-1 rounded text-[11px] font-medium transition"
          :class="$wire.activeApp === 'agenda'
            ? 'bg-white/15 text-white'
            : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5'"
        >
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
          <span class="hidden sm:inline">Agenda</span>
        </button>
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
              title="Aktivitäten — nur bei Kontext-Channels verfügbar"
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

      @if($allChannels->isNotEmpty())
        <div class="w-px h-4 bg-[var(--t-border)] flex-shrink-0"></div>
      @endif

      {{-- Unread channel pills — click opens that channel + terminal --}}
      @foreach($allChannels as $preview)
        <button
          wire:click="openChannel({{ $preview['id'] }})"
          @click.stop="if(!open) toggle()"
          class="flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] flex-shrink-0 bg-white/5 border border-[var(--t-border)] text-[var(--t-text)] font-semibold hover:bg-white/10 transition cursor-pointer"
        >
          @if($preview['_type'] === 'dm')
            <div class="w-4 h-4 rounded-full bg-emerald-500/20 flex items-center justify-center text-[8px] font-bold flex-shrink-0 overflow-hidden text-emerald-600">
              @if(! empty($preview['avatar']))
                <img src="{{ $preview['avatar'] }}" alt="" class="w-full h-full object-cover">
              @else
                {{ $preview['initials'] ?? '?' }}
              @endif
            </div>
          @elseif($preview['_type'] === 'context')
            <span class="text-[10px]">{{ $preview['context_icon'] ?? '📎' }}</span>
          @else
            <span class="text-[10px] text-amber-500">{{ $preview['icon'] ?? '#' }}</span>
          @endif
          <span class="truncate max-w-[80px]">{{ $preview['name'] }}</span>
          <span class="min-w-[14px] h-[14px] px-0.5 rounded-full bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center">{{ $preview['unread'] > 9 ? '9+' : $preview['unread'] }}</span>
          @if($preview['last_message'])
            <span class="text-[10px] text-[var(--t-text-muted)] truncate max-w-[120px] hidden sm:inline font-normal">{{ $preview['last_message'] }}</span>
          @endif
        </button>
      @endforeach

      {{-- Spacer to push unread pills left --}}
      <div class="ml-auto"></div>

      {{-- Algedonic-Signal — Eskalation direkt an die oberste Ebene (Stafford Beer);
           rechts in der Leiste, toggelt das Terminal NICHT --}}
      <button x-data
        @click.stop="$dispatch('open-modal-algedonic')"
        class="flex items-center justify-center w-7 h-7 rounded flex-shrink-0 text-[color:var(--nx-warning)] hover:text-white hover:bg-[color:var(--nx-warning)] transition"
        title="Algedonic-Signal — direkt an die oberste Ebene (Stafford Beer)">
        @svg('heroicon-o-bell-alert', 'w-4 h-4')
      </button>
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
           x-data="{
             searchQuery: '',
             searchResults: [],
             searching: false,
             _searchTimeout: null,
             doSearch() {
               clearTimeout(this._searchTimeout);
               const q = this.searchQuery.trim();
               if (q.length < 2) { this.searchResults = []; this.searching = false; return; }
               this.searching = true;
               this._searchTimeout = setTimeout(() => {
                 $wire.searchMessages(q).then(r => { this.searchResults = r; this.searching = false; });
               }, 300);
             },
             clearSearch() {
               this.searchQuery = '';
               this.searchResults = [];
               this.searching = false;
             },
           }"
      >
        <!-- Sidebar resize handle -->
        <div
          @mousedown.prevent="startSidebarResize($event)"
          class="absolute top-0 right-0 w-1 h-full cursor-ew-resize group/sresize z-10"
        >
          <div class="absolute inset-y-0 right-0 w-px bg-transparent group-hover/sresize:bg-[var(--t-accent)]/40 transition"></div>
          <div class="absolute top-1/2 -translate-y-1/2 right-0 h-8 w-1 rounded-full bg-transparent group-hover/sresize:bg-[var(--t-accent)]/30 transition"></div>
        </div>

        <!-- ═══ Sidebar: Chat (Channels) ═══ -->
        <div x-show="$wire.activeApp === 'chat'" class="flex-1 min-h-0 flex flex-col">

        <!-- Search field -->
        <div class="px-2 mb-2">
          <div class="relative">
            <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
            <input
              type="text"
              x-model="searchQuery"
              @input="doSearch()"
              @keydown.escape="clearSearch()"
              placeholder="Suchen…"
              class="w-full text-[11px] pl-7 pr-6 py-1.5 rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:border-[var(--t-accent)]/40 outline-none transition"
            >
            <button x-show="searchQuery.length > 0" x-cloak @click="clearSearch()" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
          </div>
        </div>

        <!-- Search results overlay -->
        <template x-if="searchQuery.trim().length >= 2">
          <div class="flex-1 min-h-0 overflow-y-auto px-2">
            <template x-if="searching">
              <div class="px-1.5 py-4 text-center text-[10px] text-[var(--t-text-muted)]">Suche…</div>
            </template>
            <template x-if="!searching && searchResults.length === 0">
              <div class="px-1.5 py-4 text-center text-[10px] text-[var(--t-text-muted)]">Keine Ergebnisse</div>
            </template>
            <template x-if="!searching && searchResults.length > 0">
              <div class="space-y-px">
                <template x-for="result in searchResults" :key="result.id">
                  <button
                    @click="
                      const msgId = result.id;
                      clearSearch();
                      $wire.openChannel(result.channel_id).then(() => {
                        setTimeout(() => {
                          const el = document.getElementById('msg-' + msgId);
                          if(el) {
                            el.scrollIntoView({behavior:'smooth',block:'center'});
                            el.classList.add('!bg-amber-500/15');
                            setTimeout(() => el.classList.remove('!bg-amber-500/15'), 2000);
                          }
                        }, 150);
                      });
                    "
                    class="w-full text-left px-1.5 py-2 rounded-md hover:bg-white/5 transition"
                  >
                    <div class="flex items-center gap-1.5 text-[10px] text-[var(--t-text-muted)]">
                      <span x-text="result.channel_name" class="font-medium truncate"></span>
                      <span>&middot;</span>
                      <span x-text="result.date"></span>
                      <span x-text="result.time"></span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                      <span class="text-[11px] font-medium text-[var(--t-text)]" x-text="result.user_name"></span>
                    </div>
                    <div class="text-[11px] text-[var(--t-text-muted)] truncate mt-0.5" x-text="result.snippet"></div>
                  </button>
                </template>
              </div>
            </template>
          </div>
        </template>

        <!-- Channel lists (hidden during search) -->
        <div x-show="searchQuery.trim().length < 2" class="flex-1 min-h-0 overflow-y-auto">

        <!-- Bookmarks toggle -->
        <div class="px-2 mb-2" x-data="{ showBookmarks: false, bookmarks: [], loadingBookmarks: false }">
          <button
            @click="
              showBookmarks = !showBookmarks;
              if (showBookmarks && bookmarks.length === 0) {
                loadingBookmarks = true;
                $wire.getBookmarks().then(r => { bookmarks = r; loadingBookmarks = false; });
              }
            "
            class="w-full flex items-center gap-1.5 px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition"
          >
            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2c-.22 0-.44.03-.65.09L5.47 3.6a2.5 2.5 0 00-1.8 2.4v9.5a2 2 0 003.32 1.5L10 14.5l3.01 2.5A2 2 0 0016.33 15.5V6a2.5 2.5 0 00-1.8-2.4l-3.88-1.51A1.75 1.75 0 0010 2z"/></svg>
            <span>Lesezeichen</span>
            <svg class="w-3 h-3 ml-auto transition-transform duration-150" :class="showBookmarks ? '' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div x-show="showBookmarks" x-collapse class="mt-0.5 space-y-px">
            <template x-if="loadingBookmarks">
              <div class="px-1.5 py-2 text-[10px] text-[var(--t-text-muted)] text-center">Laden…</div>
            </template>
            <template x-if="!loadingBookmarks && bookmarks.length === 0">
              <div class="px-1.5 py-2 text-[10px] text-[var(--t-text-muted)]">Keine Lesezeichen</div>
            </template>
            <template x-if="!loadingBookmarks && bookmarks.length > 0">
              <div class="space-y-px">
                <template x-for="bm in bookmarks" :key="bm.id">
                  <button
                    @click="
                      const msgId = bm.message_id;
                      $wire.openChannel(bm.channel_id).then(() => {
                        setTimeout(() => {
                          const el = document.getElementById('msg-' + msgId);
                          if(el) {
                            el.scrollIntoView({behavior:'smooth',block:'center'});
                            el.classList.add('!bg-amber-500/15');
                            setTimeout(() => el.classList.remove('!bg-amber-500/15'), 2000);
                          }
                        }, 150);
                      });
                    "
                    class="w-full text-left px-1.5 py-1.5 rounded-md hover:bg-white/5 transition"
                  >
                    <div class="flex items-center gap-1 text-[10px] text-[var(--t-text-muted)]">
                      <span x-text="bm.channel_name" class="font-medium truncate"></span>
                      <span>&middot;</span>
                      <span x-text="bm.date"></span>
                    </div>
                    <div class="text-[11px] text-[var(--t-text)] truncate mt-0.5" x-text="bm.body_snippet"></div>
                  </button>
                </template>
              </div>
            </template>
          </div>
        </div>

        <!-- New Chat / Channel buttons -->
        <div class="px-2 mb-2 flex gap-1">
          <button
            @click.stop="$dispatch('terminal-show-new-dm')"
            class="flex-1 text-[10px] px-2 py-1 rounded border border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:border-[var(--t-border)] transition"
          >+ Chat</button>
          <button
            @click.stop="$dispatch('terminal-show-new-channel')"
            class="flex-1 text-[10px] px-2 py-1 rounded border border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:border-[var(--t-border)] transition"
          >+ Channel</button>
        </div>

        <!-- Chats (DMs) Section -->
        <div class="px-2 mb-3" x-data="{ chatsOpen: true }">
          <button @click="chatsOpen = !chatsOpen" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
            <span>Chats</span>
            <svg class="w-3 h-3 transition-transform duration-150" :class="chatsOpen ? '' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div x-show="chatsOpen" x-collapse class="mt-0.5 space-y-px">
            @forelse($this->channels['dms'] as $dm)
              <button
                wire:click="openChannel({{ $dm['id'] }})"
                class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-xs transition
                  {{ $channelId === $dm['id'] ? 'bg-[var(--t-accent)]/10 text-[var(--t-accent)]' : 'text-[var(--t-text)] hover:bg-white/5' }}"
              >
                <div class="relative flex-shrink-0">
                  <div class="w-5 h-5 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold overflow-hidden">
                    @if(! empty($dm['avatar']))
                      <img src="{{ $dm['avatar'] }}" alt="" class="w-full h-full object-cover">
                    @else
                      {{ $dm['initials'] ?? '?' }}
                    @endif
                  </div>
                  @if(! empty($dm['other_user_id']) && in_array($dm['other_user_id'], $this->onlineUserIds))
                    <div class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-1 ring-[var(--t-glass)]"></div>
                  @endif
                </div>
                <span class="truncate flex-1 text-left">{{ $dm['name'] }}</span>
                @if($dm['unread'] > 0)
                  <span class="w-4 h-4 rounded-full bg-[var(--t-accent)] text-white text-[9px] flex items-center justify-center flex-shrink-0">{{ $dm['unread'] > 9 ? '9+' : $dm['unread'] }}</span>
                @endif
              </button>
            @empty
              <div class="px-1.5 py-2 text-[10px] text-[var(--t-text-muted)]">Noch keine Chats</div>
            @endforelse
          </div>
        </div>

        <!-- Context Channels — grouped by type -->
        @foreach($this->channels['context_groups'] as $groupKey => $group)
        <div class="px-2 mb-3" x-data="{ open: true }">
          <button @click="open = !open" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
            <span class="flex items-center gap-1">
              <span class="text-[10px]">{{ $group['icon'] }}</span>
              {{ $group['label'] }}
            </span>
            <svg class="w-3 h-3 transition-transform duration-150" :class="open ? '' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div x-show="open" x-collapse class="mt-0.5 space-y-px">
            @foreach($group['items'] as $ctx)
              <button
                wire:click="openChannel({{ $ctx['id'] }})"
                class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-xs transition
                  {{ $channelId === $ctx['id'] ? 'bg-[var(--t-accent)]/10 text-[var(--t-accent)]' : 'text-[var(--t-text)] hover:bg-white/5' }}"
              >
                <span class="truncate flex-1 text-left">{{ $ctx['name'] }}</span>
                @if($ctx['unread'] > 0)
                  <span class="w-4 h-4 rounded-full bg-[var(--t-accent)] text-white text-[9px] flex items-center justify-center flex-shrink-0">{{ $ctx['unread'] > 9 ? '9+' : $ctx['unread'] }}</span>
                @endif
              </button>
            @endforeach
          </div>
        </div>
        @endforeach

        <!-- Channels Section -->
        <div class="px-2" x-data="{ channelsOpen: true }">
          <button @click="channelsOpen = !channelsOpen" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
            <span>Channels</span>
            <svg class="w-3 h-3 transition-transform duration-150" :class="channelsOpen ? '' : '-rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
          </button>
          <div x-show="channelsOpen" x-collapse class="mt-0.5 space-y-px">
            @forelse($this->channels['channels'] as $ch)
              <button
                wire:click="openChannel({{ $ch['id'] }})"
                class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-xs transition
                  {{ $channelId === $ch['id'] ? 'bg-[var(--t-accent)]/10 text-[var(--t-accent)]' : 'text-[var(--t-text)] hover:bg-white/5' }}"
              >
                <span class="text-[var(--t-text-muted)]">{{ $ch['icon'] ?? '#' }}</span>
                <span class="truncate flex-1 text-left">{{ $ch['name'] }}</span>
                @if($ch['unread'] > 0)
                  <span class="w-4 h-4 rounded-full bg-[var(--t-accent)] text-white text-[9px] flex items-center justify-center flex-shrink-0">{{ $ch['unread'] > 9 ? '9+' : $ch['unread'] }}</span>
                @endif
              </button>
            @empty
              <div class="px-1.5 py-2 text-[10px] text-[var(--t-text-muted)]">Noch keine Channels</div>
            @endforelse
          </div>
        </div>

        </div>{{-- end channel lists wrapper --}}

        </div>{{-- end sidebar: chat --}}

        <!-- ═══ Sidebar: Aktivitäten (now managed by child component) ═══ -->

        <!-- ═══ Sidebar: Tags (now managed by child component) ═══ -->

        <!-- ═══ Sidebar: Zeit (now managed by child component) ═══ -->

        <!-- ═══ Sidebar: Dateien (now managed by child component) ═══ -->

        <!-- ═══ Sidebar: ExtraFields (now managed by child component) ═══ -->

        <!-- ═══ Sidebar: Agenda (Nav-Kind: Liste + Mein Tag + Anlegen) ═══ -->
        <div x-show="$wire.activeApp === 'agenda'" class="flex-1 min-h-0 flex flex-col">
          <livewire:core.terminal.agenda-nav lazy wire:key="t-nav-agenda" />
        </div>

        @include('platform::livewire.partials.terminal-comms-sidebar')

      </div>

      <!-- Main Content Area — keyed per channel so editor + messages fully rebuild -->
      <div class="terminal-light flex-1 min-w-0 flex flex-col bg-[var(--ui-surface)]" wire:key="terminal-main">

        {{-- Global context header — always visible as first element --}}
        @include('platform::livewire.partials.terminal-context-header')

        @if($this->activeChannel)
          <!-- Chat Header (only visible in chat app) -->
          <div x-show="$wire.activeApp === 'chat'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->activeChannel['type'] === 'dm')
              @php
                $dmOther = collect($this->activeChannel['members'])->first(fn($m) => $m['id'] !== auth()->id());
              @endphp
              <div class="relative flex-shrink-0">
                <div class="w-6 h-6 rounded-lg bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[10px] font-semibold overflow-hidden">
                  @if(! empty($this->activeChannel['avatar']))
                    <img src="{{ $this->activeChannel['avatar'] }}" alt="" class="w-full h-full object-cover">
                  @else
                    {{ $this->activeChannel['initials'] ?? '?' }}
                  @endif
                </div>
                @if($dmOther && in_array($dmOther['id'], $this->onlineUserIds))
                  <div class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-1 ring-[var(--t-glass)]"></div>
                @endif
              </div>
              <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $this->activeChannel['name'] }}</span>
              @if($dmOther && in_array($dmOther['id'], $this->onlineUserIds))
                <span class="text-[10px] text-emerald-500 font-medium">online</span>
              @endif
            @elseif($this->activeChannel['type'] === 'context' && ! empty($this->activeChannel['context']))
              <span class="text-[14px]">{{ $this->activeChannel['context']['icon'] }}</span>
              <div class="flex flex-col leading-tight">
                @php $contextTitle = $this->activeChannel['name'] ?: $this->activeChannel['context']['title']; @endphp
                @if(! empty($this->activeChannel['context_url']))
                  <a href="{{ $this->activeChannel['context_url'] }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition" title="Zum Kontext springen">
                    {{ $contextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $contextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--t-text-muted)]">Kontext-Diskussion</span>
              </div>
            @else
              <span class="text-[var(--t-text-muted)] font-bold text-[14px]">{{ $this->activeChannel['icon'] ?? '#' }}</span>
              <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $this->activeChannel['name'] ?? 'Kontext' }}</span>
            @endif
            @if(! empty($this->activeChannel['members']))
              <span class="text-[var(--t-text-muted)]">&middot;</span>
              @php $isManageable = in_array($this->activeChannel['type'], ['channel', 'context']); @endphp
              <{{ $isManageable ? 'button' : 'div' }}
                @if($isManageable) @click.stop="$dispatch('terminal-show-members')" @endif
                class="flex items-center gap-1.5 {{ $isManageable ? 'cursor-pointer hover:opacity-80' : '' }} transition"
                @if($isManageable) title="Mitglieder verwalten" @endif
              >
                {{-- Avatar stack --}}
                <div class="flex -space-x-1.5">
                  @foreach(array_slice($this->activeChannel['members'], 0, 5) as $member)
                    <div class="w-5 h-5 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[8px] font-semibold flex-shrink-0 overflow-hidden ring-1 ring-[var(--t-glass)]" title="{{ $member['name'] }}">
                      @if(! empty($member['avatar']))
                        <img src="{{ $member['avatar'] }}" alt="" class="w-full h-full object-cover">
                      @else
                        {{ $member['initials'] }}
                      @endif
                    </div>
                  @endforeach
                  @if($this->activeChannel['member_count'] > 5)
                    <div class="w-5 h-5 rounded-full bg-[var(--t-text-muted)]/10 text-[var(--t-text-muted)] flex items-center justify-center text-[8px] font-semibold flex-shrink-0 ring-1 ring-[var(--t-glass)]">+{{ $this->activeChannel['member_count'] - 5 }}</div>
                  @endif
                </div>
                {{-- Names --}}
                <span class="text-[10px] text-[var(--t-text-muted)] truncate max-w-[200px]">
                  @if($this->activeChannel['member_count'] <= 3)
                    {{ implode(', ', array_map(fn($m) => $m['name'], $this->activeChannel['members'])) }}
                  @else
                    {{ implode(', ', array_map(fn($m) => $m['name'], array_slice($this->activeChannel['members'], 0, 2))) }} +{{ $this->activeChannel['member_count'] - 2 }}
                  @endif
                </span>
              </{{ $isManageable ? 'button' : 'div' }}>
            @endif

            {{-- Channel actions (pins / delete / leave / context actions) --}}
            <div class="ml-auto flex items-center gap-1">
              {{-- Pins button --}}
              @if(($this->activeChannel['pin_count'] ?? 0) > 0)
                <button
                  @click.stop="$dispatch('terminal-show-pins')"
                  class="flex items-center gap-1 text-[10px] text-[var(--t-text-muted)] hover:text-[var(--t-accent)] transition px-1.5 py-0.5 rounded hover:bg-[var(--t-accent)]/10"
                  title="Gepinnte Nachrichten"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                  <span class="min-w-[14px] h-[14px] px-0.5 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] text-[9px] font-bold flex items-center justify-center">{{ $this->activeChannel['pin_count'] }}</span>
                </button>
                <div class="w-px h-4 bg-[var(--t-border)]/40"></div>
              @endif
              {{-- Context channel: tagging button --}}
              @if(! empty($this->activeChannel['context']))
                <button
                  wire:click="openTagsApp"
                  class="text-[10px] text-[var(--t-text-muted)] hover:text-[var(--t-accent)] transition px-1.5 py-0.5 rounded hover:bg-[var(--t-accent)]/10"
                  title="Tags & Farben"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.5 3A2.5 2.5 0 003 5.5v2.879a2.5 2.5 0 00.732 1.767l6.5 6.5a2.5 2.5 0 003.536 0l2.878-2.878a2.5 2.5 0 000-3.536l-6.5-6.5A2.5 2.5 0 008.38 3H5.5zM6 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                </button>
                <div class="w-px h-4 bg-[var(--t-border)]/40"></div>
                <button
                  wire:click="deleteChannel"
                  wire:confirm="Kontext-Diskussion löschen? Kann jederzeit neu erstellt werden."
                  class="text-[10px] text-[var(--t-text-muted)] hover:text-red-500 transition px-1.5 py-0.5 rounded hover:bg-red-500/10"
                  title="Diskussion löschen"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                </button>
              @endif
              @if($this->activeChannel['type'] === 'channel')
                @if(! empty($this->activeChannel['can_delete']))
                  <button
                    wire:click="deleteChannel"
                    wire:confirm="Channel und alle Nachrichten unwiderruflich loschen?"
                    class="text-[10px] text-[var(--t-text-muted)] hover:text-red-500 transition px-1.5 py-0.5 rounded hover:bg-red-500/10"
                    title="Channel loschen"
                  >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                  </button>
                @else
                  <button
                    wire:click="leaveChannel"
                    wire:confirm="Channel verlassen?"
                    class="text-[10px] text-[var(--t-text-muted)] hover:text-amber-600 transition px-1.5 py-0.5 rounded hover:bg-amber-500/10"
                    title="Channel verlassen"
                  >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M19 10a.75.75 0 00-.75-.75H8.704l1.048-.943a.75.75 0 10-1.004-1.114l-2.5 2.25a.75.75 0 000 1.114l2.5 2.25a.75.75 0 101.004-1.114l-1.048-.943h9.546A.75.75 0 0019 10z" clip-rule="evenodd"/></svg>
                  </button>
                @endif
              @elseif($this->activeChannel['type'] === 'dm')
                <button
                  wire:click="deleteChannel"
                  wire:confirm="Chat ausblenden? Die Nachrichten bleiben fur den anderen Teilnehmer erhalten."
                  class="text-[10px] text-[var(--t-text-muted)] hover:text-red-500 transition px-1.5 py-0.5 rounded hover:bg-red-500/10"
                  title="Chat ausblenden"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
              @endif
            </div>
          </div>

          {{-- Activity, Tags, Time, OKR headers consolidated into global context header --}}


          {{-- Comms has no separate app header — global context header is sufficient,
               thread header in timeline shows thread-specific info --}}

          <!-- ═══ App: Agenda-Header → jetzt in core.terminal.agenda (Kind) ═══ -->

          <!-- ═══ App: Chat ═══ -->
          <div x-show="$wire.activeApp === 'chat'" class="flex-1 min-h-0 flex flex-col">
            <livewire:core.terminal.chat :channelId="$channelId" lazy wire:key="t-app-chat" />
          </div>
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
              ], key: 't-app-'.$rkey)
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

        @else
          <!-- No channel selected (only for non-agenda apps) -->
          <div x-show="$wire.activeApp !== 'agenda' && $wire.activeApp !== 'comms'" class="flex-1 flex items-center justify-center text-[var(--t-text-muted)] text-sm">
            <div class="text-center">
              <div class="text-3xl mb-3 opacity-20">💬</div>
              <p class="font-medium">Willkommen im Terminal</p>
              <p class="text-xs text-[var(--t-text-muted)]/60 mt-1">Starte einen Chat oder tritt einem Channel bei.</p>
            </div>
          </div>
        @endif

          <!-- ═══ App: Comms ═══ -->
          <div x-show="$wire.activeApp === 'comms'" class="flex-1 min-h-0 flex flex-col relative"
               wire:poll.5s="refreshTimelines">
            {{-- Timeline is ALWAYS rendered --}}
            @include('platform::livewire.partials.terminal-comms-timeline')

            {{-- Settings Overlay (modal over timeline) --}}
            @if($commsShowSettings)
              <div class="absolute inset-0 z-30 flex flex-col bg-[var(--t-glass-surface)]/95 backdrop-blur-md">
                @include('platform::livewire.partials.terminal-comms-settings')
              </div>
            @endif
          </div>

          <!-- ═══ App: Agenda ═══ -->
          <div x-show="$wire.activeApp === 'agenda'" class="flex-1 min-h-0 flex flex-col">
            <livewire:core.terminal.agenda lazy wire:key="t-app-agenda" />
          </div>
      </div>
    </div>
  </div>

  <!-- New DM Modal -->
  <div
    wire:ignore
    x-data="{ showNewDm: false, members: [] }"
    x-on:terminal-show-new-dm.window="showNewDm = true; $wire.getTeamMembers().then(r => members = r)"
    x-show="showNewDm"
    x-cloak
    class="terminal-light fixed inset-0 z-[70] flex items-center justify-center bg-black/30"
    @click.self="showNewDm = false"
    @keydown.escape.window="showNewDm = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--t-border)] w-80 max-h-96 overflow-hidden" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--t-border)]/60">
        <h3 class="text-sm font-medium text-[var(--t-text)]">Neuer Chat</h3>
      </div>
      <div class="overflow-y-auto max-h-72">
        <template x-for="member in members" :key="member.id">
          <button
            @click="$wire.openDm(member.id); showNewDm = false"
            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--t-text)] hover:bg-white/5 transition"
          >
            <div class="w-7 h-7 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[10px] font-semibold flex-shrink-0 overflow-hidden">
              <template x-if="member.avatar">
                <img :src="member.avatar" alt="" class="w-full h-full object-cover">
              </template>
              <template x-if="!member.avatar">
                <span x-text="member.initials"></span>
              </template>
            </div>
            <span x-text="member.name"></span>
          </button>
        </template>
        <template x-if="members.length === 0">
          <div class="px-4 py-6 text-center text-xs text-[var(--t-text-muted)]">Keine Team-Mitglieder gefunden</div>
        </template>
      </div>
    </div>
  </div>

  <!-- New Channel Modal -->
  <div
    wire:ignore
    x-data="{ showNewChannel: false, channelName: '', channelDesc: '', members: [], selectedIds: [] }"
    x-on:terminal-show-new-channel.window="showNewChannel = true; channelName = ''; channelDesc = ''; selectedIds = []; $wire.getTeamMembers().then(r => members = r)"
    x-show="showNewChannel"
    x-cloak
    class="terminal-light fixed inset-0 z-[70] flex items-center justify-center bg-black/30"
    @click.self="showNewChannel = false"
    @keydown.escape.window="showNewChannel = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--t-border)] w-80 overflow-hidden" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--t-border)]/60">
        <h3 class="text-sm font-medium text-[var(--t-text)]">Neuer Channel</h3>
      </div>
      <div class="px-4 py-3 space-y-3">
        <div>
          <label class="block text-[10px] font-medium text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Name</label>
          <input x-model="channelName" type="text" placeholder="z.B. general" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:border-[var(--t-accent)]/40 outline-none transition" @keydown.enter="if(channelName.trim()) { $wire.createChatChannel(channelName.trim(), channelDesc.trim() || null, null, selectedIds); showNewChannel = false; }">
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Beschreibung (optional)</label>
          <input x-model="channelDesc" type="text" placeholder="Worum geht es?" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:border-[var(--t-accent)]/40 outline-none transition">
        </div>
        <div x-show="members.length > 0">
          <label class="block text-[10px] font-medium text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Mitglieder einladen</label>
          <div class="max-h-36 overflow-y-auto rounded border border-[var(--t-border)]/60">
            <template x-for="member in members" :key="member.id">
              <label class="flex items-center gap-2.5 px-2.5 py-1.5 text-sm text-[var(--t-text)] hover:bg-white/5 transition cursor-pointer">
                <input type="checkbox" :value="member.id" x-model.number="selectedIds" class="rounded border-[var(--t-border)] text-[var(--t-accent)] focus:ring-[var(--t-accent)]/30 w-3.5 h-3.5">
                <div class="w-5 h-5 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                  <template x-if="member.avatar">
                    <img :src="member.avatar" alt="" class="w-full h-full object-cover">
                  </template>
                  <template x-if="!member.avatar">
                    <span x-text="member.initials"></span>
                  </template>
                </div>
                <span x-text="member.name" class="text-xs"></span>
              </label>
            </template>
          </div>
          <div class="text-[10px] text-[var(--t-text-muted)] mt-1" x-show="selectedIds.length > 0" x-text="selectedIds.length + ' ausgewählt'"></div>
        </div>
      </div>
      <div class="px-4 py-3 border-t border-[var(--t-border)]/60 flex justify-end gap-2">
        <button @click="showNewChannel = false" class="text-xs px-3 py-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">Abbrechen</button>
        <button
          @click="if(channelName.trim()) { $wire.createChatChannel(channelName.trim(), channelDesc.trim() || null, null, selectedIds); showNewChannel = false; }"
          :disabled="!channelName.trim()"
          :class="channelName.trim() ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80' : 'bg-[var(--t-text-muted)]/20 text-[var(--t-text-muted)] cursor-not-allowed'"
          class="text-xs px-3 py-1.5 rounded transition"
        >Erstellen</button>
      </div>
    </div>
  </div>

  <script>
    function terminalShell(){
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
    }
  </script>
</div>
