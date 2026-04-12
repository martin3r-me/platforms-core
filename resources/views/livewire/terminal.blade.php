<div
  x-data="terminalShell()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle()"
  x-on:toggle-terminal-open.window="if(!open) toggle()"
  x-on:scroll-to-message.window="$nextTick(() => { const el = document.getElementById('msg-' + $event.detail.messageId); if(el) { el.scrollIntoView({behavior:'smooth',block:'center'}); el.classList.add('!bg-amber-500/15'); setTimeout(() => el.classList.remove('!bg-amber-500/15'), 2000); } })"
  x-on:terminal-typing="sendTypingWhisper($wire.channelId)"
  x-on:keydown.escape.window="if(fullscreen) toggleFullscreen()"
  style="height:36px;min-height:36px;max-height:36px"
  :class="[
    fullscreen ? 'fixed inset-0 z-[60]' : 'w-full flex-none relative',
    resizing ? '' : 'transition-[height,min-height,max-height] duration-300 ease-[cubic-bezier(0.33,1,0.68,1)]'
  ]"
  x-bind:style="fullscreen
    ? 'height:100vh;min-height:100vh;max-height:100vh'
    : (open ? 'height:' + panelHeight + 'px;min-height:' + panelHeight + 'px;max-height:' + panelHeight + 'px' : 'height:36px;min-height:36px;max-height:36px')"
  wire:ignore.self
  wire:key="terminal-root"
>
  <style>
    :root {
      --t-glass: rgba(var(--ui-primary-rgb), 0.72);
      --t-glass-surface: rgba(var(--ui-primary-rgb), 0.65);
      --t-glass-hover: rgba(var(--ui-primary-rgb), 0.55);
      --t-blur: 24px;
      --t-border: rgba(255,255,255,0.08);
      --t-border-bright: rgba(255,255,255,0.14);
      --t-text: #e4e4e7;
      --t-text-muted: #a1a1aa;
      --t-accent: var(--ui-secondary);
      --t-glow: rgba(var(--ui-secondary-rgb), 0.15);
      --t-unread-glow: rgba(var(--ui-danger-rgb), 0.3);
      /* Sidebar — dark primary base with subtle secondary tint */
      --t-sidebar-from: rgb(var(--ui-primary-rgb));
      --t-sidebar-to: color-mix(in srgb, rgb(var(--ui-primary-rgb)) 75%, rgb(var(--ui-secondary-rgb)));
      --t-sidebar-hover: rgba(var(--ui-secondary-rgb), 0.12);
      --t-sidebar-active: var(--ui-secondary);
    }
    /* Light scope — remaps terminal vars to platform light theme for content area + modals */
    .terminal-light {
      --t-text: var(--ui-body-color);
      --t-text-muted: var(--ui-muted);
      --t-border: var(--ui-border);
      --t-accent: var(--ui-primary);
      --t-glass-surface: var(--ui-surface);
      --t-glass: var(--ui-surface);
      --t-glass-hover: var(--ui-surface-hover);
      --t-border-bright: var(--ui-border);
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
    class="w-full h-full overflow-hidden flex flex-col relative z-[1]"
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
      :class="fullscreen ? 'h-12 border-b border-[var(--t-border)]' : 'h-9 cursor-pointer'"
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
          @click.stop="$wire.call('openMyDay'); $wire.set('activeApp', 'agenda'); if(!open) toggle()"
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

        <!-- ═══ Sidebar: Aktivitäten ═══ -->
        <div x-show="$wire.activeApp === 'activity'" class="flex-1 min-h-0 flex flex-col overflow-y-auto">
          <div class="px-3 py-3">
            @if($this->contextType && $this->contextId)
              {{-- Activity filter --}}
              @php
                $activities = $this->contextActivities;
                $manualCount = count(array_filter($activities, fn($a) => ($a['activity_type'] ?? 'system') === 'manual'));
                $systemCount = count($activities) - $manualCount;
              @endphp
              <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Filter</h3>
              <div class="space-y-1 mb-4">
                <button
                  wire:click="$set('activityFilter', 'all')"
                  class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md transition text-left {{ $this->activityFilter === 'all' ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'bg-white/[0.03] hover:bg-white/[0.06]' }}"
                >
                  <div class="w-5 h-5 rounded-full bg-[var(--t-text-muted)]/10 flex items-center justify-center">
                    @svg('heroicon-o-queue-list', 'w-3 h-3 text-[var(--t-text-muted)]')
                  </div>
                  <span class="text-xs text-[var(--t-text)] flex-1">Alle</span>
                  <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ count($activities) }}</span>
                </button>
                <button
                  wire:click="$set('activityFilter', 'manual')"
                  class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md transition text-left {{ $this->activityFilter === 'manual' ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'bg-white/[0.03] hover:bg-white/[0.06]' }}"
                >
                  <div class="w-5 h-5 rounded-full bg-[var(--t-accent)]/15 flex items-center justify-center">
                    @svg('heroicon-o-pencil-square', 'w-3 h-3 text-[var(--t-accent)]')
                  </div>
                  <span class="text-xs text-[var(--t-text)] flex-1">Notizen</span>
                  <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $manualCount }}</span>
                </button>
                <button
                  wire:click="$set('activityFilter', 'system')"
                  class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md transition text-left {{ $this->activityFilter === 'system' ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'bg-white/[0.03] hover:bg-white/[0.06]' }}"
                >
                  <div class="w-5 h-5 rounded-full bg-[var(--t-text-muted)]/5 flex items-center justify-center">
                    @svg('heroicon-o-cog-6-tooth', 'w-3 h-3 text-[var(--t-text-muted)]')
                  </div>
                  <span class="text-xs text-[var(--t-text)] flex-1">System</span>
                  <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $systemCount }}</span>
                </button>
              </div>
            @endif
          </div>
        </div>

        <!-- ═══ Sidebar: Tags ═══ -->
        <div x-show="$wire.activeApp === 'tags'" class="flex-1 min-h-0 flex flex-col overflow-y-auto">
          <div class="px-3 py-3">
            {{-- Compact all-tags overview --}}
            <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Alle Tags</h3>
            <div class="space-y-0.5">
              @forelse($allTags as $tag)
                <div class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-white/[0.03] transition">
                  <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] ?? 'var(--t-text-muted)' }}"></div>
                  <span class="text-[11px] text-[var(--t-text)] truncate flex-1">{{ $tag['label'] }}</span>
                  <span class="text-[9px] text-[var(--t-text-muted)] tabular-nums">{{ $tag['total_count'] }}x</span>
                </div>
              @empty
                <p class="text-[10px] text-[var(--t-text-muted)] text-center py-2">Keine Tags</p>
              @endforelse
            </div>
          </div>
        </div>

        <!-- ═══ Sidebar: Zeit ═══ -->
        <div x-show="$wire.activeApp === 'time'" class="flex-1 min-h-0 flex flex-col overflow-y-auto"
             x-data="{ showBudget: false }">
          <div class="px-3 py-3 space-y-3">
            {{-- Time entry form --}}
            <div>
              <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Zeit erfassen</h3>

              {{-- Date --}}
              <div class="mb-2">
                <input type="date"
                       wire:model="timeWorkDate"
                       class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] [color-scheme:dark]" />
              </div>

              {{-- Rate --}}
              <div class="mb-2">
                <input type="text"
                       wire:model="timeRate"
                       placeholder="Stundensatz (optional, z.B. 120)"
                       class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                @error('timeRate') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
              </div>

              {{-- Duration badges --}}
              <div class="mb-2">
                <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Dauer</label>
                <div class="flex flex-wrap gap-1">
                  @foreach([15 => '15m', 30 => '30m', 45 => '45m', 60 => '1h', 90 => '1.5h', 120 => '2h', 180 => '3h', 240 => '4h', 360 => '6h', 480 => '8h'] as $mins => $label)
                    <button wire:click="$set('timeMinutes', {{ $mins }})"
                            class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timeMinutes === $mins ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5' }}">
                      {{ $label }}
                    </button>
                  @endforeach
                </div>
              </div>

              {{-- Note --}}
              <div class="mb-2">
                <textarea wire:model="timeNote"
                          rows="2"
                          placeholder="Notiz (optional)"
                          class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] resize-none"></textarea>
              </div>

              {{-- Amount preview --}}
              @if($this->timeRate && $this->timeMinutes)
                @php
                  $previewRateCents = null;
                  $normalizedRate = str_replace([' ', "'", ','], ['', '', '.'], $this->timeRate);
                  if (is_numeric($normalizedRate) && (float)$normalizedRate > 0) {
                    $previewRateCents = (int) round((float)$normalizedRate * 100);
                  }
                  $previewAmount = $previewRateCents ? round($previewRateCents * ($this->timeMinutes / 60)) / 100 : null;
                @endphp
                @if($previewAmount)
                  <div class="mb-2 px-2.5 py-1.5 rounded-md bg-white/[0.03] border border-[var(--t-border)]/30 text-[10px] text-[var(--t-text-muted)]">
                    Betrag: <span class="text-[var(--t-text)] font-medium">{{ number_format($previewAmount, 2, ',', '.') }} €</span>
                  </div>
                @endif
              @endif

              {{-- Submit button --}}
              <button wire:click="saveTimeEntry"
                      @if(!$this->contextType || !$this->contextId) disabled @endif
                      class="w-full px-3 py-2 rounded-md text-[11px] font-semibold transition {{ ($this->contextType && $this->contextId) ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80' : 'bg-[var(--t-text-muted)]/20 text-[var(--t-text-muted)] cursor-not-allowed' }}">
                Zeit erfassen
              </button>
            </div>

            {{-- Budget section (collapsible) --}}
            <div>
              <button @click="showBudget = !showBudget"
                      class="flex items-center gap-1.5 w-full text-left">
                <svg class="w-3 h-3 text-[var(--t-text-muted)] transition-transform" :class="showBudget && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Budget</h3>
              </button>

              <div x-show="showBudget" x-collapse class="mt-2 space-y-2">
                {{-- Hours badges --}}
                <div>
                  <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Stunden</label>
                  <div class="flex flex-wrap gap-1">
                    @foreach([60 => '1h', 120 => '2h', 180 => '3h', 240 => '4h', 300 => '5h', 360 => '6h', 420 => '7h', 480 => '8h'] as $mins => $label)
                      <button wire:click="$set('timePlannedMinutes', {{ $mins }})"
                              class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timePlannedMinutes === $mins ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5' }}">
                        {{ $label }}
                      </button>
                    @endforeach
                  </div>
                </div>

                {{-- Days badges --}}
                <div>
                  <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Tage (à 8h)</label>
                  <div class="flex flex-wrap gap-1">
                    @foreach([480 => '1d', 960 => '2d', 2400 => '5d', 4800 => '10d', 9600 => '20d'] as $mins => $label)
                      <button wire:click="$set('timePlannedMinutes', {{ $mins }})"
                              class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timePlannedMinutes === $mins ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5' }}">
                        {{ $label }}
                      </button>
                    @endforeach
                  </div>
                </div>

                {{-- Manual minutes --}}
                <div>
                  <input type="number"
                         wire:model="timePlannedMinutes"
                         placeholder="Minuten manuell"
                         min="1"
                         class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                  @error('timePlannedMinutes') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                </div>

                {{-- Budget note --}}
                <div>
                  <input type="text"
                         wire:model="timePlannedNote"
                         placeholder="Budget-Notiz (optional)"
                         class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                </div>

                {{-- Budget submit --}}
                <button wire:click="saveTimePlanned"
                        @if(!$this->contextType || !$this->contextId || !$this->timePlannedMinutes) disabled @endif
                        class="w-full px-3 py-1.5 rounded-md text-[11px] font-semibold transition {{ ($this->contextType && $this->contextId && $this->timePlannedMinutes) ? 'bg-emerald-600 text-white hover:bg-emerald-500' : 'bg-[var(--t-text-muted)]/20 text-[var(--t-text-muted)] cursor-not-allowed' }}">
                  Budget hinzufügen
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ═══ Sidebar: Dateien ═══ -->
        <div x-show="$wire.activeApp === 'files'" class="flex-1 min-h-0 flex flex-col overflow-y-auto">
          <div class="px-3 py-3">
            @if($this->contextType && $this->contextId)
              @php
                $allFiles = $this->contextFiles;
                $imageCount = count(array_filter($allFiles, fn($f) => $f['is_image']));
                $docCount = count($allFiles) - $imageCount;
              @endphp

              {{-- Stats --}}
              <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Übersicht</h3>
              <div class="grid grid-cols-3 gap-1.5 mb-4">
                <div class="p-2 rounded-lg bg-white/[0.03] text-center">
                  <div class="text-sm font-bold text-[var(--t-text)]">{{ count($allFiles) }}</div>
                  <div class="text-[9px] text-[var(--t-text-muted)]">Gesamt</div>
                </div>
                <div class="p-2 rounded-lg bg-white/[0.03] text-center">
                  <div class="text-sm font-bold text-[var(--t-text)]">{{ $imageCount }}</div>
                  <div class="text-[9px] text-[var(--t-text-muted)]">Bilder</div>
                </div>
                <div class="p-2 rounded-lg bg-white/[0.03] text-center">
                  <div class="text-sm font-bold text-[var(--t-text)]">{{ $docCount }}</div>
                  <div class="text-[9px] text-[var(--t-text-muted)]">Dokumente</div>
                </div>
              </div>

              {{-- Filter --}}
              <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Filter</h3>
              <div class="space-y-1 mb-4">
                <button
                  wire:click="$set('filesFilter', 'all')"
                  class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md transition text-left {{ $this->filesFilter === 'all' ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'bg-white/[0.03] hover:bg-white/[0.06]' }}"
                >
                  <div class="w-5 h-5 rounded-full bg-[var(--t-text-muted)]/10 flex items-center justify-center">
                    @svg('heroicon-o-queue-list', 'w-3 h-3 text-[var(--t-text-muted)]')
                  </div>
                  <span class="text-xs text-[var(--t-text)] flex-1">Alle</span>
                  <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ count($allFiles) }}</span>
                </button>
                <button
                  wire:click="$set('filesFilter', 'images')"
                  class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md transition text-left {{ $this->filesFilter === 'images' ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'bg-white/[0.03] hover:bg-white/[0.06]' }}"
                >
                  <div class="w-5 h-5 rounded-full bg-[var(--t-accent)]/15 flex items-center justify-center">
                    @svg('heroicon-o-photo', 'w-3 h-3 text-[var(--t-accent)]')
                  </div>
                  <span class="text-xs text-[var(--t-text)] flex-1">Bilder</span>
                  <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $imageCount }}</span>
                </button>
                <button
                  wire:click="$set('filesFilter', 'documents')"
                  class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md transition text-left {{ $this->filesFilter === 'documents' ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'bg-white/[0.03] hover:bg-white/[0.06]' }}"
                >
                  <div class="w-5 h-5 rounded-full bg-[var(--t-text-muted)]/5 flex items-center justify-center">
                    @svg('heroicon-o-document', 'w-3 h-3 text-[var(--t-text-muted)]')
                  </div>
                  <span class="text-xs text-[var(--t-text)] flex-1">Dokumente</span>
                  <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $docCount }}</span>
                </button>
              </div>
            @endif
          </div>
        </div>

        <!-- ═══ Sidebar: ExtraFields ═══ -->
        <div x-show="$wire.activeApp === 'extrafields'" class="flex-1 min-h-0 flex flex-col overflow-y-auto"
             x-data="{ showNewField: false, showNewLookup: false }">
          <div class="px-3 py-3 space-y-3">

            {{-- Tab switcher: Felder | Lookups --}}
            <div class="flex rounded-md border border-[var(--t-border)]/60 overflow-hidden">
              <button wire:click="$set('efTab', 'fields')"
                      class="flex-1 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider transition {{ $this->efTab === 'fields' ? 'bg-[var(--t-accent)]/20 text-[var(--t-accent)]' : 'text-[var(--t-text-muted)] hover:bg-white/5' }}">
                Felder
              </button>
              <button wire:click="$set('efTab', 'lookups')"
                      class="flex-1 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider transition {{ $this->efTab === 'lookups' ? 'bg-[var(--t-accent)]/20 text-[var(--t-accent)]' : 'text-[var(--t-text-muted)] hover:bg-white/5' }}">
                Lookups
              </button>
            </div>

            {{-- === Fields Tab === --}}
            @if($this->efTab === 'fields')
              {{-- Field list --}}
              <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Felder ({{ count($this->efDefinitions) }})</h3>
                <div class="space-y-0.5">
                  @forelse($this->efDefinitions as $def)
                    <div class="group flex items-center gap-1.5 px-2 py-1.5 rounded-md transition cursor-pointer {{ $this->efEditingDefinitionId === $def['id'] ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'hover:bg-white/[0.03]' }}"
                         wire:click="efStartEditDefinition({{ $def['id'] }})">
                      <div class="min-w-0 flex-1">
                        <div class="text-[11px] text-[var(--t-text)] truncate">{{ $def['label'] }}</div>
                        <div class="flex items-center gap-1 mt-0.5">
                          <span class="text-[9px] px-1 py-0 rounded bg-white/[0.06] text-[var(--t-text-muted)]">{{ $def['type_label'] ?? $def['type'] }}</span>
                          @if($def['is_required'])<span class="text-[9px] text-amber-400">pflicht</span>@endif
                          @if($def['is_encrypted'])<span class="text-[9px] text-purple-400">🔒</span>@endif
                          @if($def['has_visibility_conditions'])<span class="text-[9px] text-blue-400">👁</span>@endif
                        </div>
                      </div>
                      <button wire:click.stop="efDeleteDefinition({{ $def['id'] }})"
                              class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-red-500/20 text-[var(--t-text-muted)] hover:text-red-400 transition"
                              onclick="return confirm('Feld wirklich löschen?')">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                      </button>
                    </div>
                  @empty
                    <p class="text-[10px] text-[var(--t-text-muted)] text-center py-3">Keine Felder definiert</p>
                  @endforelse
                </div>
              </div>

              {{-- New field form (collapsible) --}}
              <div>
                <button @click="showNewField = !showNewField"
                        class="flex items-center gap-1.5 w-full text-left">
                  <svg class="w-3 h-3 text-[var(--t-text-muted)] transition-transform" :class="showNewField && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                  <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Neues Feld</h3>
                </button>

                <div x-show="showNewField" x-collapse class="mt-2 space-y-2">
                  {{-- Label --}}
                  <div>
                    <input type="text" wire:model="efNewField.label" placeholder="Feldname"
                           class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                    @error('efNewField.label') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                  </div>

                  {{-- Type --}}
                  <div>
                    <select wire:model.live="efNewField.type"
                            class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] [&>option]:bg-[var(--t-bg)] [&>option]:text-[var(--t-text)]">
                      @foreach($this->efAvailableTypes() as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                      @endforeach
                    </select>
                  </div>

                  {{-- Select options --}}
                  @if($this->efNewField['type'] === 'select')
                    <div>
                      <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Optionen</label>
                      <div class="flex gap-1">
                        <input type="text" wire:model="efNewOptionText" wire:keydown.enter="efAddNewOption" placeholder="Option hinzufügen"
                               class="flex-1 px-2 py-1 text-[10px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                        <button wire:click="efAddNewOption" class="px-2 py-1 text-[10px] bg-[var(--t-accent)]/20 text-[var(--t-accent)] rounded-md hover:bg-[var(--t-accent)]/30 transition">+</button>
                      </div>
                      @if(!empty($this->efNewField['options']))
                        <div class="flex flex-wrap gap-1 mt-1.5">
                          @foreach($this->efNewField['options'] as $i => $opt)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] bg-white/[0.06] text-[var(--t-text)] rounded">
                              {{ $opt }}
                              <button wire:click="efRemoveNewOption({{ $i }})" class="text-[var(--t-text-muted)] hover:text-red-400">&times;</button>
                            </span>
                          @endforeach
                        </div>
                      @endif
                      @error('efNewField.options') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                    </div>
                  @endif

                  {{-- Lookup --}}
                  @if($this->efNewField['type'] === 'lookup')
                    <div>
                      <select wire:model="efNewField.lookup_id"
                              class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] [&>option]:bg-[var(--t-bg)] [&>option]:text-[var(--t-text)]">
                        <option value="">Lookup wählen…</option>
                        @foreach($this->efLookups as $lu)
                          <option value="{{ $lu['id'] }}">{{ $lu['label'] }} ({{ $lu['values_count'] }})</option>
                        @endforeach
                      </select>
                      @error('efNewField.lookup_id') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                    </div>
                  @endif

                  {{-- Regex --}}
                  @if($this->efNewField['type'] === 'regex')
                    <div>
                      <input type="text" wire:model="efNewField.regex_pattern" placeholder="Regex Pattern"
                             class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                      @error('efNewField.regex_pattern') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                    </div>
                  @endif

                  {{-- Checkboxes --}}
                  <div class="flex flex-wrap gap-3">
                    <label class="flex items-center gap-1.5 text-[10px] text-[var(--t-text-muted)] cursor-pointer">
                      <input type="checkbox" wire:model="efNewField.is_required" class="rounded border-[var(--t-border)]/60 text-[var(--t-accent)] focus:ring-[var(--t-accent)] w-3 h-3" />
                      Pflicht
                    </label>
                    <label class="flex items-center gap-1.5 text-[10px] text-[var(--t-text-muted)] cursor-pointer">
                      <input type="checkbox" wire:model="efNewField.is_encrypted" class="rounded border-[var(--t-border)]/60 text-[var(--t-accent)] focus:ring-[var(--t-accent)] w-3 h-3" />
                      Verschlüsselt
                    </label>
                  </div>

                  {{-- Submit --}}
                  <button wire:click="efCreateDefinition"
                          class="w-full px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition">
                    Feld erstellen
                  </button>
                </div>
              </div>
            @endif

            {{-- === Lookups Tab === --}}
            @if($this->efTab === 'lookups')
              {{-- Lookup list --}}
              <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Lookups ({{ count($this->efLookups) }})</h3>
                <div class="space-y-0.5">
                  @forelse($this->efLookups as $lu)
                    <div class="group flex items-center gap-1.5 px-2 py-1.5 rounded-md transition {{ $this->efSelectedLookupId === $lu['id'] ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'hover:bg-white/[0.03]' }}">
                      @if($this->efEditingLookupId === $lu['id'])
                        <div class="flex-1 space-y-1">
                          <input type="text" wire:model="efEditLookup.label"
                                 class="w-full px-2 py-1 text-[10px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                          <div class="flex gap-1">
                            <button wire:click="efSaveEditLookup" class="px-2 py-0.5 text-[9px] bg-[var(--t-accent)] text-white rounded hover:bg-[var(--t-accent)]/80 transition">Speichern</button>
                            <button wire:click="efCancelEditLookup" class="px-2 py-0.5 text-[9px] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">Abbrechen</button>
                          </div>
                        </div>
                      @else
                        <div class="min-w-0 flex-1 cursor-pointer" wire:click="efSelectLookup({{ $lu['id'] }})">
                          <div class="text-[11px] text-[var(--t-text)] truncate">{{ $lu['label'] }}</div>
                          <div class="flex items-center gap-1 mt-0.5">
                            <span class="text-[9px] text-[var(--t-text-muted)]">{{ $lu['values_count'] }} Werte</span>
                            @if($lu['is_system'])<span class="text-[9px] px-1 rounded bg-white/[0.06] text-[var(--t-text-muted)]">System</span>@endif
                          </div>
                        </div>
                        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                          @if(!$lu['is_system'])
                            <button wire:click.stop="efStartEditLookup({{ $lu['id'] }})" class="p-1 rounded hover:bg-white/10 text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z"/></svg>
                            </button>
                            <button wire:click.stop="efDeleteLookup({{ $lu['id'] }})" class="p-1 rounded hover:bg-red-500/20 text-[var(--t-text-muted)] hover:text-red-400 transition"
                                    onclick="return confirm('Lookup wirklich löschen?')">
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                            </button>
                          @endif
                        </div>
                      @endif
                    </div>
                  @empty
                    <p class="text-[10px] text-[var(--t-text-muted)] text-center py-3">Keine Lookups vorhanden</p>
                  @endforelse
                </div>
              </div>

              {{-- New lookup form (collapsible) --}}
              <div>
                <button @click="showNewLookup = !showNewLookup"
                        class="flex items-center gap-1.5 w-full text-left">
                  <svg class="w-3 h-3 text-[var(--t-text-muted)] transition-transform" :class="showNewLookup && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                  <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Neuer Lookup</h3>
                </button>

                <div x-show="showNewLookup" x-collapse class="mt-2 space-y-2">
                  <div>
                    <input type="text" wire:model="efNewLookup.label" placeholder="Lookup-Name"
                           class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                    @error('efNewLookup.label') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                  </div>
                  <div>
                    <input type="text" wire:model="efNewLookup.description" placeholder="Beschreibung (optional)"
                           class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                  </div>
                  <button wire:click="efCreateLookup"
                          class="w-full px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition">
                    Lookup erstellen
                  </button>
                </div>
              </div>
            @endif

          </div>
        </div>

        <!-- ═══ Sidebar: Agenda ═══ -->
        <div x-show="$wire.activeApp === 'agenda'" class="flex-1 min-h-0 flex flex-col"
             x-data="{ showNewAgenda: false, newAgendaName: '', newAgendaIcon: '' }">

          {{-- Mein Tag — always first --}}
          <div class="px-2 mb-2">
            <button
              wire:click="openMyDay"
              class="w-full flex items-center gap-2 px-2 py-2 rounded-lg text-xs transition
                {{ $agendaView === 'day' && !$activeAgendaId ? 'bg-[var(--t-accent)]/10 text-[var(--t-accent)] ring-1 ring-[var(--t-accent)]/20' : 'text-[var(--t-text)] hover:bg-white/5' }}"
            >
              <span class="text-sm">☀️</span>
              <div class="flex-1 text-left">
                <span class="font-semibold">Mein Tag</span>
              </div>
              @php
                $todayCount = \Platform\Core\Models\TerminalAgendaItem::whereIn('agenda_id',
                  \Platform\Core\Models\TerminalAgendaMember::where('user_id', auth()->id())
                    ->whereHas('agenda', fn($q) => $q->where('team_id', auth()->user()?->currentTeam?->id))
                    ->pluck('agenda_id')
                )->whereDate('date', today())->where('is_done', false)->count();
              @endphp
              @if($todayCount > 0)
                <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-[var(--t-accent)]/20 text-[var(--t-accent)] text-[10px] font-bold flex items-center justify-center">{{ $todayCount }}</span>
              @endif
            </button>
          </div>

          <div class="w-full px-2 mb-2"><div class="border-t border-white/[0.06]"></div></div>

          {{-- Agenda list --}}
          <div class="px-2 mb-2">
            <div class="flex items-center justify-between px-1.5 py-1 mb-1">
              <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Agendas</span>
              <button @click="showNewAgenda = !showNewAgenda" class="text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
              </button>
            </div>

            {{-- New agenda inline form --}}
            <div x-show="showNewAgenda" x-collapse class="mb-2 space-y-1.5">
              <input type="text" x-model="newAgendaName" placeholder="Agenda-Name…"
                     @keydown.enter="if(newAgendaName.trim()) { $wire.createAgenda(newAgendaName.trim(), null, newAgendaIcon || null); newAgendaName = ''; newAgendaIcon = ''; showNewAgenda = false; }"
                     @keydown.escape="showNewAgenda = false"
                     class="w-full text-[11px] px-2.5 py-1.5 rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:border-[var(--t-accent)]/40 outline-none transition">
              <div class="flex gap-1">
                <button @click="if(newAgendaName.trim()) { $wire.createAgenda(newAgendaName.trim(), null, newAgendaIcon || null); newAgendaName = ''; newAgendaIcon = ''; showNewAgenda = false; }"
                        class="flex-1 text-[10px] px-2 py-1 rounded bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition">Erstellen</button>
                <button @click="showNewAgenda = false; newAgendaName = ''"
                        class="text-[10px] px-2 py-1 rounded border border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">Abbrechen</button>
              </div>
            </div>

            {{-- Agenda entries --}}
            <div class="space-y-px">
              @forelse($this->agendas as $agenda)
                <button
                  wire:click="selectAgenda({{ $agenda['id'] }})"
                  class="w-full flex items-center gap-2 px-1.5 py-1.5 rounded-md text-xs transition
                    {{ $activeAgendaId === $agenda['id'] ? 'bg-[var(--t-accent)]/10 text-[var(--t-accent)]' : 'text-[var(--t-text)] hover:bg-white/5' }}"
                >
                  <span class="text-sm flex-shrink-0">{{ $agenda['icon'] }}</span>
                  <span class="truncate flex-1 text-left">{{ $agenda['name'] }}</span>
                  @if($agenda['item_count'] > 0)
                    <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $agenda['item_count'] }}</span>
                  @endif
                </button>
              @empty
                <div class="px-1.5 py-3 text-[10px] text-[var(--t-text-muted)] text-center">Noch keine Agendas</div>
              @endforelse
            </div>
          </div>
        </div>

        @include('platform::livewire.partials.terminal-comms-sidebar')

      </div>

      <!-- Main Content Area — keyed per channel so editor + messages fully rebuild -->
      <div class="terminal-light flex-1 min-w-0 flex flex-col bg-[var(--ui-surface)]" wire:key="terminal-main-{{ $channelId }}">

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

          <!-- Activity Header (only visible in activity app) -->
          <div x-show="$wire.activeApp === 'activity'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->activeChannel['type'] === 'context' && ! empty($this->activeChannel['context']))
              <span class="text-[14px]">{{ $this->activeChannel['context']['icon'] ?? '' }}</span>
              <div class="flex flex-col leading-tight">
                @php $actContextTitle = $this->activeChannel['name'] ?: ($this->activeChannel['context']['title'] ?? 'Kontext'); @endphp
                @if(! empty($this->activeChannel['context_url']))
                  <a href="{{ $this->activeChannel['context_url'] }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition" title="Zum Kontext springen">
                    {{ $actContextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $actContextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--t-text-muted)]">Aktivitäten</span>
              </div>
            @else
              @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--t-text-muted)]')
              <span class="font-bold text-[13px] text-[var(--t-text)]">Aktivitäten</span>
            @endif
          </div>

          <!-- Tags Header (only visible in tags app) -->
          <div x-show="$wire.activeApp === 'tags'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->contextType && $this->contextId)
              @php $tagsHeaderBreadcrumb = $this->getContextBreadcrumb(); @endphp
              <span class="text-[14px]">{{ $tagsHeaderBreadcrumb['icon'] ?? '' }}</span>
              <div class="flex flex-col leading-tight">
                @php $tagsContextTitle = $tagsHeaderBreadcrumb['title'] ?? $this->contextSubject ?? 'Kontext'; @endphp
                @if($this->contextUrl)
                  <a href="{{ $this->contextUrl }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition" title="Zum Kontext springen">
                    {{ $tagsContextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $tagsContextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--t-text-muted)]">Tags & Farben</span>
              </div>
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">Tags & Farben</span>
            @endif
          </div>

          <!-- Time Header (only visible in time app) -->
          <div x-show="$wire.activeApp === 'time'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->contextType && $this->contextId)
              @php $timeHeaderBreadcrumb = $this->getContextBreadcrumb(); @endphp
              <span class="text-[14px]">{{ $timeHeaderBreadcrumb['icon'] ?? '' }}</span>
              <div class="flex flex-col leading-tight">
                @php $timeContextTitle = $timeHeaderBreadcrumb['title'] ?? $this->contextSubject ?? 'Kontext'; @endphp
                @if($this->contextUrl)
                  <a href="{{ $this->contextUrl }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition" title="Zum Kontext springen">
                    {{ $timeContextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $timeContextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--t-text-muted)]">Zeiterfassung</span>
              </div>
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">Zeiterfassung</span>
            @endif
          </div>

          <!-- OKR Header (only visible in okr app) -->
          <div x-show="$wire.activeApp === 'okr'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->contextType && $this->contextId)
              @php $okrHeaderBreadcrumb = $this->getContextBreadcrumb(); @endphp
              <span class="text-[14px]">{{ $okrHeaderBreadcrumb['icon'] ?? '' }}</span>
              <div class="flex flex-col leading-tight">
                @php $okrContextTitle = $okrHeaderBreadcrumb['title'] ?? $this->contextSubject ?? 'Kontext'; @endphp
                @if($this->contextUrl)
                  <a href="{{ $this->contextUrl }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition" title="Zum Kontext springen">
                    {{ $okrContextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $okrContextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--t-text-muted)]">OKR KeyResults</span>
              </div>
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">OKR KeyResults</span>
            @endif
          </div>

          <!-- ExtraFields Header (only visible in extrafields app) -->
          <div x-show="$wire.activeApp === 'extrafields'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->efContextType && $this->efContextId)
              @php $efHeaderBreadcrumb = $this->getContextBreadcrumb($this->efContextType, $this->efContextId); @endphp
              <span class="text-[14px]">{{ $efHeaderBreadcrumb['icon'] ?? '📎' }}</span>
              <div class="flex flex-col leading-tight">
                @php $efContextTitle = $efHeaderBreadcrumb['title'] ?? $this->efContextLabel() ?? 'Kontext'; @endphp
                <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $efContextTitle }}</span>
                <span class="text-[10px] text-[var(--t-text-muted)]">Extra-Felder · {{ $this->efTab === 'fields' ? 'Felder' : 'Lookups' }}</span>
              </div>
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">Extra-Felder</span>
            @endif
          </div>

          <!-- Comms Header (only visible in comms app) -->
          <div x-show="$wire.activeApp === 'comms'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->contextType && $this->contextId)
              @php $commsHeaderBreadcrumb = $this->getContextBreadcrumb(); @endphp
              <span class="text-[14px]">{{ $commsHeaderBreadcrumb['icon'] ?? '📨' }}</span>
              <div class="flex flex-col leading-tight">
                @php $commsContextTitle = $commsHeaderBreadcrumb['title'] ?? $this->contextSubject ?? 'Kontext'; @endphp
                @if($this->contextUrl)
                  <a href="{{ $this->contextUrl }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition" title="Zum Kontext springen">
                    {{ $commsContextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $commsContextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--t-text-muted)]">E-Mail &middot; WhatsApp</span>
              </div>
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">Comms</span>
            @endif
          </div>

          <!-- Agenda Header -->
          <div x-show="$wire.activeApp === 'agenda'"
               class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'"
               x-data="{
                 showAgendaMembers: false,
                 agendaMembers: [],
                 loadingMembers: false,
                 showEditAgenda: false,
                 editName: '',
                 editIcon: '',
               }">
            @if($agendaView === 'day' && !$activeAgendaId)
              {{-- Mein Tag header with date navigation --}}
              <span class="text-sm">☀️</span>
              <div class="flex flex-col leading-tight">
                <span class="font-bold text-[13px] text-[var(--t-text)]">Mein Tag</span>
                <span class="text-[10px] text-[var(--t-text-muted)]">
                  {{ $agendaDayDate ? \Carbon\Carbon::parse($agendaDayDate)->translatedFormat('l, d. F Y') : now()->translatedFormat('l, d. F Y') }}
                </span>
              </div>
              <div class="ml-auto flex items-center gap-1">
                <button wire:click="navigateDay('prev')" class="p-1 rounded hover:bg-[var(--t-glass-hover)] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition" title="Gestern">
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                </button>
                <button wire:click="$set('agendaDayDate', '{{ now()->toDateString() }}')" class="px-2 py-0.5 rounded text-[10px] font-medium hover:bg-[var(--t-glass-hover)] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition" title="Heute">Heute</button>
                <button wire:click="navigateDay('next')" class="p-1 rounded hover:bg-[var(--t-glass-hover)] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition" title="Morgen">
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </button>
              </div>
            @elseif($activeAgendaId)
              {{-- Active agenda header --}}
              @php $currentAgenda = collect($this->agendas)->firstWhere('id', $activeAgendaId); @endphp
              @if($currentAgenda)
                <span class="text-sm">{{ $currentAgenda['icon'] }}</span>
                <div class="flex flex-col leading-tight flex-1 min-w-0">
                  <span class="font-bold text-[13px] text-[var(--t-text)] truncate">{{ $currentAgenda['name'] }}</span>
                  @if(!empty($currentAgenda['description']))
                    <span class="text-[10px] text-[var(--t-text-muted)] truncate">{{ $currentAgenda['description'] }}</span>
                  @endif
                </div>

                {{-- Member avatars --}}
                <button
                  @click="
                    showAgendaMembers = !showAgendaMembers;
                    if(showAgendaMembers && agendaMembers.length === 0) {
                      loadingMembers = true;
                      $wire.getAgendaMembers().then(r => { agendaMembers = r; loadingMembers = false; });
                    }
                  "
                  class="flex items-center gap-1 px-1.5 py-0.5 rounded hover:bg-[var(--t-glass-hover)] transition text-[var(--t-text-muted)] hover:text-[var(--t-text)]"
                  title="Mitglieder"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                </button>

                {{-- Members dropdown --}}
                <div x-show="showAgendaMembers" @click.outside="showAgendaMembers = false" x-cloak
                     class="absolute top-full right-4 mt-1 w-64 bg-[var(--ui-surface)] border border-[var(--t-border)] rounded-lg shadow-xl z-50 overflow-hidden">
                  <div class="px-3 py-2 border-b border-[var(--t-border)]/40 flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-[var(--t-text)]">Mitglieder</span>
                  </div>
                  <div class="max-h-48 overflow-y-auto">
                    <template x-if="loadingMembers">
                      <div class="px-3 py-3 text-[10px] text-[var(--t-text-muted)] text-center">Laden…</div>
                    </template>
                    <template x-for="member in agendaMembers" :key="member.id">
                      <div class="px-3 py-1.5 flex items-center gap-2 hover:bg-[var(--t-glass-hover)] transition">
                        <div class="w-5 h-5 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold overflow-hidden flex-shrink-0">
                          <template x-if="member.avatar"><img :src="member.avatar" class="w-full h-full object-cover"></template>
                          <template x-if="!member.avatar"><span x-text="member.initials"></span></template>
                        </div>
                        <span class="text-[11px] text-[var(--t-text)] flex-1 truncate" x-text="member.name"></span>
                        <span class="text-[9px] text-[var(--t-text-muted)]" x-text="member.role"></span>
                        @if($currentAgenda['role'] === 'owner')
                          <button @click="$wire.removeAgendaMember(member.id).then(() => { agendaMembers = agendaMembers.filter(m => m.id !== member.id); })"
                                  x-show="member.role !== 'owner'"
                                  class="p-0.5 rounded hover:bg-red-500/20 text-[var(--t-text-muted)] hover:text-red-400 transition">
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                          </button>
                        @endif
                      </div>
                    </template>
                  </div>
                  @if($currentAgenda['role'] === 'owner')
                    <div class="px-3 py-2 border-t border-[var(--t-border)]/40"
                         x-data="{ addMemberId: '', teamMembers: [], loadedTeam: false, searchMember: '' }"
                         x-init="
                           if(!loadedTeam) {
                             fetch('/api/team-members')
                               .then(r => r.ok ? r.json() : [])
                               .then(d => { teamMembers = d; loadedTeam = true; })
                               .catch(() => { loadedTeam = true; });
                           }
                         ">
                      <div class="flex gap-1">
                        <input type="number" x-model="addMemberId" placeholder="User-ID…"
                               @keydown.enter="if(addMemberId) { $wire.addAgendaMember(parseInt(addMemberId)).then(() => { addMemberId = ''; $wire.getAgendaMembers().then(r => { agendaMembers = r; }); }); }"
                               class="flex-1 text-[10px] px-2 py-1 rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 outline-none">
                        <button @click="if(addMemberId) { $wire.addAgendaMember(parseInt(addMemberId)).then(() => { addMemberId = ''; $wire.getAgendaMembers().then(r => { agendaMembers = r; }); }); }"
                                class="px-2 py-1 text-[10px] rounded bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition">+</button>
                      </div>
                    </div>
                  @endif
                </div>

                {{-- Delete agenda button (owner only) --}}
                @if($currentAgenda['role'] === 'owner')
                  <button wire:click="deleteAgenda({{ $activeAgendaId }})"
                          wire:confirm="Agenda wirklich löschen? Alle Items werden gelöscht."
                          class="p-1 rounded hover:bg-red-500/20 text-[var(--t-text-muted)] hover:text-red-400 transition"
                          title="Agenda löschen">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                  </button>
                @endif
              @endif
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">Agenda</span>
              <span class="text-[10px] text-[var(--t-text-muted)]">Wähle eine Agenda oder öffne "Mein Tag"</span>
            @endif
          </div>

          <!-- ═══ App: Chat ═══ -->
          <div x-show="$wire.activeApp === 'chat'" class="flex-1 min-h-0 flex flex-col">

          <!-- Messages -->
          <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain" :class="fullscreen ? 'text-[14px]' : 'text-[13px]'" x-ref="body" wire:key="terminal-messages-{{ $channelId }}"
               x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
            <div class="py-2" :class="fullscreen ? 'px-6' : 'px-4'">
              @php $lastDate = null; $lastUserId = null; $lastTime = null; @endphp
              @forelse($this->messages as $msg)
                @php
                  $isNewGroup = $msg['user_id'] !== $lastUserId || $msg['date'] !== $lastDate || $msg['time'] !== $lastTime;
                  $isNewDate = $msg['date'] !== $lastDate;
                @endphp

                {{-- Date separator --}}
                @if($isNewDate)
                  @php $lastDate = $msg['date']; @endphp
                  <div class="flex items-center gap-3 my-3 first:mt-0">
                    <div class="flex-1 h-px bg-[var(--t-border)]/50"></div>
                    <span class="text-[11px] text-[var(--t-text-muted)] font-medium px-2 select-none">{{ $msg['date'] }}</span>
                    <div class="flex-1 h-px bg-[var(--t-border)]/50"></div>
                  </div>
                @endif

                @php $lastUserId = $msg['user_id']; $lastTime = $msg['time']; @endphp

                {{-- Message row --}}
                <div id="msg-{{ $msg['id'] }}" class="group relative {{ $isNewGroup ? 'mt-3 first:mt-0' : 'mt-px' }} -mx-4 px-4 py-0.5 hover:bg-white/[0.04] transition-colors" wire:key="msg-{{ $msg['id'] }}">

                  {{-- Hover action bar --}}
                  <div class="absolute -top-3 right-5 items-center gap-px bg-[var(--t-glass-surface)] border border-[var(--t-border)]/60 rounded-md shadow-sm z-10"
                       x-data="{ showMore: false, showEmoji: false, showReminder: false, copied: false }"
                       :class="(showMore || showEmoji) ? 'flex' : 'hidden group-hover:flex'">

                    {{-- Emoji Picker Button --}}
                    <div class="relative">
                      <button @click.stop="showEmoji = !showEmoji; showMore = false"
                              class="p-1 rounded-l-md text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition"
                              title="Reagieren">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.536-4.464a.75.75 0 10-1.072-1.05A3.49 3.49 0 0110 13.5a3.49 3.49 0 01-2.464-1.014.75.75 0 00-1.072 1.05A4.99 4.99 0 0010 15a4.99 4.99 0 003.536-1.464zM9 8.5a1 1 0 11-2 0 1 1 0 012 0zm4 0a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd"/></svg>
                      </button>
                      {{-- Emoji Picker Dropdown --}}
                      <div x-show="showEmoji" x-cloak @click.outside="showEmoji = false"
                           class="absolute bottom-full right-0 mb-1 bg-[var(--t-glass-surface)] border border-[var(--t-border)]/60 rounded-lg shadow-xl z-50 w-[260px]"
                           x-data="{ activeTab: 0 }">
                        {{-- Quick reactions row --}}
                        <div class="flex gap-0.5 p-1.5 border-b border-[var(--t-border)]/40">
                          @foreach(['👍','❤️','😂','🎉','✅','👀','🔥'] as $emoji)
                            <button wire:click="toggleReaction({{ $msg['id'] }}, '{{ $emoji }}')" @click.stop="showEmoji = false"
                                    class="p-1.5 text-lg rounded hover:bg-white/5 transition text-center leading-none">{{ $emoji }}</button>
                          @endforeach
                        </div>
                        @php
                          $emojiCategories = [
                            ['name' => 'Häufig', 'icon' => '👍', 'emojis' => ['👍','❤️','😊','😂','🎉','🔥','✅','👀','🙏','💪','😍','🤔','👏','💯','🚀']],
                            ['name' => 'Smileys', 'icon' => '😀', 'emojis' => ['😀','😃','😄','😁','😅','🤣','😇','🙂','😉','😌','😋','😎','🤩','🥳','😏']],
                            ['name' => 'Gesten', 'icon' => '👋', 'emojis' => ['👋','🤝','✌️','🤞','👌','🤙','👆','👇','👈','👉','☝️','✋','🤚','🖐️','🫡']],
                            ['name' => 'Objekte', 'icon' => '💡', 'emojis' => ['💡','📌','📎','✏️','📝','📅','📊','📈','💻','📱','⏰','🔔','📧','🗂️','🏷️']],
                            ['name' => 'Symbole', 'icon' => '✅', 'emojis' => ['✅','❌','⚠️','❓','❗','💬','🔗','⭐','🏆','🎯','🔒','🔑','♻️','➡️','⬅️']],
                          ];
                        @endphp
                        <div class="flex gap-0.5 px-1 pt-1 border-b border-[var(--t-border)]/40">
                          @foreach($emojiCategories as $ci => $cat)
                            <button @click.stop="activeTab = {{ $ci }}" :class="activeTab === {{ $ci }} ? 'opacity-100 border-b-2 border-[var(--t-accent)]' : 'opacity-50'" class="p-1.5 text-sm rounded-t transition">{{ $cat['icon'] }}</button>
                          @endforeach
                        </div>
                        @foreach($emojiCategories as $ci => $cat)
                          <div x-show="activeTab === {{ $ci }}" class="grid grid-cols-5 gap-0.5 p-1.5">
                            @foreach($cat['emojis'] as $emoji)
                              <button wire:click="toggleReaction({{ $msg['id'] }}, '{{ $emoji }}')" @click.stop="showEmoji = false" class="p-1.5 text-lg rounded hover:bg-white/5 transition text-center leading-none">{{ $emoji }}</button>
                            @endforeach
                          </div>
                        @endforeach
                      </div>
                    </div>

                    {{-- Thread / Reply --}}
                    <button @click.stop="$dispatch('terminal-open-thread', { messageId: {{ $msg['id'] }} })"
                            class="p-1 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition"
                            title="Antworten">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5zm11 4V5a4 4 0 00-4-4H7a4 4 0 014 4h2a2 2 0 012 2v4a2 2 0 01-2 2h-1l-2 2h3l3 3v-3a2 2 0 002-2V9z" clip-rule="evenodd"/></svg>
                    </button>

                    {{-- Bookmark --}}
                    <button wire:click="toggleBookmark({{ $msg['id'] }})"
                            class="p-1 transition {{ $msg['is_bookmarked'] ? 'text-amber-500' : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)]' }} hover:bg-white/5"
                            title="{{ $msg['is_bookmarked'] ? 'Lesezeichen entfernen' : 'Lesezeichen setzen' }}">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2c-.22 0-.44.03-.65.09L5.47 3.6a2.5 2.5 0 00-1.8 2.4v9.5a2 2 0 003.32 1.5L10 14.5l3.01 2.5A2 2 0 0016.33 15.5V6a2.5 2.5 0 00-1.8-2.4l-3.88-1.51A1.75 1.75 0 0010 2z"/></svg>
                    </button>

                    {{-- More menu --}}
                    <div class="relative">
                      <button @click.stop="showMore = !showMore; showEmoji = false"
                              class="p-1 rounded-r-md text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition"
                              title="Mehr">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm5.5 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm7-1.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
                      </button>
                      {{-- More dropdown --}}
                      <div x-show="showMore" x-cloak @click.outside="showMore = false"
                           x-transition:enter="transition ease-out duration-100"
                           x-transition:enter-start="opacity-0 scale-95"
                           x-transition:enter-end="opacity-100 scale-100"
                           x-transition:leave="transition ease-in duration-75"
                           x-transition:leave-start="opacity-100 scale-100"
                           x-transition:leave-end="opacity-0 scale-95"
                           class="absolute bottom-full right-0 mb-1 bg-[var(--t-glass-surface)] border border-[var(--t-border)]/60 rounded-lg shadow-xl z-50 w-52 py-1">
                        {{-- Pin --}}
                        <button wire:click="{{ $msg['is_pinned'] ? 'unpinMessage' : 'pinMessage' }}({{ $msg['id'] }})" @click.stop="showMore = false"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-[var(--t-text)] hover:bg-white/5 transition {{ $msg['is_pinned'] ? 'text-[var(--t-accent)]' : '' }}">
                          <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                          <span>{{ $msg['is_pinned'] ? 'Pin entfernen' : 'Anpinnen' }}</span>
                        </button>
                        {{-- Forward --}}
                        <button @click.stop="$dispatch('terminal-show-forward', { messageId: {{ $msg['id'] }} }); showMore = false"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-[var(--t-text)] hover:bg-white/5 transition">
                          <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.21 2.22a.75.75 0 011.06-.02l7.5 7.25a.75.75 0 010 1.08l-7.5 7.25a.75.75 0 11-1.04-1.08l6.1-5.9H3.75a.75.75 0 010-1.5h13.08l-6.1-5.9a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
                          <span>Weiterleiten</span>
                        </button>
                        {{-- Reminder --}}
                        <button @click.stop="showReminder = !showReminder"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-white/5 transition {{ $msg['has_reminder'] ? 'text-amber-500' : 'text-[var(--t-text)]' }}">
                          <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 00.515 1.076 32.91 32.91 0 003.256.508 3.5 3.5 0 006.972 0 32.903 32.903 0 003.256-.508.75.75 0 00.515-1.076A11.448 11.448 0 0116 8a6 6 0 00-6-6zm0 14.5a2 2 0 01-1.95-1.557 33.146 33.146 0 003.9 0A2 2 0 0110 16.5z" clip-rule="evenodd"/></svg>
                          <span>Erinnern</span>
                          <svg class="w-3 h-3 ml-auto flex-shrink-0 transition-transform" :class="showReminder && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                        {{-- Reminder sub-options (inline) --}}
                        <div x-show="showReminder" x-cloak class="bg-white/[0.02]">
                          <button wire:click="setReminder({{ $msg['id'] }}, '30min')" @click.stop="showMore = false; showReminder = false" class="w-full text-left px-3 py-1.5 pl-9 text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition">In 30 Minuten</button>
                          <button wire:click="setReminder({{ $msg['id'] }}, '1h')" @click.stop="showMore = false; showReminder = false" class="w-full text-left px-3 py-1.5 pl-9 text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition">In 1 Stunde</button>
                          <button wire:click="setReminder({{ $msg['id'] }}, '3h')" @click.stop="showMore = false; showReminder = false" class="w-full text-left px-3 py-1.5 pl-9 text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition">In 3 Stunden</button>
                          <button wire:click="setReminder({{ $msg['id'] }}, 'tomorrow_9')" @click.stop="showMore = false; showReminder = false" class="w-full text-left px-3 py-1.5 pl-9 text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition">Morgen 09:00</button>
                          <button wire:click="setReminder({{ $msg['id'] }}, 'next_monday_9')" @click.stop="showMore = false; showReminder = false" class="w-full text-left px-3 py-1.5 pl-9 text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition">Nächsten Montag 09:00</button>
                          @if($msg['has_reminder'])
                            <div class="border-t border-[var(--t-border)]/40 my-0.5 mx-3"></div>
                            <button wire:click="cancelReminder({{ $msg['id'] }})" @click.stop="showMore = false; showReminder = false" class="w-full text-left px-3 py-1.5 pl-9 text-xs text-red-500 hover:bg-red-500/10 transition">Erinnerung entfernen</button>
                          @endif
                        </div>
                        {{-- Copy link --}}
                        <button @click.stop="
                                  const url = location.origin + '/terminal?channel={{ $channelId }}&message={{ $msg['id'] }}';
                                  navigator.clipboard.writeText(url).then(() => {
                                    copied = true;
                                    setTimeout(() => { copied = false }, 1500);
                                  });
                                  showMore = false;
                                "
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-[var(--t-text)] hover:bg-white/5 transition">
                          <template x-if="!copied">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z"/><path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 005.656 5.656l3-3a4 4 0 00-.225-5.865z"/></svg>
                          </template>
                          <template x-if="copied">
                            <svg class="w-3.5 h-3.5 flex-shrink-0 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                          </template>
                          <span x-text="copied ? 'Kopiert!' : 'Link kopieren'"></span>
                        </button>
                        @if($msg['is_mine'])
                          <div class="border-t border-[var(--t-border)]/40 my-1"></div>
                          {{-- Edit --}}
                          <button wire:click="startEditMessage({{ $msg['id'] }})" @click.stop="showMore = false"
                                  class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-[var(--t-text)] hover:bg-white/5 transition">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z"/></svg>
                            <span>Bearbeiten</span>
                          </button>
                          {{-- Delete --}}
                          <button wire:click="deleteMessage({{ $msg['id'] }})" wire:confirm="Nachricht unwiderruflich löschen?" @click.stop="showMore = false"
                                  class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-red-500 hover:bg-red-500/10 transition">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                            <span>Löschen</span>
                          </button>
                        @endif
                      </div>
                    </div>
                  </div>

                  @if($isNewGroup)
                    {{-- Full message with avatar + name --}}
                    <div class="flex gap-2.5">
                      <div class="w-8 h-8 rounded-lg {{ $msg['is_mine'] ? 'bg-white/10 text-[var(--t-text-muted)]' : 'bg-[var(--t-accent)]/15 text-[var(--t-accent)]' }} flex items-center justify-center text-[11px] font-semibold flex-shrink-0 overflow-hidden mt-0.5">
                        @if(! empty($msg['user_avatar']))
                          <img src="{{ $msg['user_avatar'] }}" alt="" class="w-full h-full object-cover">
                        @else
                          {{ $msg['user_initials'] }}
                        @endif
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2">
                          <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $msg['is_mine'] ? 'Du' : $msg['user_name'] }}</span>
                          <span class="text-[11px] text-[var(--t-text-muted)] font-normal">{{ $msg['time'] }}</span>
                          @if(! empty($msg['edited_at']))
                            <span class="text-[10px] text-[var(--t-text-muted)] font-normal" title="Bearbeitet am {{ $msg['edited_at'] }}">(bearbeitet)</span>
                          @endif
                        </div>
                        @if($msg['type'] === 'forwarded' && ! empty($msg['meta']['forwarded_from']))
                          <div class="text-[10px] text-[var(--t-text-muted)] italic mb-0.5 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.21 2.22a.75.75 0 011.06-.02l7.5 7.25a.75.75 0 010 1.08l-7.5 7.25a.75.75 0 11-1.04-1.08l6.1-5.9H3.75a.75.75 0 010-1.5h13.08l-6.1-5.9a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
                            Weitergeleitet von {{ $msg['meta']['forwarded_from']['user_name'] ?? 'Unbekannt' }}
                          </div>
                        @endif
                        @if($editingMessageId === $msg['id'])
                          <div x-data="{ editText: @js($msg['body_plain']) }" class="mt-1">
                            <textarea
                              x-ref="editInput"
                              x-model="editText"
                              x-init="$nextTick(() => { $refs.editInput.focus(); $refs.editInput.selectionStart = $refs.editInput.value.length; })"
                              @keydown.enter.prevent="if(editText.trim()) $wire.editMessage({{ $msg['id'] }}, '<p>' + editText.replace(/\n/g, '</p><p>') + '</p>', editText)"
                              @keydown.escape="$wire.cancelEdit()"
                              class="w-full text-[13px] px-2.5 py-1.5 rounded border border-[var(--t-accent)]/40 bg-transparent text-[var(--t-text)] focus:border-[var(--t-accent)] outline-none transition resize-none leading-relaxed"
                              rows="2"
                            ></textarea>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-[var(--t-text-muted)]">
                              <span>Enter = Speichern</span>
                              <span>&middot;</span>
                              <span>Escape = Abbrechen</span>
                            </div>
                          </div>
                        @else
                          <div class="text-[var(--t-text)] leading-relaxed prose-terminal">{!! $msg['body_html'] !!}</div>
                        @endif
                        @if(! empty($msg['attachments']))
                          @include('platform::livewire.terminal-attachments', ['attachments' => $msg['attachments']])
                        @endif
                      </div>
                    </div>
                  @else
                    {{-- Continuation — no avatar, time on hover --}}
                    <div class="flex gap-2.5">
                      <div class="w-8 flex-shrink-0 flex items-center justify-center">
                        <span class="text-[10px] text-[var(--t-text-muted)] opacity-0 group-hover:opacity-100 transition-opacity select-none">{{ $msg['time'] }}</span>
                      </div>
                      <div class="flex-1 min-w-0">
                        @if($msg['type'] === 'forwarded' && ! empty($msg['meta']['forwarded_from']))
                          <div class="text-[10px] text-[var(--t-text-muted)] italic mb-0.5 flex items-center gap-1">
                            <svg class="w-3 h-3 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.21 2.22a.75.75 0 011.06-.02l7.5 7.25a.75.75 0 010 1.08l-7.5 7.25a.75.75 0 11-1.04-1.08l6.1-5.9H3.75a.75.75 0 010-1.5h13.08l-6.1-5.9a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
                            Weitergeleitet von {{ $msg['meta']['forwarded_from']['user_name'] ?? 'Unbekannt' }}
                          </div>
                        @endif
                        @if($editingMessageId === $msg['id'])
                          <div x-data="{ editText: @js($msg['body_plain']) }">
                            <textarea
                              x-ref="editInput"
                              x-model="editText"
                              x-init="$nextTick(() => { $refs.editInput.focus(); $refs.editInput.selectionStart = $refs.editInput.value.length; })"
                              @keydown.enter.prevent="if(editText.trim()) $wire.editMessage({{ $msg['id'] }}, '<p>' + editText.replace(/\n/g, '</p><p>') + '</p>', editText)"
                              @keydown.escape="$wire.cancelEdit()"
                              class="w-full text-[13px] px-2.5 py-1.5 rounded border border-[var(--t-accent)]/40 bg-transparent text-[var(--t-text)] focus:border-[var(--t-accent)] outline-none transition resize-none leading-relaxed"
                              rows="2"
                            ></textarea>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-[var(--t-text-muted)]">
                              <span>Enter = Speichern</span>
                              <span>&middot;</span>
                              <span>Escape = Abbrechen</span>
                            </div>
                          </div>
                        @else
                          <div class="text-[var(--t-text)] leading-relaxed prose-terminal">{!! $msg['body_html'] !!}</div>
                          @if(! empty($msg['edited_at']))
                            <span class="text-[10px] text-[var(--t-text-muted)]" title="Bearbeitet am {{ $msg['edited_at'] }}">(bearbeitet)</span>
                          @endif
                        @endif
                        @if(! empty($msg['attachments']))
                          @include('platform::livewire.terminal-attachments', ['attachments' => $msg['attachments']])
                        @endif
                      </div>
                    </div>
                  @endif

                  {{-- Reactions --}}
                  @if(! empty($msg['reactions']))
                    <div class="flex flex-wrap gap-1 mt-1 ml-[42px]">
                      @foreach($msg['reactions'] as $reaction)
                        <button
                          wire:click="toggleReaction({{ $msg['id'] }}, '{{ $reaction['emoji'] }}')"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] border transition
                            {{ $reaction['reacted'] ? 'border-[var(--t-accent)]/30 bg-[var(--t-accent)]/10 text-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:border-[var(--t-border)] hover:bg-white/5' }}"
                        >
                          <span>{{ $reaction['emoji'] }}</span>
                          <span class="font-medium">{{ $reaction['count'] }}</span>
                        </button>
                      @endforeach
                    </div>
                  @endif

                  {{-- Thread indicator --}}
                  @if($msg['reply_count'] > 0)
                    <div class="ml-[42px] mt-1">
                      <button class="inline-flex items-center gap-1.5 text-[12px] text-[var(--t-accent)] hover:underline font-medium">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5zm11 4V5a4 4 0 00-4-4H7a4 4 0 014 4h2a2 2 0 012 2v4a2 2 0 01-2 2h-1l-2 2h3l3 3v-3a2 2 0 002-2V9z" clip-rule="evenodd"/></svg>
                        {{ $msg['reply_count'] }} {{ $msg['reply_count'] === 1 ? 'Antwort' : 'Antworten' }}
                      </button>
                    </div>
                  @endif
                </div>
              @empty
                <div class="flex items-center justify-center h-full text-[var(--t-text-muted)] text-sm">
                  <div class="text-center py-8">
                    <div class="text-3xl mb-3 opacity-20">💬</div>
                    <p>Noch keine Nachrichten.</p>
                    <p class="text-[var(--t-text-muted)]/60 text-xs mt-1">Schreib die erste!</p>
                  </div>
                </div>
              @endforelse
            </div>
          </div>

          <!-- Typing indicator -->
          <div x-show="typingDisplay" x-cloak class="px-4 py-1 text-[11px] text-[var(--t-text-muted)] italic flex items-center gap-1.5 border-t border-transparent">
            <span class="flex gap-0.5">
              <span class="w-1 h-1 rounded-full bg-[var(--t-text-muted)] animate-bounce" style="animation-delay:0ms"></span>
              <span class="w-1 h-1 rounded-full bg-[var(--t-text-muted)] animate-bounce" style="animation-delay:150ms"></span>
              <span class="w-1 h-1 rounded-full bg-[var(--t-text-muted)] animate-bounce" style="animation-delay:300ms"></span>
            </span>
            <span x-text="typingDisplay"></span>
          </div>

          <!-- Input (Tiptap Editor) — wire:ignore prevents morph from destroying ProseMirror DOM -->
          <div wire:key="terminal-editor-{{ $channelId }}"
               wire:ignore
               class="border-t border-[var(--t-border)]/60 flex-shrink-0"
               :class="fullscreen ? 'px-2' : ''"
               x-data="{
                 ...tiptapEditor({
                   placeholder: '{{ $this->activeChannel['type'] === 'dm' ? 'Nachricht an ' . e($this->activeChannel['name']) . ' …' : 'Nachricht in #' . e($this->activeChannel['name'] ?? 'Kontext') . ' schreiben …' }}',
                   fetchUsers: async (query) => {
                     const members = await $wire.getTeamMembers();
                     const q = (query || '').toLowerCase();
                     return members
                       .filter(m => m.name.toLowerCase().includes(q))
                       .map(m => ({ id: m.id, label: m.name, initials: m.initials, avatar: m.avatar || null }));
                   },
                   onSubmit: (html, text, json) => {
                     const mentions = [];
                     try {
                       if (json && json.content) {
                         const walk = (nodes) => {
                           for (const n of nodes) {
                             if (n.type === 'mention' && n.attrs && n.attrs.id) mentions.push(parseInt(n.attrs.id));
                             if (n.content) walk(n.content);
                           }
                         };
                         walk(json.content);
                       }
                     } catch(e) {}
                     const ids = $data.uploadedFiles.map(f => f.id);
                     $wire.sendMessage(html, text, null, mentions, ids);
                     $data.uploadedFiles = [];
                   },
                 }),
                 uploadedFiles: [],
                 uploading: false,
                 dragOver: false,
                 get canSend() {
                   return !this.isEmpty || this.uploadedFiles.length > 0;
                 },
                 handleFiles(files) {
                   if (!files || !files.length) return;
                   this.uploading = true;
                   $wire.uploadMultiple('pendingFiles', Array.from(files), () => {
                     $wire.uploadAttachments().then(results => {
                       this.uploadedFiles = [...this.uploadedFiles, ...results];
                       this.uploading = false;
                     });
                   }, () => { this.uploading = false; });
                 },
                 removeFile(index) {
                   this.uploadedFiles.splice(index, 1);
                 },
                 formatSize(bytes) {
                   if (bytes < 1024) return bytes + ' B';
                   if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                   return (bytes / 1048576).toFixed(1) + ' MB';
                 },
               }"
               x-on:dragover.prevent="dragOver = true"
               x-on:dragleave.prevent="dragOver = false"
               x-on:drop.prevent="dragOver = false; handleFiles($event.dataTransfer.files)"
               x-on:keydown="$dispatch('terminal-typing')"
          >
            {{-- Upload preview bar --}}
            <div x-show="uploadedFiles.length > 0 || uploading" x-cloak class="px-4 pt-2 pb-1">
              <div class="flex flex-wrap gap-2">
                <template x-for="(file, index) in uploadedFiles" :key="file.id">
                  <div class="relative group/file">
                    <template x-if="file.is_image">
                      <div class="w-12 h-12 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5">
                        <img :src="file.url" alt="" class="w-full h-full object-cover">
                      </div>
                    </template>
                    <template x-if="!file.is_image">
                      <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--t-border)]/60 bg-white/5 text-[11px] text-[var(--t-text)] max-w-[140px]">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
                        <span class="truncate" x-text="file.original_name"></span>
                      </div>
                    </template>
                    <button
                      @click="removeFile(index)"
                      class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white flex items-center justify-center text-[10px] opacity-0 group-hover/file:opacity-100 transition"
                    >&times;</button>
                  </div>
                </template>
                <template x-if="uploading">
                  <div class="w-12 h-12 rounded-md border border-[var(--t-border)]/60 bg-white/5 flex items-center justify-center">
                    <svg class="w-4 h-4 animate-spin text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                  </div>
                </template>
              </div>
            </div>

            {{-- Formatting toolbar --}}
            <div class="px-4 pt-1.5 pb-0 flex items-center gap-0.5">
              <button type="button" @click="editor?.chain().focus().toggleBulletList().run()" :class="editor?.isActive('bulletList') ? 'text-[var(--t-accent)] bg-[var(--t-accent)]/10' : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)]'" class="p-1 rounded transition" title="Aufzählung">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 4.75A.75.75 0 016.75 4h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 4.75zM6 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 10zm0 5.25a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75a.75.75 0 01-.75-.75zM1.99 4.75a1 1 0 011-1h.01a1 1 0 010 2h-.01a1 1 0 01-1-1zm0 5.25a1 1 0 011-1h.01a1 1 0 010 2h-.01a1 1 0 01-1-1zm1 4.25a1 1 0 100 2h.01a1 1 0 100-2h-.01z" clip-rule="evenodd"/></svg>
              </button>
              <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()" :class="editor?.isActive('orderedList') ? 'text-[var(--t-accent)] bg-[var(--t-accent)]/10' : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)]'" class="p-1 rounded transition" title="Nummerierung">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 4.75A.75.75 0 016.75 4h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 4.75zM6 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75A.75.75 0 016 10zm0 5.25a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H6.75a.75.75 0 01-.75-.75zM2 4a.75.75 0 01.75-.75h.5a.75.75 0 01.75.75v2.25h.25a.5.5 0 010 1H2a.5.5 0 010-1h.25V4.75H2A.75.75 0 012 4zm0 5.75a.5.5 0 01.5-.5h1a.75.75 0 01.53 1.28L2.56 12H3.5a.5.5 0 010 1h-1a.75.75 0 01-.53-1.28L3.44 10.25H2.5a.5.5 0 01-.5-.5z" clip-rule="evenodd"/></svg>
              </button>
              <button type="button" @click="editor?.chain().focus().toggleCodeBlock().run()" :class="editor?.isActive('codeBlock') ? 'text-[var(--t-accent)] bg-[var(--t-accent)]/10' : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)]'" class="p-1 rounded transition" title="Code-Block">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 010 1.06L2.56 10l3.72 3.72a.75.75 0 01-1.06 1.06L.97 10.53a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0zm7.44 0a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 010-1.06zM11.377 2.011a.75.75 0 01.612.867l-2.5 14.5a.75.75 0 01-1.478-.255l2.5-14.5a.75.75 0 01.866-.612z" clip-rule="evenodd"/></svg>
              </button>
            </div>

            <div class="px-4 py-2.5">
              <div class="flex items-end gap-2">
                {{-- Paperclip upload button --}}
                <button
                  type="button"
                  @click="$refs.fileInput.click()"
                  class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition flex-shrink-0"
                  title="Datei anhängen"
                >
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
                </button>
                <input x-ref="fileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

                <div class="flex-1 min-w-0 rounded-lg border transition-all"
                     :class="dragOver ? 'border-[var(--t-accent)] shadow-[0_0_0_1px_var(--t-accent)] bg-[var(--t-accent)]/10' : 'border-[var(--t-border)]/80 focus-within:border-[var(--t-accent)]/50 focus-within:shadow-[0_0_0_1px_var(--t-accent)]'">
                  <div x-ref="editorEl"></div>
                </div>
                <div x-ref="emojiSlot" class="flex-shrink-0"></div>
                <button
                  type="button"
                  @click="submit()"
                  :disabled="!canSend"
                  :class="canSend ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 cursor-pointer shadow-sm' : 'border border-[var(--t-border)]/60 text-[var(--t-text-muted)] opacity-40 cursor-not-allowed'"
                  class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0"
                >
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                </button>
              </div>
            </div>
          </div>

          </div>

          <!-- ═══ App: Aktivitäten ═══ -->
          <div x-show="$wire.activeApp === 'activity'" class="flex-1 min-h-0 flex flex-col" wire:key="terminal-activities-{{ $channelId }}">
            {{-- Scrollable activity list --}}
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
              <div class="py-4 space-y-1.5" :class="fullscreen ? 'px-6' : 'px-4'">
                @php
                  $filteredActivities = $this->activityFilter === 'all'
                    ? $this->contextActivities
                    : array_filter($this->contextActivities, fn($a) => ($a['activity_type'] ?? 'system') === $this->activityFilter);
                @endphp
                @forelse($filteredActivities as $act)
                  @if(($act['activity_type'] ?? 'system') === 'manual')
                    {{-- Manual note --}}
                    <div class="group flex items-start gap-2.5 py-2 px-3 rounded-lg hover:bg-white/[0.06] transition-colors" wire:key="act-{{ $act['id'] }}">
                      <div class="flex-shrink-0 mt-0.5">
                        <div class="w-7 h-7 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[10px] font-semibold overflow-hidden">
                          @if(! empty($act['user_avatar']))
                            <img src="{{ $act['user_avatar'] }}" alt="" class="w-full h-full object-cover">
                          @else
                            {{ $act['user_initials'] ?? '?' }}
                          @endif
                        </div>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 mb-0.5">
                          <span class="text-xs font-semibold text-[var(--t-text)]">{{ $act['user'] }}</span>
                          <span class="text-[10px] text-[var(--t-text-muted)]">{{ $act['time'] }}</span>
                        </div>
                        <p class="text-sm text-[var(--t-text)] leading-snug whitespace-pre-line">{{ $act['title'] }}</p>
                        @if(! empty($act['attachments']))
                          <div class="flex flex-wrap gap-1.5 mt-1.5">
                            @foreach($act['attachments'] as $att)
                              @if($att['is_image'])
                                <a href="{{ $att['url'] }}" target="_blank" class="block w-20 h-20 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5 hover:opacity-80 transition">
                                  <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}" class="w-full h-full object-cover">
                                </a>
                              @else
                                <a href="{{ $att['download_url'] }}" target="_blank" class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--t-border)]/60 bg-white/5 text-[11px] text-[var(--t-text)] hover:bg-white/[0.06] transition max-w-[180px]">
                                  <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
                                  <span class="truncate">{{ $att['original_name'] }}</span>
                                </a>
                              @endif
                            @endforeach
                          </div>
                        @endif
                      </div>
                      @if($act['is_mine'])
                        <button
                          wire:click="deleteActivityNote({{ $act['id'] }})"
                          wire:confirm="Notiz wirklich löschen?"
                          class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-[var(--t-text-muted)] hover:text-red-500 hover:bg-red-500/10 transition"
                          title="Notiz löschen"
                        >
                          @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        </button>
                      @endif
                    </div>
                  @else
                    {{-- System activity --}}
                    <div class="flex items-start gap-2.5 py-1.5 px-3" wire:key="act-{{ $act['id'] }}">
                      <div class="flex-shrink-0 w-7 h-7 rounded-full bg-[var(--t-text-muted)]/5 flex items-center justify-center mt-0.5">
                        @svg('heroicon-o-cog-6-tooth', 'w-3.5 h-3.5 text-[var(--t-text-muted)]/60')
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-xs text-[var(--t-text-muted)] leading-snug">{{ $act['title'] }}</p>
                        <span class="text-[10px] text-[var(--t-text-muted)]/50">{{ $act['time'] }}</span>
                      </div>
                    </div>
                  @endif
                @empty
                  <div class="py-8 text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--t-text-muted)]/5 mb-3">
                      @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--t-text-muted)]')
                    </div>
                    <p class="text-sm text-[var(--t-text-muted)]">Noch keine Aktivitäten</p>
                    <p class="text-xs text-[var(--t-text-muted)]/60 mt-1">Änderungen werden hier angezeigt</p>
                  </div>
                @endforelse
              </div>
            </div>

            {{-- Note input --}}
            <div class="border-t border-[var(--t-border)]/60 flex-shrink-0"
                 :class="fullscreen ? 'px-2' : ''"
                 x-data="{
                   noteText: '',
                   uploadedFiles: [],
                   uploading: false,
                   dragOver: false,
                   get canSend() {
                     return this.noteText.trim().length > 0 || this.uploadedFiles.length > 0;
                   },
                   submitNote() {
                     if (!this.canSend) return;
                     const ids = this.uploadedFiles.map(f => f.id);
                     $wire.addActivityNote(this.noteText.trim(), null, ids);
                     this.noteText = '';
                     this.uploadedFiles = [];
                   },
                   handleFiles(files) {
                     if (!files || !files.length) return;
                     this.uploading = true;
                     $wire.uploadMultiple('pendingFiles', Array.from(files), () => {
                       $wire.uploadAttachments().then(results => {
                         this.uploadedFiles = [...this.uploadedFiles, ...results];
                         this.uploading = false;
                       });
                     }, () => { this.uploading = false; });
                   },
                   removeFile(index) {
                     this.uploadedFiles.splice(index, 1);
                   },
                 }"
                 x-on:dragover.prevent="dragOver = true"
                 x-on:dragleave.prevent="dragOver = false"
                 x-on:drop.prevent="dragOver = false; handleFiles($event.dataTransfer.files)"
            >
              {{-- Upload preview bar --}}
              <div x-show="uploadedFiles.length > 0 || uploading" x-cloak class="px-4 pt-2 pb-1">
                <div class="flex flex-wrap gap-2">
                  <template x-for="(file, index) in uploadedFiles" :key="file.id">
                    <div class="relative group/file">
                      <template x-if="file.is_image">
                        <div class="w-12 h-12 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5">
                          <img :src="file.url" alt="" class="w-full h-full object-cover">
                        </div>
                      </template>
                      <template x-if="!file.is_image">
                        <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--t-border)]/60 bg-white/5 text-[11px] text-[var(--t-text)] max-w-[140px]">
                          <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
                          <span class="truncate" x-text="file.original_name"></span>
                        </div>
                      </template>
                      <button
                        @click="removeFile(index)"
                        class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white flex items-center justify-center text-[10px] opacity-0 group-hover/file:opacity-100 transition"
                      >&times;</button>
                    </div>
                  </template>
                  <template x-if="uploading">
                    <div class="w-12 h-12 rounded-md border border-[var(--t-border)]/60 bg-white/5 flex items-center justify-center">
                      <svg class="w-4 h-4 animate-spin text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </div>
                  </template>
                </div>
              </div>

              <div class="px-4 py-2.5">
                <div class="flex items-end gap-2">
                  {{-- Paperclip upload button --}}
                  <button
                    type="button"
                    @click="$refs.noteFileInput.click()"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition flex-shrink-0"
                    title="Datei anhängen"
                  >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
                  </button>
                  <input x-ref="noteFileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

                  <textarea
                    x-model="noteText"
                    @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); submitNote(); }"
                    placeholder="Notiz hinzufügen…"
                    rows="1"
                    class="flex-1 min-h-[36px] max-h-24 resize-none rounded-lg border border-[var(--t-border)]/60 bg-[var(--t-glass-surface)] px-3 py-2 text-sm text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:outline-none focus:border-[var(--t-accent)]/50 focus:ring-1 focus:ring-[var(--t-accent)]/20 transition"
                  ></textarea>
                  <button
                    type="button"
                    @click="submitNote()"
                    :disabled="!canSend"
                    :class="canSend ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 cursor-pointer shadow-sm' : 'border border-[var(--t-border)]/60 text-[var(--t-text-muted)] opacity-40 cursor-not-allowed'"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0"
                  >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- ═══ App: Dateien ═══ -->
          <div x-show="$wire.activeApp === 'files'" class="flex-1 min-h-0 flex flex-col">
            {{-- Scrollable file list --}}
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
              <div class="py-4 space-y-1" :class="fullscreen ? 'px-6' : 'px-4'">
                @php
                  $filteredFiles = match($this->filesFilter) {
                    'images' => array_filter($this->contextFiles, fn($f) => $f['is_image']),
                    'documents' => array_filter($this->contextFiles, fn($f) => ! $f['is_image']),
                    default => $this->contextFiles,
                  };
                @endphp
                @forelse($filteredFiles as $file)
                  @php $isSelected = in_array($file['id'], $this->filePickerSelected); @endphp
                  <div
                    class="group flex items-center gap-3 py-2 px-3 rounded-lg transition-colors
                      {{ $this->filePickerActive ? 'cursor-pointer' : '' }}
                      {{ $isSelected ? 'bg-[var(--t-accent)]/10 ring-2 ring-[var(--t-accent)]/40' : 'hover:bg-white/[0.06]' }}"
                    wire:key="ctxfile-{{ $file['id'] }}"
                    @if($this->filePickerActive) wire:click="toggleFilePickerSelection({{ $file['id'] }})" @endif
                  >
                    {{-- Selection indicator (picker mode) --}}
                    @if($this->filePickerActive)
                      <div class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition
                        {{ $isSelected ? 'border-[var(--t-accent)] bg-[var(--t-accent)] text-white' : 'border-[var(--t-border)]' }}">
                        @if($isSelected)
                          <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        @endif
                      </div>
                    @endif
                    {{-- Thumbnail / Icon --}}
                    <div class="flex-shrink-0 w-10 h-10 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5 flex items-center justify-center">
                      @if($file['is_image'] && ($file['thumbnail'] ?? $file['url']))
                        <img src="{{ $file['thumbnail'] ?? $file['url'] }}" alt="" class="w-full h-full object-cover">
                      @else
                        <svg class="w-5 h-5 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
                      @endif
                    </div>
                    {{-- File info --}}
                    <div class="flex-1 min-w-0">
                      <p class="text-xs font-medium text-[var(--t-text)] truncate" title="{{ $file['original_name'] }}">{{ $file['original_name'] }}</p>
                      <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] text-[var(--t-text-muted)]">{{ \Illuminate\Support\Number::fileSize($file['file_size']) }}</span>
                        <span class="text-[10px] text-[var(--t-text-muted)]">&middot;</span>
                        <span class="text-[10px] text-[var(--t-text-muted)]">{{ $file['uploaded_by'] }}</span>
                        <span class="text-[10px] text-[var(--t-text-muted)]">&middot;</span>
                        <span class="text-[10px] text-[var(--t-text-muted)]">{{ $file['created_at'] }}</span>
                      </div>
                    </div>
                    {{-- Actions (hidden in picker mode) --}}
                    @if(! $this->filePickerActive)
                      <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                        @if($file['is_image'] && $file['url'])
                          <a href="{{ $file['url'] }}" target="_blank" class="p-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition" title="Ansehen">
                            @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                          </a>
                        @endif
                        <a href="{{ $file['download_url'] }}" target="_blank" class="p-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition" title="Download">
                          @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                        </a>
                        <button
                          @click.stop="if(confirm('Datei wirklich löschen?')) $wire.deleteContextFile({{ $file['id'] }})"
                          class="p-1.5 rounded text-[var(--t-text-muted)] hover:text-red-500 hover:bg-red-500/10 transition"
                          title="Löschen"
                        >
                          @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        </button>
                      </div>
                    @endif
                  </div>
                @empty
                  <div class="py-8 text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--t-text-muted)]/5 mb-3">
                      @svg('heroicon-o-paper-clip', 'w-6 h-6 text-[var(--t-text-muted)]')
                    </div>
                    <p class="text-sm text-[var(--t-text-muted)]">Keine Dateien</p>
                    <p class="text-xs text-[var(--t-text-muted)]/60 mt-1">Lade Dateien hoch per Drag & Drop oder Button</p>
                  </div>
                @endforelse
              </div>
            </div>

            {{-- Bottom bar: Picker confirmation OR Upload area --}}
            @if($this->filePickerActive)
              {{-- Picker selection bar --}}
              <div class="border-t border-[var(--t-border)]/60 flex-shrink-0 px-4 py-2.5">
                <div class="flex items-center gap-3">
                  <div class="flex-1 text-xs">
                    @if(count($this->filePickerSelected) > 0)
                      <span class="font-medium text-[var(--t-accent)]">{{ count($this->filePickerSelected) }} {{ count($this->filePickerSelected) === 1 ? 'Datei' : 'Dateien' }} ausgewählt</span>
                    @else
                      <span class="text-[var(--t-text-muted)]">Dateien auswählen…</span>
                    @endif
                  </div>
                  <button
                    wire:click="cancelFilePicker"
                    class="px-3 py-1.5 rounded text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition"
                  >Abbrechen</button>
                  <button
                    wire:click="confirmFilePicker"
                    @if(empty($this->filePickerSelected)) disabled @endif
                    class="px-3 py-1.5 rounded text-xs font-medium transition
                      {{ ! empty($this->filePickerSelected) ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/90' : 'bg-[var(--t-text-muted)]/10 text-[var(--t-text-muted)] cursor-not-allowed' }}"
                  >Auswählen</button>
                </div>
              </div>
            @else
              {{-- Upload area --}}
              <div class="border-t border-[var(--t-border)]/60 flex-shrink-0"
                   :class="fullscreen ? 'px-2' : ''"
                   x-data="{
                     selectedFiles: [],
                     uploading: false,
                     dragOver: false,
                     handleFiles(files) {
                       if (!files || !files.length) return;
                       this.uploading = true;
                       $wire.uploadMultiple('pendingFiles', Array.from(files), () => {
                         $wire.uploadContextFiles().then(() => {
                           this.uploading = false;
                           this.selectedFiles = [];
                         });
                       }, () => { this.uploading = false; });
                     },
                   }"
                   x-on:dragover.prevent="dragOver = true"
                   x-on:dragleave.prevent="dragOver = false"
                   x-on:drop.prevent="dragOver = false; handleFiles($event.dataTransfer.files)"
              >
                <div class="px-4 py-2.5">
                  <div class="flex items-center gap-2"
                       :class="dragOver ? 'ring-2 ring-[var(--t-accent)]/40 ring-offset-1 rounded-lg' : ''"
                  >
                    <button
                      type="button"
                      @click="$refs.contextFileInput.click()"
                      class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition flex-shrink-0"
                      title="Dateien auswählen"
                    >
                      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
                    </button>
                    <input x-ref="contextFileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

                    <div class="flex-1 text-xs text-[var(--t-text-muted)]" x-show="!uploading">
                      <span :class="dragOver ? 'text-[var(--t-accent)] font-medium' : ''">
                        <span x-show="dragOver">Dateien hier ablegen</span>
                        <span x-show="!dragOver">Dateien hochladen per Drag & Drop oder Klick</span>
                      </span>
                    </div>
                    <div class="flex-1 flex items-center gap-2 text-xs text-[var(--t-text-muted)]" x-show="uploading" x-cloak>
                      <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                      <span>Wird hochgeladen…</span>
                    </div>
                  </div>
                </div>
              </div>
            @endif
          </div>

          <!-- ═══ App: Tags ═══ -->
          <div x-show="$wire.activeApp === 'tags'" class="flex-1 min-h-0 flex flex-col"
               x-data="{ tagSearch: '', personalMode: false }">
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
              <div class="py-4 space-y-5" :class="fullscreen ? 'px-6' : 'px-4'">

                @if($this->contextType && $this->contextId)
                  {{-- ── Section A: Assigned Tags ── --}}
                  <div>
                    <div class="flex items-center justify-between mb-2">
                      <h4 class="text-xs font-semibold text-[var(--t-text)]">Zugeordnet</h4>
                      <button
                        @click="personalMode = !personalMode"
                        class="flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full transition"
                        :class="personalMode ? 'bg-purple-500/15 text-purple-400' : 'bg-[var(--t-accent)]/15 text-[var(--t-accent)]'"
                      >
                        <svg x-show="!personalMode" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z"/></svg>
                        <svg x-show="personalMode" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                        <span x-text="personalMode ? 'Persönlich' : 'Team'"></span>
                      </button>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                      @foreach($teamTags as $tag)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium transition group"
                              style="background-color: {{ $tag['color'] ? $tag['color'] . '20' : 'var(--t-accent)' . '20' }}; border: 1px solid {{ $tag['color'] ? $tag['color'] . '40' : 'var(--t-accent)' . '40' }}; color: {{ $tag['color'] ?: 'var(--t-accent)' }}">
                          @if($tag['color'])
                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $tag['color'] }}"></span>
                          @endif
                          {{ $tag['label'] }}
                          <span class="text-[8px] opacity-60">T</span>
                          <button wire:click="toggleTag({{ $tag['id'] }}, false)"
                                  class="ml-0.5 opacity-40 hover:opacity-100 transition">
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                          </button>
                        </span>
                      @endforeach
                      @foreach($personalTags as $tag)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium transition group"
                              style="background-color: {{ $tag['color'] ? $tag['color'] . '20' : 'var(--t-accent)' . '20' }}; border: 1px solid {{ $tag['color'] ? $tag['color'] . '40' : 'var(--t-accent)' . '40' }}; color: {{ $tag['color'] ?: 'var(--t-accent)' }}">
                          @if($tag['color'])
                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $tag['color'] }}"></span>
                          @endif
                          {{ $tag['label'] }}
                          <span class="text-[8px] opacity-60">P</span>
                          <button wire:click="toggleTag({{ $tag['id'] }}, true)"
                                  class="ml-0.5 opacity-40 hover:opacity-100 transition">
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                          </button>
                        </span>
                      @endforeach
                      @if(empty($teamTags) && empty($personalTags))
                        <span class="text-[11px] text-[var(--t-text-muted)]">Noch keine Tags zugeordnet.</span>
                      @endif
                    </div>
                  </div>

                  {{-- ── Section B: Available Tags ── --}}
                  <div>
                    <div class="flex items-center justify-between mb-2">
                      <h4 class="text-xs font-semibold text-[var(--t-text)]">Verfügbar</h4>
                      <div class="relative flex-1 max-w-[180px] ml-3">
                        <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                        <input type="text"
                               x-model="tagSearch"
                               placeholder="Filtern..."
                               class="w-full pl-7 pr-2 py-1 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent" />
                      </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                      @forelse($availableTags as $tag)
                        <button
                          x-show="!tagSearch || {{ Js::from(strtolower($tag['label'])) }}.includes(tagSearch.toLowerCase())"
                          @click="$wire.toggleTag({{ $tag['id'] }}, personalMode)"
                          class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium border border-[var(--t-border)]/40 text-[var(--t-text)] hover:bg-white/[0.06] transition"
                        >
                          @if($tag['color'])
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                          @endif
                          {{ $tag['label'] }}
                        </button>
                      @empty
                        <span class="text-[11px] text-[var(--t-text-muted)]">Keine weiteren Tags verfügbar.</span>
                      @endforelse
                    </div>
                  </div>

                  {{-- ── Section C: Color Palette ── --}}
                  <div>
                    <div class="flex items-center justify-between mb-2">
                      <h4 class="text-xs font-semibold text-[var(--t-text)]">Farbe</h4>
                      @if($contextColor)
                        <button wire:click="removeColor" class="text-[10px] text-red-400 hover:text-red-400/80 transition">Entfernen</button>
                      @endif
                    </div>

                    @php
                      $colorPresets = [
                        '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4',
                        '#3b82f6', '#8b5cf6', '#ec4899', '#6b7280', '#1e293b',
                      ];
                    @endphp

                    <div class="flex flex-wrap gap-2 items-center">
                      @foreach($colorPresets as $preset)
                        <button
                          wire:click="setColorPreset('{{ $preset }}')"
                          class="w-6 h-6 rounded-full border-2 transition-all hover:scale-110 flex items-center justify-center {{ $contextColor === $preset ? 'ring-2 ring-offset-1 ring-[var(--t-accent)] border-white/30' : 'border-transparent hover:border-white/20' }}"
                          style="background-color: {{ $preset }}"
                          title="{{ $preset }}"
                        >
                          @if($contextColor === $preset)
                            <svg class="w-3 h-3 text-white drop-shadow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                          @endif
                        </button>
                      @endforeach

                      {{-- Custom color picker --}}
                      <div class="relative" x-data="{ customColor: '{{ $contextColor ?? '#6b7280' }}' }">
                        <input type="color"
                               x-model="customColor"
                               @change="$wire.setColorPreset(customColor)"
                               class="w-6 h-6 rounded-full cursor-pointer border border-[var(--t-border)]/40 p-0 overflow-hidden appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch]:rounded-full" />
                      </div>

                      @if($contextColor)
                        <span class="text-[10px] text-[var(--t-text-muted)] font-mono ml-1">{{ $contextColor }}</span>
                      @endif
                    </div>
                  </div>

                  {{-- ── Section D: Create New Tag ── --}}
                  <div>
                    <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Neues Tag erstellen</h4>
                    <div class="flex items-center gap-2">
                      <input type="text"
                             wire:model="tagInput"
                             placeholder="Name eingeben..."
                             class="flex-1 px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent"
                             @keydown.enter="$wire.set('newTagIsPersonal', personalMode).then(() => $wire.createAndAddTag())" />
                      <input type="color"
                             wire:model="newTagColor"
                             class="w-7 h-7 rounded cursor-pointer border border-[var(--t-border)]/40 p-0 overflow-hidden appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch]:rounded"
                             title="Farbe wählen" />
                      <button @click="$wire.set('newTagIsPersonal', personalMode).then(() => $wire.createAndAddTag())"
                              class="px-3 py-1.5 bg-[var(--t-accent)] text-white rounded-md text-[11px] font-medium hover:bg-[var(--t-accent)]/90 transition whitespace-nowrap">
                        Erstellen
                      </button>
                    </div>
                  </div>

                @else
                  {{-- No context — show overview of all tags + colors --}}
                  <div class="space-y-6">
                    <div>
                      <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Alle Tags</h4>
                      <div class="space-y-1">
                        @forelse($allTags as $tag)
                          <div class="flex items-center justify-between p-2 rounded-md hover:bg-white/[0.03] transition">
                            <div class="flex items-center gap-1.5 min-w-0">
                              <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] ?? 'var(--t-text-muted)' }}"></div>
                              <span class="text-xs font-medium text-[var(--t-text)] truncate">{{ $tag['label'] }}</span>
                              <span class="text-[9px] text-[var(--t-text-muted)] px-1 py-px bg-[var(--t-text-muted)]/5 rounded flex-shrink-0">{{ $tag['is_team_tag'] ? 'Team' : 'Global' }}</span>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                              <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $tag['total_count'] }}x</span>
                              @if($tag['total_count'] === 0)
                                <button
                                  wire:click="deleteTag({{ $tag['id'] }})"
                                  wire:confirm="Tag wirklich löschen?"
                                  class="text-[9px] text-red-400 hover:text-red-400/80 px-1 py-0.5 rounded hover:bg-red-400/5 transition"
                                >Löschen</button>
                              @endif
                            </div>
                          </div>
                        @empty
                          <div class="py-4 text-center">
                            <p class="text-xs text-[var(--t-text-muted)]">Noch keine Tags vorhanden.</p>
                          </div>
                        @endforelse
                      </div>
                    </div>

                    <div>
                      <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Alle Farben</h4>
                      <div class="space-y-1">
                        @forelse($allColors as $color)
                          <div class="flex items-center justify-between p-2 rounded-md hover:bg-white/[0.03] transition">
                            <div class="flex items-center gap-2">
                              <div class="w-6 h-6 rounded-md border border-[var(--t-border)]/40" style="background-color: {{ $color['color'] }}"></div>
                              <span class="text-xs font-medium text-[var(--t-text)] font-mono">{{ $color['color'] }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-[10px] text-[var(--t-text-muted)] tabular-nums">
                              <span>{{ $color['total_count'] }}x</span>
                              <span>T:{{ $color['team_count'] }}</span>
                              <span>P:{{ $color['personal_count'] }}</span>
                            </div>
                          </div>
                        @empty
                          <div class="py-4 text-center">
                            <p class="text-xs text-[var(--t-text-muted)]">Noch keine Farben verwendet.</p>
                          </div>
                        @endforelse
                      </div>
                    </div>

                    {{-- Create tag even without context --}}
                    <div>
                      <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Neues Tag erstellen</h4>
                      <div class="flex items-center gap-2">
                        <input type="text"
                               wire:model="tagInput"
                               placeholder="Name eingeben..."
                               class="flex-1 px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent"
                               @keydown.enter="$wire.createAndAddTag()" />
                        <input type="color"
                               wire:model="newTagColor"
                               class="w-7 h-7 rounded cursor-pointer border border-[var(--t-border)]/40 p-0 overflow-hidden appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch]:rounded"
                               title="Farbe wählen" />
                        <button wire:click="createAndAddTag"
                                class="px-3 py-1.5 bg-[var(--t-accent)] text-white rounded-md text-[11px] font-medium hover:bg-[var(--t-accent)]/90 transition whitespace-nowrap">
                          Erstellen
                        </button>
                      </div>
                    </div>
                  </div>
                @endif

              </div>
            </div>
          </div>

          <!-- ═══ App: Zeit ═══ -->
          <div x-show="$wire.activeApp === 'time'" class="flex-1 min-h-0 flex flex-col">
            <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
              <div class="py-4 space-y-4" :class="fullscreen ? 'px-6' : 'px-4'">

                @if($this->contextType && $this->contextId)
                  @php
                    $tStats = $this->timeStats;
                    $tPlanned = $this->timePlannedEntries;
                    $tEntries = $this->timeEntries;
                    $tUsers = $this->timeAvailableUsers;
                    $tTotalPlanned = $tStats['totalPlannedMinutes'];
                    $tTotalMin = $tStats['totalMinutes'];
                    $tBilledMin = $tStats['billedMinutes'];
                    $tUnbilledMin = $tStats['unbilledMinutes'];
                    $tUnbilledCents = $tStats['unbilledAmountCents'];
                  @endphp

                  {{-- Budget bar --}}
                  @if($tTotalPlanned)
                    @php
                      $budgetPct = $tTotalPlanned > 0 ? min(100, round(($tTotalMin / $tTotalPlanned) * 100)) : 0;
                      $budgetColor = $budgetPct >= 100 ? '#ef4444' : ($budgetPct >= 80 ? '#f59e0b' : '#10b981');
                    @endphp
                    <div class="p-3 rounded-lg border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)]">
                      <div class="flex items-center justify-between text-[11px] mb-1.5">
                        <span class="text-[var(--t-text-muted)]">Budget</span>
                        <span class="font-medium text-[var(--t-text)]">{{ number_format($tTotalMin / 60, 1, ',', '.') }}h / {{ number_format($tTotalPlanned / 60, 1, ',', '.') }}h</span>
                      </div>
                      <div class="h-2 rounded-full bg-[var(--t-border)]/30 overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: {{ $budgetPct }}%; background-color: {{ $budgetColor }}"></div>
                      </div>
                      <div class="text-[10px] text-[var(--t-text-muted)] mt-1 text-right">{{ $budgetPct }}%</div>
                    </div>
                  @endif

                  {{-- Filters --}}
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="flex gap-1">
                      @foreach(['all' => 'Alle', 'current_week' => 'Woche', 'current_month' => 'Monat', 'current_year' => 'Jahr'] as $range => $label)
                        <button wire:click="$set('timeOverviewRange', '{{ $range }}')"
                                class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timeOverviewRange === $range ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/40 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-[var(--t-glass-surface)]' }}">
                          {{ $label }}
                        </button>
                      @endforeach
                    </div>

                    @if(count($tUsers) > 1)
                      <select wire:model.live="timeSelectedUserId"
                              class="px-2 py-0.5 text-[10px] bg-[var(--t-glass-surface)] border border-[var(--t-border)]/40 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
                        <option value="">Alle Personen</option>
                        @foreach($tUsers as $u)
                          <option value="{{ $u['id'] }}">{{ $u['name'] }}</option>
                        @endforeach
                      </select>
                    @endif
                  </div>

                  {{-- Stats tiles --}}
                  <div class="grid grid-cols-4 gap-2">
                    <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
                      <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Gesamt</div>
                      <div class="text-sm font-bold text-[var(--t-text)] tabular-nums">{{ number_format($tTotalMin / 60, 1, ',', '.') }}h</div>
                    </div>
                    <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
                      <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Abgerechnet</div>
                      <div class="text-sm font-bold text-emerald-500 tabular-nums">{{ number_format($tBilledMin / 60, 1, ',', '.') }}h</div>
                    </div>
                    <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
                      <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Offen</div>
                      <div class="text-sm font-bold text-amber-500 tabular-nums">{{ number_format($tUnbilledMin / 60, 1, ',', '.') }}h</div>
                    </div>
                    <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
                      <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Offen €</div>
                      <div class="text-sm font-bold text-amber-500 tabular-nums">{{ number_format($tUnbilledCents / 100, 2, ',', '.') }}</div>
                    </div>
                  </div>

                  {{-- Budget entries (collapsible) --}}
                  @if(!empty($tPlanned))
                    <div x-data="{ budgetOpen: false }">
                      <button @click="budgetOpen = !budgetOpen" class="flex items-center gap-1.5 text-[11px] font-semibold text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition mb-1">
                        <svg class="w-3 h-3 transition-transform" :class="budgetOpen && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                        Budgets ({{ count($tPlanned) }})
                      </button>
                      <div x-show="budgetOpen" x-collapse class="space-y-1">
                        @foreach($tPlanned as $pe)
                          <div class="flex items-center justify-between px-3 py-1.5 rounded-md border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)] text-[11px]">
                            <div class="flex items-center gap-2 min-w-0">
                              <span class="font-medium text-[var(--t-text)] tabular-nums">{{ number_format($pe['planned_minutes'] / 60, 1, ',', '.') }}h</span>
                              @if($pe['note'])
                                <span class="text-[var(--t-text-muted)] truncate">{{ $pe['note'] }}</span>
                              @endif
                              <span class="text-[9px] text-[var(--t-text-muted)]">{{ $pe['user_name'] }} · {{ $pe['created_at'] }}</span>
                            </div>
                            <button wire:click="deleteTimePlanned({{ $pe['id'] }})"
                                    wire:confirm="Budget-Eintrag deaktivieren?"
                                    class="flex-shrink-0 p-1 rounded hover:bg-red-500/10 text-[var(--t-text-muted)] hover:text-red-400 transition">
                              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                            </button>
                          </div>
                        @endforeach
                      </div>
                    </div>
                  @endif

                  {{-- Entries table --}}
                  <div>
                    <h4 class="text-[11px] font-semibold text-[var(--t-text)] mb-2">Einträge ({{ count($tEntries) }})</h4>
                    @if(!empty($tEntries))
                      <div class="space-y-1">
                        @foreach($tEntries as $entry)
                          <div class="flex items-center gap-2 px-3 py-2 rounded-md border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)] text-[11px] group">
                            {{-- Date --}}
                            <span class="text-[var(--t-text-muted)] tabular-nums w-16 flex-shrink-0">{{ $entry['work_date'] }}</span>

                            {{-- Duration --}}
                            <span class="font-medium text-[var(--t-text)] tabular-nums w-10 flex-shrink-0">{{ number_format($entry['minutes'] / 60, 1, ',', '.') }}h</span>

                            {{-- Amount --}}
                            <span class="text-[var(--t-text-muted)] tabular-nums w-14 flex-shrink-0 text-right">
                              @if($entry['amount_cents'])
                                {{ number_format($entry['amount_cents'] / 100, 2, ',', '.') }}€
                              @else
                                –
                              @endif
                            </span>

                            {{-- User --}}
                            <span class="text-[var(--t-text-muted)] truncate flex-1 min-w-0">
                              {{ $entry['user_name'] }}
                              @if($entry['note'])
                                <span class="text-[var(--t-text-muted)]/60"> · {{ Str::limit($entry['note'], 30) }}</span>
                              @endif
                            </span>

                            {{-- Billed status toggle --}}
                            <button wire:click="toggleTimeBilled({{ $entry['id'] }})"
                                    class="flex-shrink-0 px-1.5 py-0.5 rounded text-[9px] font-medium transition border {{ $entry['is_billed'] ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/25' : 'bg-amber-500/10 border-amber-500/20 text-amber-400 hover:bg-amber-500/20' }}"
                                    title="{{ $entry['is_billed'] ? 'Abgerechnet — klicken zum Ändern' : 'Offen — klicken zum Abrechnen' }}">
                              {{ $entry['is_billed'] ? '✓ Abgr.' : 'Offen' }}
                            </button>

                            {{-- Delete --}}
                            <button wire:click="deleteTimeEntry({{ $entry['id'] }})"
                                    wire:confirm="Zeiteintrag löschen?"
                                    class="flex-shrink-0 p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-red-500/10 text-[var(--t-text-muted)] hover:text-red-400 transition">
                              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                            </button>
                          </div>
                        @endforeach
                      </div>
                    @else
                      <div class="py-6 text-center">
                        <div class="text-2xl opacity-20 mb-2">⏱️</div>
                        <p class="text-[11px] text-[var(--t-text-muted)]">Noch keine Zeiteinträge vorhanden.</p>
                      </div>
                    @endif
                  </div>

                @else
                  <div class="py-8 text-center">
                    <div class="text-3xl opacity-20 mb-3">⏱️</div>
                    <p class="text-sm font-medium text-[var(--t-text)]">Kein Kontext ausgewählt</p>
                    <p class="text-xs text-[var(--t-text-muted)] mt-1">Öffne ein Element um Zeiten zu erfassen.</p>
                  </div>
                @endif

              </div>
            </div>
          </div>

          <!-- ═══ App: OKR (Platzhalter) ═══ -->
          <div x-show="$wire.activeApp === 'okr'" class="flex-1 min-h-0 flex flex-col">
            <div class="flex-1 flex items-center justify-center">
              <div class="text-center py-12">
                <div class="text-3xl opacity-20 mb-3">🎯</div>
                <p class="text-sm font-medium text-[var(--t-text)]">OKR KeyResults</p>
                <p class="text-xs text-[var(--t-text-muted)] mt-1 max-w-[200px] mx-auto">Wird überarbeitet — KeyResult-Verknüpfungen werden hier integriert.</p>
              </div>
            </div>
          </div>

          <!-- ═══ App: ExtraFields ═══ -->
          <div x-show="$wire.activeApp === 'extrafields'" class="flex-1 min-h-0 flex flex-col overflow-y-auto">

            {{-- Field Editor (5 sub-tabs) --}}
            @if($this->efEditingDefinitionId)
              @php $editDef = collect($this->efDefinitions)->firstWhere('id', $this->efEditingDefinitionId); @endphp
              <div class="flex-1 flex flex-col" :class="fullscreen ? 'p-6' : 'p-4'">
                {{-- Sub-tab navigation --}}
                <div class="flex items-center gap-1 mb-4 border-b border-[var(--ui-border)]/40 pb-2">
                  @foreach(['basis' => 'Basis', 'options' => 'Optionen', 'conditions' => 'Bedingungen', 'verification' => 'Verifizierung', 'autofill' => 'Autofill'] as $tabKey => $tabLabel)
                    <button wire:click="$set('efEditFieldTab', '{{ $tabKey }}')"
                            class="px-3 py-1.5 text-[11px] font-medium rounded-md transition {{ $this->efEditFieldTab === $tabKey ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)] hover:bg-[var(--ui-muted-5)]' }}">
                      {{ $tabLabel }}
                    </button>
                  @endforeach
                </div>

                {{-- Sub-tab: Basis --}}
                @if($this->efEditFieldTab === 'basis')
                  <div class="space-y-4 max-w-lg">
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Label</label>
                      <input type="text" wire:model="efEditField.label"
                             class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                      @error('efEditField.label') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Beschreibung</label>
                      <textarea wire:model="efEditField.description" rows="2"
                                class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 resize-none"></textarea>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Typ</label>
                      <select wire:model.live="efEditField.type"
                              class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @foreach($this->efAvailableTypes() as $typeKey => $typeLabel)
                          <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Platzhalter</label>
                      <input type="text" wire:model="efEditField.placeholder" placeholder="Platzhalter-Text (optional)"
                             class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                    </div>
                    <div class="flex flex-wrap gap-4">
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_required" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Pflichtfeld
                      </label>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_mandatory" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mandatory
                      </label>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_encrypted" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Verschlüsselt
                      </label>
                    </div>
                  </div>
                @endif

                {{-- Sub-tab: Optionen --}}
                @if($this->efEditFieldTab === 'options')
                  <div class="space-y-4 max-w-lg">
                    @if($this->efEditField['type'] === 'select')
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Optionen</label>
                        <div class="flex gap-2 mb-2">
                          <input type="text" wire:model="efEditOptionText" wire:keydown.enter="efAddEditOption" placeholder="Option hinzufügen"
                                 class="flex-1 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                          <button wire:click="efAddEditOption" class="px-3 py-2 text-sm bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/80 transition">+</button>
                        </div>
                        @if(!empty($this->efEditField['options']))
                          <div class="flex flex-wrap gap-1.5">
                            @foreach($this->efEditField['options'] as $i => $opt)
                              <span class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-[var(--ui-muted-5)] text-[var(--ui-text)] rounded-md border border-[var(--ui-border)]/40">
                                {{ $opt }}
                                <button wire:click="efRemoveEditOption({{ $i }})" class="text-[var(--ui-muted)] hover:text-red-500">&times;</button>
                              </span>
                            @endforeach
                          </div>
                        @endif
                        @error('efEditField.options') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                      </div>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_multiple" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mehrfachauswahl
                      </label>
                    @elseif($this->efEditField['type'] === 'lookup')
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Lookup</label>
                        <select wire:model="efEditField.lookup_id"
                                class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                          <option value="">Lookup wählen…</option>
                          @foreach($this->efLookups as $lu)
                            <option value="{{ $lu['id'] }}">{{ $lu['label'] }} ({{ $lu['values_count'] }})</option>
                          @endforeach
                        </select>
                        @error('efEditField.lookup_id') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                      </div>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_multiple" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mehrfachauswahl
                      </label>
                    @elseif($this->efEditField['type'] === 'regex')
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Regex Pattern</label>
                        <input type="text" wire:model="efEditField.regex_pattern"
                               class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                        @error('efEditField.regex_pattern') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Pattern-Beschreibung</label>
                        <input type="text" wire:model="efEditField.regex_description" placeholder="z.B. Nur Buchstaben und Zahlen"
                               class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Fehlermeldung</label>
                        <input type="text" wire:model="efEditField.regex_error" placeholder="Benutzerdefinierte Fehlermeldung"
                               class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                      </div>
                    @elseif($this->efEditField['type'] === 'file')
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_multiple" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mehrere Dateien
                      </label>
                    @else
                      <p class="text-sm text-[var(--ui-muted)]">Keine typ-spezifischen Optionen für diesen Feldtyp.</p>
                    @endif
                  </div>
                @endif

                {{-- Sub-tab: Bedingungen --}}
                @if($this->efEditFieldTab === 'conditions')
                  <div class="space-y-4 max-w-2xl">
                    <div class="flex items-center justify-between">
                      <label class="text-sm font-medium text-[var(--ui-text)]">Bedingte Sichtbarkeit</label>
                      <button wire:click="efToggleVisibilityEnabled"
                              class="px-3 py-1 text-xs rounded-md transition {{ ($this->efEditField['visibility']['enabled'] ?? false) ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">
                        {{ ($this->efEditField['visibility']['enabled'] ?? false) ? 'Aktiv' : 'Inaktiv' }}
                      </button>
                    </div>

                    @if($this->efEditField['visibility']['enabled'] ?? false)
                      <p class="text-xs text-[var(--ui-muted)] italic">{{ $this->efVisibilityDescription() }}</p>
                      @error('efEditField.visibility') <span class="text-xs text-red-500">{{ $message }}</span> @enderror

                      {{-- Main logic toggle --}}
                      <div class="flex items-center gap-2">
                        <span class="text-xs text-[var(--ui-muted)]">Gruppen-Logik:</span>
                        <button wire:click="efSetVisibilityLogic('AND')"
                                class="px-2 py-0.5 text-[10px] rounded transition {{ ($this->efEditField['visibility']['logic'] ?? 'AND') === 'AND' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">AND</button>
                        <button wire:click="efSetVisibilityLogic('OR')"
                                class="px-2 py-0.5 text-[10px] rounded transition {{ ($this->efEditField['visibility']['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">OR</button>
                      </div>

                      {{-- Condition groups --}}
                      @foreach($this->efEditField['visibility']['groups'] ?? [] as $gi => $group)
                        <div class="border border-[var(--ui-border)]/40 rounded-lg p-3 space-y-2">
                          <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                              <span class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase">Gruppe {{ $gi + 1 }}</span>
                              <button wire:click="efSetGroupLogic({{ $gi }}, 'AND')"
                                      class="px-1.5 py-0.5 text-[9px] rounded transition {{ ($group['logic'] ?? 'AND') === 'AND' ? 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }}">AND</button>
                              <button wire:click="efSetGroupLogic({{ $gi }}, 'OR')"
                                      class="px-1.5 py-0.5 text-[9px] rounded transition {{ ($group['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }}">OR</button>
                            </div>
                            <button wire:click="efRemoveConditionGroup({{ $gi }})" class="text-[var(--ui-muted)] hover:text-red-500 transition">
                              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                            </button>
                          </div>

                          @foreach($group['conditions'] ?? [] as $ci => $condition)
                            <div class="flex items-start gap-1.5">
                              {{-- Field select --}}
                              <select wire:change="efUpdateConditionField({{ $gi }}, {{ $ci }}, $event.target.value)"
                                      class="flex-1 px-2 py-1.5 text-[11px] border border-[var(--ui-border)] rounded bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]/30">
                                <option value="">Feld…</option>
                                @foreach($this->efConditionFields() as $cf)
                                  <option value="{{ $cf['name'] }}" {{ ($condition['field'] ?? '') === $cf['name'] ? 'selected' : '' }}>{{ $cf['label'] }}</option>
                                @endforeach
                              </select>

                              {{-- Operator select --}}
                              @if(!empty($condition['field']))
                                @php $ops = $this->efGetOperatorsForField($condition['field']); @endphp
                                <select wire:change="efUpdateConditionOperator({{ $gi }}, {{ $ci }}, $event.target.value)"
                                        class="w-28 px-2 py-1.5 text-[11px] border border-[var(--ui-border)] rounded bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]/30">
                                  @foreach($ops as $opKey => $opLabel)
                                    <option value="{{ $opKey }}" {{ ($condition['operator'] ?? '') === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                                  @endforeach
                                </select>
                              @endif

                              {{-- Value input --}}
                              @if(!empty($condition['field']) && !empty($condition['operator']))
                                @php
                                  $opMeta = \Platform\Core\Services\ExtraFieldConditionEvaluator::OPERATORS[$condition['operator']] ?? null;
                                  $requiresValue = $opMeta['requiresValue'] ?? true;
                                @endphp
                                @if($requiresValue && !in_array($condition['operator'], ['is_in', 'is_not_in']))
                                  <input type="text" wire:change="efUpdateConditionValue({{ $gi }}, {{ $ci }}, $event.target.value)"
                                         value="{{ $condition['value'] ?? '' }}"
                                         placeholder="Wert"
                                         class="w-32 px-2 py-1.5 text-[11px] border border-[var(--ui-border)] rounded bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]/30" />
                                @endif
                              @endif

                              <button wire:click="efRemoveCondition({{ $gi }}, {{ $ci }})" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition flex-shrink-0">
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                              </button>
                            </div>
                          @endforeach

                          <button wire:click="efAddCondition({{ $gi }})"
                                  class="text-[10px] text-[var(--ui-primary)] hover:underline">+ Bedingung</button>
                        </div>
                      @endforeach

                      <button wire:click="efAddConditionGroup"
                              class="text-xs text-[var(--ui-primary)] hover:underline">+ Gruppe hinzufügen</button>
                    @endif
                  </div>
                @endif

                {{-- Sub-tab: Verifizierung --}}
                @if($this->efEditFieldTab === 'verification')
                  <div class="space-y-4 max-w-lg">
                    @if($this->efEditField['type'] === 'file')
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.verify_by_llm" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        LLM-Verifizierung aktivieren
                      </label>
                      @if($this->efEditField['verify_by_llm'])
                        <div>
                          <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Verifizierungs-Anweisungen</label>
                          <textarea wire:model="efEditField.verify_instructions" rows="4"
                                    placeholder="Anweisungen für die LLM-Verifizierung…"
                                    class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 resize-none"></textarea>
                        </div>
                      @endif
                    @else
                      <p class="text-sm text-[var(--ui-muted)]">LLM-Verifizierung ist nur für Datei-Felder verfügbar.</p>
                    @endif
                  </div>
                @endif

                {{-- Sub-tab: Autofill --}}
                @if($this->efEditFieldTab === 'autofill')
                  <div class="space-y-4 max-w-lg">
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Autofill-Quelle</label>
                      <select wire:model.live="efEditField.auto_fill_source"
                              class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <option value="">Kein Autofill</option>
                        @foreach($this->efAutoFillSources() as $srcKey => $srcLabel)
                          <option value="{{ $srcKey }}">{{ $srcLabel }}</option>
                        @endforeach
                      </select>
                    </div>
                    @if(!empty($this->efEditField['auto_fill_source']))
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Autofill-Prompt</label>
                        <textarea wire:model="efEditField.auto_fill_prompt" rows="4"
                                  placeholder="Prompt für das Autofill…"
                                  class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 resize-none"></textarea>
                      </div>
                    @endif
                  </div>
                @endif

                {{-- Action buttons --}}
                <div class="flex items-center gap-2 mt-6 pt-4 border-t border-[var(--ui-border)]/40">
                  <button wire:click="efSaveEditDefinition"
                          class="px-4 py-2 text-sm font-medium bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/80 transition">
                    Speichern
                  </button>
                  <button wire:click="efCancelEditDefinition"
                          class="px-4 py-2 text-sm font-medium text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition">
                    Abbrechen
                  </button>
                </div>
              </div>

            {{-- Lookup Values Manager --}}
            @elseif($this->efSelectedLookupId && $this->efTab === 'lookups')
              @php $selectedLu = $this->efSelectedLookup(); @endphp
              <div class="flex-1 flex flex-col" :class="fullscreen ? 'p-6' : 'p-4'">
                @if($selectedLu)
                  <div class="flex items-center justify-between mb-4">
                    <div>
                      <h3 class="text-sm font-bold text-[var(--ui-text)]">{{ $selectedLu['label'] }}</h3>
                      @if($selectedLu['description'])<p class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $selectedLu['description'] }}</p>@endif
                    </div>
                    <button wire:click="efDeselectLookup" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition">Zurück</button>
                  </div>

                  {{-- Add value form --}}
                  <div class="flex gap-2 mb-4">
                    <input type="text" wire:model="efNewLookupValueLabel" placeholder="Label"
                           class="flex-1 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                    <input type="text" wire:model="efNewLookupValueText" placeholder="Wert (optional)"
                           class="w-32 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                    <button wire:click="efAddLookupValue"
                            class="px-4 py-2 text-sm font-medium bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/80 transition">
                      Hinzufügen
                    </button>
                  </div>
                  @error('efNewLookupValueText') <span class="text-xs text-red-500 mb-2 block">{{ $message }}</span> @enderror

                  {{-- Values list --}}
                  <div class="space-y-1">
                    @forelse($this->efLookupValues as $lv)
                      <div class="group flex items-center gap-2 px-3 py-2 rounded-lg border border-[var(--ui-border)]/40 hover:border-[var(--ui-border)] transition">
                        <div class="min-w-0 flex-1">
                          <span class="text-sm text-[var(--ui-text)]">{{ $lv['label'] }}</span>
                          @if($lv['value'] !== $lv['label'])
                            <span class="text-xs text-[var(--ui-muted)] ml-1">({{ $lv['value'] }})</span>
                          @endif
                        </div>
                        <button wire:click="efToggleLookupValue({{ $lv['id'] }})"
                                class="px-2 py-0.5 text-[10px] rounded transition {{ $lv['is_active'] ? 'bg-emerald-500/15 text-emerald-600' : 'bg-red-500/10 text-red-400' }}">
                          {{ $lv['is_active'] ? 'Aktiv' : 'Inaktiv' }}
                        </button>
                        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                          <button wire:click="efMoveLookupValueUp({{ $lv['id'] }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition" title="Nach oben">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd"/></svg>
                          </button>
                          <button wire:click="efMoveLookupValueDown({{ $lv['id'] }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition" title="Nach unten">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                          </button>
                          <button wire:click="efDeleteLookupValue({{ $lv['id'] }})" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition" title="Löschen">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                          </button>
                        </div>
                      </div>
                    @empty
                      <p class="text-sm text-[var(--ui-muted)] text-center py-6">Keine Werte vorhanden</p>
                    @endforelse
                  </div>
                @endif
              </div>

            {{-- Placeholder --}}
            @else
              <div class="flex-1 flex items-center justify-center">
                <div class="text-center py-12">
                  <div class="text-3xl opacity-20 mb-3">
                    @if($this->efTab === 'fields')
                      📋
                    @else
                      📖
                    @endif
                  </div>
                  <p class="text-sm font-medium text-[var(--ui-text)]">
                    @if($this->efTab === 'fields')
                      Feld auswählen zum Bearbeiten
                    @else
                      Lookup auswählen für Werte-Verwaltung
                    @endif
                  </p>
                  <p class="text-xs text-[var(--ui-muted)] mt-1 max-w-[200px] mx-auto">
                    @if($this->efTab === 'fields')
                      Wähle ein Feld aus der Sidebar oder erstelle ein neues.
                    @else
                      Klicke auf einen Lookup in der Sidebar um dessen Werte zu verwalten.
                    @endif
                  </p>
                </div>
              </div>
            @endif

          </div>

        @else
          <!-- No channel selected (only for non-agenda apps) -->
          <div x-show="$wire.activeApp !== 'agenda'" class="flex-1 flex items-center justify-center text-[var(--t-text-muted)] text-sm">
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
          <div x-show="$wire.activeApp === 'agenda'" class="flex-1 min-h-0 flex flex-col"
               x-data="{
                 newItemTitle: '',
                 newItemDate: '',
                 newItemTimeStart: '',
                 newItemTimeEnd: '',
                 newItemColor: '',
                 colors: ['', 'red', 'orange', 'amber', 'green', 'blue', 'purple', 'pink'],
               }">

            {{-- Kanban View — single agenda --}}
            @if($agendaView === 'board' && $activeAgendaId)
              <x-ui-kanban-container sortable="updateAgendaSlotOrder" sortable-group="updateAgendaItemSlotOrder">

                {{-- Backlog --}}
                <x-ui-kanban-column title="Backlog" sortable-id="backlog" :scrollable="true" :muted="true">
                  <x-slot name="headerActions">
                    <span class="text-xs text-[var(--ui-muted)] font-medium">{{ count($this->agendaBacklogItems) }}</span>
                  </x-slot>
                  @foreach($this->agendaBacklogItems as $item)
                    @include('platform::livewire.partials.agenda-kanban-card', ['item' => $item])
                  @endforeach
                </x-ui-kanban-column>

                {{-- Custom Slot Spalten (sortierbar) --}}
                @foreach($this->agendaSlots as $slot)
                  <x-ui-kanban-column :title="$slot['name']" :sortable-id="$slot['id']" :scrollable="true">
                    <x-slot name="headerActions">
                      @if(count($slot['items']) > 0)
                        <span class="text-xs text-[var(--ui-muted)] font-medium">{{ count($slot['items']) }}</span>
                      @endif
                      <button wire:click="deleteAgendaSlot({{ $slot['id'] }})" wire:confirm="Slot löschen? Items werden in den Backlog verschoben."
                              class="text-[var(--ui-muted)] hover:text-red-500 transition-colors" title="Slot löschen">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                      </button>
                    </x-slot>
                    @foreach($slot['items'] as $item)
                      @include('platform::livewire.partials.agenda-kanban-card', ['item' => $item])
                    @endforeach
                  </x-ui-kanban-column>
                @endforeach

                {{-- Erledigt --}}
                <x-ui-kanban-column title="Erledigt" sortable-id="done" :scrollable="true" :muted="true">
                  <x-slot name="headerActions">
                    <span class="text-xs text-[var(--ui-muted)] font-medium">{{ count($this->agendaDoneItems) }}</span>
                  </x-slot>
                  @foreach($this->agendaDoneItems as $item)
                    <x-ui-kanban-card :title="''" :sortable-id="$item['id']">
                      <div class="flex items-start gap-2">
                        <button wire:click="toggleAgendaItemDone({{ $item['id'] }})" class="mt-0.5 w-3.5 h-3.5 rounded border flex-shrink-0 flex items-center justify-center bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white">
                          <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        </button>
                        <div class="flex-1 min-w-0">
                          <div class="text-xs text-[var(--ui-muted)] line-through leading-tight">{{ $item['title'] }}</div>
                        </div>
                      </div>
                    </x-ui-kanban-card>
                  @endforeach
                </x-ui-kanban-column>

                {{-- Neue Spalte hinzufügen --}}
                <div class="flex-shrink-0 w-80 pt-2" x-data="{ showNewSlot: false, newSlotName: '' }">
                  <div x-show="!showNewSlot">
                    <button @click="showNewSlot = true; $nextTick(() => $refs.newSlotInput?.focus())" class="flex items-center gap-1.5 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition px-3 py-2">
                      <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                      Spalte hinzufügen
                    </button>
                  </div>
                  <div x-show="showNewSlot" x-cloak class="p-2 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/30 space-y-2">
                    <input x-ref="newSlotInput" type="text" x-model="newSlotName" placeholder="Spalten-Name…"
                           @keydown.enter="if(newSlotName.trim()) { $wire.createAgendaSlot({{ $activeAgendaId }}, newSlotName.trim()); newSlotName = ''; showNewSlot = false; }"
                           @keydown.escape="showNewSlot = false; newSlotName = ''"
                           class="w-full text-xs px-2.5 py-1.5 rounded border border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-text)] placeholder:text-[var(--ui-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]">
                    <div class="flex gap-1">
                      <button @click="if(newSlotName.trim()) { $wire.createAgendaSlot({{ $activeAgendaId }}, newSlotName.trim()); newSlotName = ''; showNewSlot = false; }"
                              class="flex-1 text-[10px] px-2 py-1 rounded bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/80 transition">Erstellen</button>
                      <button @click="showNewSlot = false; newSlotName = ''"
                              class="text-[10px] px-2 py-1 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition">Abbrechen</button>
                    </div>
                  </div>
                </div>

              </x-ui-kanban-container>

            {{-- "Mein Tag" View --}}
            @elseif($agendaView === 'day')
              <div class="flex-1 min-h-0 overflow-y-auto" :class="fullscreen ? 'p-6' : 'p-4'">

                {{-- Timed items --}}
                @php
                  $timedItems = collect($this->myDayItems)->filter(fn($i) => $i['time_start'])->sortBy('time_start');
                  $untimedItems = collect($this->myDayItems)->filter(fn($i) => !$i['time_start']);
                @endphp

                @if($timedItems->isNotEmpty())
                  <div class="mb-6">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                      Timeline
                    </h3>
                    <div class="space-y-1 border-l-2 border-[var(--ui-border)]/40 pl-3 ml-1">
                      @foreach($timedItems as $item)
                        <div class="group flex items-start gap-2 rounded-lg px-2 py-2 hover:bg-[var(--ui-muted-5)] transition relative
                          {{ $item['is_done'] ? 'opacity-50' : '' }}"
                        >
                          <div class="absolute -left-[calc(0.75rem+1.5px)] top-3 w-2 h-2 rounded-full {{ $item['is_done'] ? 'bg-[var(--ui-muted)]' : 'bg-[var(--ui-primary)]' }}"></div>
                          <button wire:click="toggleAgendaItemDone({{ $item['id'] }})"
                                  class="mt-0.5 w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition
                                    {{ $item['is_done'] ? 'bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}">
                            @if($item['is_done'])
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            @endif
                          </button>
                          <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                              <span class="text-[10px] font-mono text-[var(--ui-primary)] font-semibold">{{ $item['time_start'] }}@if($item['time_end'])–{{ $item['time_end'] }}@endif</span>
                              @if(!empty($item['agenda_name']))
                                <span class="text-[9px] px-1 py-0 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $item['agenda_icon'] ?? '📋' }} {{ $item['agenda_name'] }}</span>
                              @endif
                              @if(!empty($item['is_linked']))
                                <span class="text-[9px] px-1 py-0 rounded bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] inline-flex items-center gap-0.5">
                                  <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364l1.757 1.757"/></svg>
                                  {{ $item['agendable_type_label'] }}
                                </span>
                              @endif
                            </div>
                            <div class="text-sm font-medium text-[var(--ui-text)] {{ $item['is_done'] ? 'line-through' : '' }}">{{ $item['title'] }}</div>
                            @if($item['notes'])
                              <div class="text-xs text-[var(--ui-muted)] mt-0.5 line-clamp-1">{{ $item['notes'] }}</div>
                            @endif
                          </div>
                          <div class="flex items-center gap-0.5 {{ !empty($item['is_linked']) ? '' : 'opacity-0 group-hover:opacity-100' }} transition flex-shrink-0">
                            @if(!empty($item['is_linked']))
                              <button wire:click="detachAgendaItem({{ $item['id'] }})"
                                      class="p-1 rounded hover:bg-red-500/10 text-[var(--ui-muted)] hover:text-red-500 transition" title="Aus Agenda entfernen">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                              </button>
                            @else
                              <button wire:click="deleteAgendaItem({{ $item['id'] }})" wire:confirm="Item löschen?"
                                      class="p-1 rounded hover:bg-red-500/10 text-[var(--ui-muted)] hover:text-red-500 transition">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                              </button>
                            @endif
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif

                {{-- Untimed items --}}
                @if($untimedItems->isNotEmpty())
                  <div class="mb-6">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                      Aufgaben
                    </h3>
                    <div class="space-y-1">
                      @foreach($untimedItems as $item)
                        <div class="group flex items-start gap-2.5 rounded-lg px-2 py-2 hover:bg-[var(--ui-muted-5)] transition {{ $item['is_done'] ? 'opacity-50' : '' }}">
                          <button wire:click="toggleAgendaItemDone({{ $item['id'] }})"
                                  class="mt-0.5 w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition
                                    {{ $item['is_done'] ? 'bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}">
                            @if($item['is_done'])
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            @endif
                          </button>
                          <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-[var(--ui-text)] {{ $item['is_done'] ? 'line-through' : '' }}">{{ $item['title'] }}</div>
                            @if($item['notes'])
                              <div class="text-xs text-[var(--ui-muted)] mt-0.5 line-clamp-1">{{ $item['notes'] }}</div>
                            @endif
                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                              @if(!empty($item['agenda_name']))
                                <span class="text-[9px] px-1 py-0 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] inline-block">{{ $item['agenda_icon'] ?? '📋' }} {{ $item['agenda_name'] }}</span>
                              @endif
                              @if(!empty($item['is_linked']))
                                <span class="text-[9px] px-1 py-0 rounded bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] inline-flex items-center gap-0.5">
                                  <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364l1.757 1.757"/></svg>
                                  {{ $item['agendable_type_label'] }}
                                </span>
                              @endif
                            </div>
                          </div>
                          <div class="flex items-center gap-0.5 {{ !empty($item['is_linked']) ? '' : 'opacity-0 group-hover:opacity-100' }} transition flex-shrink-0">
                            @if(!empty($item['is_linked']))
                              <button wire:click="detachAgendaItem({{ $item['id'] }})"
                                      class="p-1 rounded hover:bg-red-500/10 text-[var(--ui-muted)] hover:text-red-500 transition" title="Aus Agenda entfernen">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                              </button>
                            @else
                              <button wire:click="deleteAgendaItem({{ $item['id'] }})" wire:confirm="Item löschen?"
                                      class="p-1 rounded hover:bg-red-500/10 text-[var(--ui-muted)] hover:text-red-500 transition">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                              </button>
                            @endif
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif

                {{-- Empty state --}}
                @if($timedItems->isEmpty() && $untimedItems->isEmpty())
                  <div class="text-center py-8">
                    <div class="text-3xl opacity-20 mb-3">☀️</div>
                    <p class="text-sm font-medium text-[var(--ui-text)]">Keine Items für diesen Tag</p>
                    <p class="text-xs text-[var(--ui-muted)] mt-1">Setze ein Datum auf deine Agenda-Items</p>
                  </div>
                @endif

                {{-- Backlog --}}
                @if(count($this->myDayBacklogItems) > 0)
                  <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/30">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                      Backlog <span class="font-normal text-[var(--ui-muted)]">(ohne Datum)</span>
                    </h3>
                    <div class="space-y-1">
                      @foreach($this->myDayBacklogItems as $item)
                        <div class="group flex items-start gap-2.5 rounded-lg px-2 py-2 hover:bg-[var(--ui-muted-5)] transition {{ $item['is_done'] ? 'opacity-50' : '' }}">
                          <button wire:click="toggleAgendaItemDone({{ $item['id'] }})"
                                  class="mt-0.5 w-4 h-4 rounded border flex-shrink-0 flex items-center justify-center transition
                                    {{ $item['is_done'] ? 'bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white' : 'border-[var(--ui-border)] hover:border-[var(--ui-primary)]' }}">
                            @if($item['is_done'])
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            @endif
                          </button>
                          <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-[var(--ui-text)] {{ $item['is_done'] ? 'line-through' : '' }}">{{ $item['title'] }}</div>
                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                              @if(!empty($item['agenda_name']))
                                <span class="text-[9px] px-1 py-0 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] inline-block">{{ $item['agenda_icon'] ?? '📋' }} {{ $item['agenda_name'] }}</span>
                              @endif
                              @if(!empty($item['is_linked']))
                                <span class="text-[9px] px-1 py-0 rounded bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] inline-flex items-center gap-0.5">
                                  <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364l1.757 1.757"/></svg>
                                  {{ $item['agendable_type_label'] }}
                                </span>
                              @endif
                            </div>
                          </div>
                          <div class="flex items-center gap-0.5 {{ !empty($item['is_linked']) ? '' : 'opacity-0 group-hover:opacity-100' }} transition flex-shrink-0">
                            <button wire:click="moveAgendaItemDate({{ $item['id'] }}, '{{ $agendaDayDate ?: now()->toDateString() }}')"
                                    class="p-1 rounded hover:bg-[var(--ui-primary)]/10 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition" title="Auf heute setzen">
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                            </button>
                            @if(!empty($item['is_linked']))
                              <button wire:click="detachAgendaItem({{ $item['id'] }})"
                                      class="p-1 rounded hover:bg-red-500/10 text-[var(--ui-muted)] hover:text-red-500 transition" title="Aus Agenda entfernen">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                              </button>
                            @else
                              <button wire:click="deleteAgendaItem({{ $item['id'] }})" wire:confirm="Item löschen?"
                                      class="p-1 rounded hover:bg-red-500/10 text-[var(--ui-muted)] hover:text-red-500 transition">
                                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                              </button>
                            @endif
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif
              </div>

            @else
              {{-- No agenda selected --}}
              <div class="flex-1 flex items-center justify-center">
                <div class="text-center py-12">
                  <div class="text-3xl opacity-20 mb-3">📋</div>
                  <p class="text-sm font-medium text-[var(--ui-text)]">Wähle eine Agenda</p>
                  <p class="text-xs text-[var(--ui-muted)] mt-1">Erstelle eine neue Agenda oder öffne "Mein Tag"</p>
                </div>
              </div>
            @endif
          </div>

      </div>
    </div>
  </div>

  <!-- New DM Modal -->
  <div
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
          <input x-model="channelName" type="text" placeholder="z.B. general" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:border-[var(--t-accent)]/40 outline-none transition" @keydown.enter="if(channelName.trim()) { $wire.createChannel(channelName.trim(), channelDesc.trim() || null, null, selectedIds); showNewChannel = false; }">
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
          @click="if(channelName.trim()) { $wire.createChannel(channelName.trim(), channelDesc.trim() || null, null, selectedIds); showNewChannel = false; }"
          :disabled="!channelName.trim()"
          :class="channelName.trim() ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80' : 'bg-[var(--t-text-muted)]/20 text-[var(--t-text-muted)] cursor-not-allowed'"
          class="text-xs px-3 py-1.5 rounded transition"
        >Erstellen</button>
      </div>
    </div>
  </div>

  <!-- Channel Members Modal -->
  <div
    x-data="{
      showMembers: false,
      members: [],
      available: [],
      loading: false,
      async load() {
        this.loading = true;
        const [m, t] = await Promise.all([
          this.$wire.getChannelMembers(),
          this.$wire.getTeamMembers()
        ]);
        this.members = m;
        const memberIds = m.map(x => x.id);
        this.available = t.filter(x => !memberIds.includes(x.id));
        this.loading = false;
      },
      async add(userId) {
        await this.$wire.addMember(userId);
        this.load();
      },
      async remove(userId) {
        await this.$wire.removeMember(userId);
        this.load();
      }
    }"
    x-on:terminal-show-members.window="showMembers = true; load()"
    x-show="showMembers"
    x-cloak
    class="terminal-light fixed inset-0 z-[70] flex items-center justify-center bg-black/30"
    @click.self="showMembers = false"
    @keydown.escape.window="showMembers = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--t-border)] w-80 max-h-[28rem] overflow-hidden flex flex-col" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--t-border)]/60 flex-shrink-0">
        <h3 class="text-sm font-medium text-[var(--t-text)]">Mitglieder</h3>
      </div>

      <div class="flex-1 min-h-0 overflow-y-auto">
        {{-- Current members --}}
        <div class="px-2 py-2">
          <template x-if="loading">
            <div class="px-2 py-4 text-center text-xs text-[var(--t-text-muted)]">Laden…</div>
          </template>
          <template x-if="!loading">
            <div class="space-y-px">
              <template x-for="member in members" :key="member.id">
                <div class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-[var(--t-text)] hover:bg-white/5 transition">
                  <div class="w-6 h-6 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="member.avatar">
                      <img :src="member.avatar" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!member.avatar">
                      <span x-text="member.initials"></span>
                    </template>
                  </div>
                  <span x-text="member.name" class="flex-1 text-xs truncate"></span>
                  <template x-if="member.role === 'owner'">
                    <span class="text-[9px] font-medium uppercase tracking-wider text-[var(--t-text-muted)] px-1.5 py-0.5 rounded bg-[var(--t-text-muted)]/10">Owner</span>
                  </template>
                  <template x-if="member.role !== 'owner'">
                    <button
                      @click="remove(member.id)"
                      class="text-[var(--t-text-muted)] hover:text-red-500 transition p-0.5 rounded hover:bg-red-500/10"
                      title="Entfernen"
                    >
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                    </button>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>

        {{-- Add members --}}
        <template x-if="!loading && available.length > 0">
          <div class="px-2 pb-2">
            <div class="px-2 py-1.5 text-[10px] font-medium text-[var(--t-text-muted)] uppercase tracking-wider">Hinzufügen</div>
            <div class="space-y-px">
              <template x-for="user in available" :key="user.id">
                <button
                  @click="add(user.id)"
                  class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-[var(--t-text)] hover:bg-white/5 transition"
                >
                  <div class="w-6 h-6 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="user.avatar">
                      <img :src="user.avatar" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!user.avatar">
                      <span x-text="user.initials"></span>
                    </template>
                  </div>
                  <span x-text="user.name" class="flex-1 text-xs truncate text-left"></span>
                  <svg class="w-3.5 h-3.5 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                </button>
              </template>
            </div>
          </div>
        </template>
      </div>

      <div class="px-4 py-2.5 border-t border-[var(--t-border)]/60 flex justify-end flex-shrink-0">
        <button @click="showMembers = false" class="text-xs px-3 py-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">Schließen</button>
      </div>
    </div>
  </div>

  <!-- Pins Panel Modal -->
  <div
    x-data="{ showPins: false, pins: [], loading: false }"
    x-on:terminal-show-pins.window="showPins = true; loading = true; $wire.getPinnedMessages().then(r => { pins = r; loading = false; })"
    x-show="showPins"
    x-cloak
    class="terminal-light fixed inset-0 z-[70] flex items-center justify-center bg-black/30"
    @click.self="showPins = false"
    @keydown.escape.window="showPins = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--t-border)] w-96 max-h-[28rem] overflow-hidden flex flex-col" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--t-border)]/60 flex-shrink-0 flex items-center justify-between">
        <h3 class="text-sm font-medium text-[var(--t-text)]">Gepinnte Nachrichten</h3>
        <button @click="showPins = false" class="text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
        </button>
      </div>
      <div class="flex-1 min-h-0 overflow-y-auto">
        <template x-if="loading">
          <div class="px-4 py-6 text-center text-xs text-[var(--t-text-muted)]">Laden…</div>
        </template>
        <template x-if="!loading && pins.length === 0">
          <div class="px-4 py-6 text-center text-xs text-[var(--t-text-muted)]">Keine gepinnten Nachrichten</div>
        </template>
        <template x-if="!loading && pins.length > 0">
          <div class="py-1">
            <template x-for="pin in pins" :key="pin.id">
              <div class="px-4 py-2.5 hover:bg-white/5 transition group/pin">
                <div class="flex items-start gap-2.5">
                  <div class="w-6 h-6 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden mt-0.5">
                    <template x-if="pin.user_avatar">
                      <img :src="pin.user_avatar" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!pin.user_avatar">
                      <span x-text="pin.user_initials"></span>
                    </template>
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2">
                      <span class="font-medium text-xs text-[var(--t-text)]" x-text="pin.user_name"></span>
                      <span class="text-[10px] text-[var(--t-text-muted)]" x-text="pin.date + ' ' + pin.time"></span>
                    </div>
                    <button
                      @click="
                        showPins = false;
                        const msgId = pin.message_id;
                        setTimeout(() => {
                          const el = document.getElementById('msg-' + msgId);
                          if(el) {
                            el.scrollIntoView({behavior:'smooth',block:'center'});
                            el.classList.add('!bg-amber-500/15');
                            setTimeout(() => el.classList.remove('!bg-amber-500/15'), 2000);
                          }
                        }, 100);
                      "
                      class="text-xs text-[var(--t-text)] hover:text-[var(--t-accent)] mt-0.5 text-left transition cursor-pointer"
                      x-text="pin.body_snippet"
                    ></button>
                    <div class="flex items-center gap-2 mt-1">
                      <span class="text-[9px] text-[var(--t-text-muted)]">Gepinnt von <span x-text="pin.pinned_by"></span> <span x-text="pin.pinned_at"></span></span>
                      <button
                        @click="$wire.unpinMessage(pin.message_id).then(() => { pins = pins.filter(p => p.id !== pin.id); })"
                        class="text-[9px] text-[var(--t-text-muted)] hover:text-red-500 transition opacity-0 group-hover/pin:opacity-100"
                      >Entfernen</button>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </template>
      </div>
    </div>
  </div>

  <!-- Forward Message Modal -->
  <div
    x-data="{ showForward: false, forwardMessageId: null, targets: [], loading: false }"
    x-on:terminal-show-forward.window="showForward = true; forwardMessageId = $event.detail.messageId; loading = true; $wire.getForwardTargets().then(r => { targets = r; loading = false; })"
    x-show="showForward"
    x-cloak
    class="terminal-light fixed inset-0 z-[70] flex items-center justify-center bg-black/30"
    @click.self="showForward = false"
    @keydown.escape.window="showForward = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--t-border)] w-80 max-h-96 overflow-hidden flex flex-col" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--t-border)]/60 flex-shrink-0">
        <h3 class="text-sm font-medium text-[var(--t-text)]">Nachricht weiterleiten</h3>
        <p class="text-[10px] text-[var(--t-text-muted)] mt-0.5">Wähle einen Channel oder Chat</p>
      </div>
      <div class="flex-1 min-h-0 overflow-y-auto">
        <template x-if="loading">
          <div class="px-4 py-6 text-center text-xs text-[var(--t-text-muted)]">Laden…</div>
        </template>
        <template x-if="!loading && targets.length === 0">
          <div class="px-4 py-6 text-center text-xs text-[var(--t-text-muted)]">Keine Ziele verfügbar</div>
        </template>
        <template x-if="!loading && targets.length > 0">
          <div>
            <template x-for="target in targets" :key="target.id">
              <button
                @click="$wire.forwardMessage(forwardMessageId, target.id); showForward = false"
                class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--t-text)] hover:bg-white/5 transition"
              >
                <template x-if="target.type === 'dm'">
                  <div class="w-6 h-6 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="target.avatar">
                      <img :src="target.avatar" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!target.avatar">
                      <span x-text="target.initials || '?'"></span>
                    </template>
                  </div>
                </template>
                <template x-if="target.type !== 'dm'">
                  <span class="text-[var(--t-text-muted)] text-sm" x-text="target.icon || '#'"></span>
                </template>
                <span x-text="target.name" class="flex-1 text-xs truncate text-left"></span>
                <span class="text-[9px] text-[var(--t-text-muted)] uppercase" x-text="target.type === 'dm' ? 'Chat' : target.type === 'channel' ? 'Channel' : 'Kontext'"></span>
              </button>
            </template>
          </div>
        </template>
      </div>
      <div class="px-4 py-2.5 border-t border-[var(--t-border)]/60 flex justify-end flex-shrink-0">
        <button @click="showForward = false" class="text-xs px-3 py-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">Abbrechen</button>
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
        typingUsers: {},
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
              if (Alpine?.store('page') && !Alpine.store('page').terminalOpen) {
                Alpine.store('page').terminalOpen = true;
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
              if (Alpine?.store('page') && !Alpine.store('page').terminalOpen) {
                Alpine.store('page').terminalOpen = true;
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

        get typingDisplay() {
          const names = Object.values(this.typingUsers).map(u => u.name);
          if (names.length === 0) return '';
          if (names.length === 1) return names[0] + ' tippt…';
          if (names.length === 2) return names[0] + ' und ' + names[1] + ' tippen…';
          return names[0] + ', ' + names[1] + ' und andere tippen…';
        },

        get open(){ return Alpine?.store('page')?.terminalOpen ?? false; },
        toggle(){ if(Alpine?.store('page')) Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen; },

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

        _typingChannel: null,
        _lastTypingSent: 0,

        setupTypingListener(channelId) {
          // Clean up previous listener
          if (this._typingChannel) {
            try { window.Echo?.private(this._typingChannel)?.stopListeningForWhisper('typing'); } catch(e) {}
          }
          if (!channelId || !window.Echo) return;

          this._typingChannel = `terminal.channel.${channelId}`;
          const userId = {{ auth()->id() }};

          window.Echo.private(this._typingChannel)
            .listenForWhisper('typing', (e) => {
              if (e.userId === userId) return;
              const key = e.userId;
              if (this.typingUsers[key]?._timeout) clearTimeout(this.typingUsers[key]._timeout);
              const timeout = setTimeout(() => {
                delete this.typingUsers[key];
                this.typingUsers = { ...this.typingUsers };
              }, 4000);
              this.typingUsers[key] = { name: e.userName, _timeout: timeout };
              this.typingUsers = { ...this.typingUsers };
            });
        },

        sendTypingWhisper(channelId) {
          if (!channelId || !window.Echo) return;
          const now = Date.now();
          if (now - this._lastTypingSent < 3000) return;
          this._lastTypingSent = now;
          try {
            window.Echo.private(`terminal.channel.${channelId}`)
              .whisper('typing', {
                userId: {{ auth()->id() }},
                userName: @js(auth()->user()->name),
              });
          } catch(e) {}
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
            if (Alpine?.store('page') && !Alpine.store('page').terminalOpen) {
              Alpine.store('page').terminalOpen = true;
            }
          }

          const scrollBottom = () => {
            this.$nextTick(() => {
              const c = this.$refs.body;
              if (c) c.scrollTop = c.scrollHeight;
            });
          };
          // Scroll on new messages / channel switch
          Livewire.hook('morph.updated', ({el}) => {
            const c = this.$refs.body;
            if (!c) return;
            if (el === c || c.contains(el)) scrollBottom();
          });

          // Setup typing listener for initial channel
          this.$watch('$wire.channelId', (id) => {
            this.typingUsers = {};
            this.setupTypingListener(id);
          });
          @if($channelId)
            this.setupTypingListener({{ $channelId }});
          @endif

        },
      };
    }
  </script>
</div>
