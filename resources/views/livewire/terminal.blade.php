<div
  x-data="terminalShell()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle()"
  x-on:toggle-terminal-open.window="if(!open) toggle()"
  x-on:scroll-to-message.window="$nextTick(() => { const el = document.getElementById('msg-' + $event.detail.messageId); if(el) { el.scrollIntoView({behavior:'smooth',block:'center'}); el.classList.add('!bg-amber-100/30'); setTimeout(() => el.classList.remove('!bg-amber-100/30'), 2000); } })"
  x-on:terminal-typing="sendTypingWhisper($wire.channelId)"
  class="w-full flex-none relative"
  :class="resizing ? '' : 'transition-[height] duration-200 ease-out'"
  x-bind:style="open ? 'height:' + panelHeight + 'px;min-height:' + panelHeight + 'px;max-height:' + panelHeight + 'px' : 'height:38px;min-height:38px;max-height:38px'"
  wire:ignore.self
  wire:key="terminal-root"
>
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

  <!-- Single terminal container — status bar always peeks out -->
  <div
    class="w-full h-full border-t-2 border-[var(--ui-border)] bg-[var(--ui-surface)] overflow-hidden flex flex-col"
    wire:key="terminal-slide"
  >
    <!-- Resize handle — only visible when open -->
    <div
      x-show="open"
      @mousedown.prevent="startResize($event)"
      class="h-1 flex-shrink-0 cursor-ns-resize group/resize relative -mb-1 z-10"
    >
      <div class="absolute inset-x-0 top-0 h-px bg-transparent group-hover/resize:bg-[var(--ui-primary)]/40 transition"></div>
      <div class="absolute left-1/2 -translate-x-1/2 top-0 w-8 h-1 rounded-full bg-transparent group-hover/resize:bg-[var(--ui-primary)]/30 transition"></div>
    </div>

    <!-- Status bar — always visible (36px) -->
    <div
      @click="toggle()"
      class="h-9 flex-shrink-0 px-4 flex items-center gap-1.5 overflow-x-auto scrollbar-none select-none group/bar cursor-pointer"
      wire:key="terminal-statusbar"
    >
      {{-- Terminal icon + label + unread badge — click toggles open/close --}}
      <div class="flex items-center gap-1.5 mr-1 flex-shrink-0 cursor-pointer text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors" @click.stop="toggle()">
        @svg('heroicon-o-command-line', 'w-5 h-5')
        @if($totalUnread > 0)
          <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center animate-pulse">{{ $totalUnread > 99 ? '99+' : $totalUnread }}</span>
        @endif
      </div>

      @if($allChannels->isNotEmpty())
        <div class="w-px h-4 bg-[var(--ui-border)]/40 flex-shrink-0"></div>
      @endif

      {{-- Unread channel pills — click opens that channel + terminal --}}
      @foreach($allChannels as $preview)
        <button
          wire:click="openChannel({{ $preview['id'] }})"
          @click.stop="if(!open) toggle()"
          class="flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] flex-shrink-0 bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-semibold hover:bg-[var(--ui-primary-10)] transition cursor-pointer"
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
            <span class="text-[10px] text-[var(--ui-muted)] truncate max-w-[120px] hidden sm:inline font-normal">{{ $preview['last_message'] }}</span>
          @endif
        </button>
      @endforeach

      {{-- Page context indicator — shows current page entity, click opens context channel --}}
      @if($pageContext)
        <div class="w-px h-4 bg-[var(--ui-border)]/40 flex-shrink-0"></div>
        <button
          wire:click="openTerminal"
          @click.stop="if(!open) toggle()"
          class="flex items-center gap-1 px-2 py-1 rounded-full text-[11px] flex-shrink-0 border border-[var(--ui-border)]/40 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:border-[var(--ui-primary)]/30 hover:bg-[var(--ui-primary-5)] transition cursor-pointer"
          title="Diskussion zu {{ $pageContext['label'] }}: {{ $pageContext['title'] }}"
        >
          <span class="text-[10px]">{{ $pageContext['icon'] }}</span>
          <span class="truncate max-w-[120px]">{{ $pageContext['title'] }}</span>
        </button>
      @endif

      {{-- Chevron — click toggles open/close --}}
      <button @click.stop="toggle()" class="ml-auto flex-shrink-0 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition cursor-pointer p-1 -mr-1 rounded hover:bg-[var(--ui-surface-hover)]">
        <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd" />
        </svg>
      </button>
    </div>

    <!-- Panel Content: Sidebar + Main -->
    <div class="flex-1 min-h-0 flex"
         wire:key="terminal-content">

      <!-- Sidebar (resizable) -->
      <div class="flex-shrink-0 border-r border-[var(--ui-border)]/60 overflow-y-auto overscroll-contain py-2 flex flex-col relative"
           :style="'width: ' + sidebarWidth + 'px'"
           :class="resizingSidebar ? '' : 'transition-[width] duration-0'"
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
          <div class="absolute inset-y-0 right-0 w-px bg-transparent group-hover/sresize:bg-[var(--ui-primary)]/40 transition"></div>
          <div class="absolute top-1/2 -translate-y-1/2 right-0 h-8 w-1 rounded-full bg-transparent group-hover/sresize:bg-[var(--ui-primary)]/30 transition"></div>
        </div>

        <!-- Search field -->
        <div class="px-2 mb-2">
          <div class="relative">
            <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
            <input
              type="text"
              x-model="searchQuery"
              @input="doSearch()"
              @keydown.escape="clearSearch()"
              placeholder="Suchen…"
              class="w-full text-[11px] pl-7 pr-6 py-1.5 rounded border border-[var(--ui-border)]/60 bg-transparent text-[var(--ui-body-color)] placeholder:text-[var(--ui-muted)]/50 focus:border-[var(--ui-primary)]/40 outline-none transition"
            >
            <button x-show="searchQuery.length > 0" x-cloak @click="clearSearch()" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
          </div>
        </div>

        <!-- Search results overlay -->
        <template x-if="searchQuery.trim().length >= 2">
          <div class="flex-1 min-h-0 overflow-y-auto px-2">
            <template x-if="searching">
              <div class="px-1.5 py-4 text-center text-[10px] text-[var(--ui-muted)]">Suche…</div>
            </template>
            <template x-if="!searching && searchResults.length === 0">
              <div class="px-1.5 py-4 text-center text-[10px] text-[var(--ui-muted)]">Keine Ergebnisse</div>
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
                            el.classList.add('!bg-amber-100/30');
                            setTimeout(() => el.classList.remove('!bg-amber-100/30'), 2000);
                          }
                        }, 150);
                      });
                    "
                    class="w-full text-left px-1.5 py-2 rounded-md hover:bg-[var(--ui-surface-hover)] transition"
                  >
                    <div class="flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)]">
                      <span x-text="result.channel_name" class="font-medium truncate"></span>
                      <span>&middot;</span>
                      <span x-text="result.date"></span>
                      <span x-text="result.time"></span>
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                      <span class="text-[11px] font-medium text-[var(--ui-secondary)]" x-text="result.user_name"></span>
                    </div>
                    <div class="text-[11px] text-[var(--ui-muted)] truncate mt-0.5" x-text="result.snippet"></div>
                  </button>
                </template>
              </div>
            </template>
          </div>
        </template>

        <!-- Channel lists (hidden during search) -->
        <div x-show="searchQuery.trim().length < 2" class="flex-1 min-h-0 overflow-y-auto">

        <!-- New Chat / Channel buttons -->
        <div class="px-2 mb-2 flex gap-1">
          <button
            @click.stop="$dispatch('terminal-show-new-dm')"
            class="flex-1 text-[10px] px-2 py-1 rounded border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:border-[var(--ui-border)] transition"
          >+ Chat</button>
          <button
            @click.stop="$dispatch('terminal-show-new-channel')"
            class="flex-1 text-[10px] px-2 py-1 rounded border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:border-[var(--ui-border)] transition"
          >+ Channel</button>
        </div>

        <!-- Chats (DMs) Section -->
        <div class="px-2 mb-3" x-data="{ chatsOpen: true }">
          <button @click="chatsOpen = !chatsOpen" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">
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
                  {{ $channelId === $dm['id'] ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)]' }}"
              >
                <div class="relative flex-shrink-0">
                  <div class="w-5 h-5 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[9px] font-semibold overflow-hidden">
                    @if(! empty($dm['avatar']))
                      <img src="{{ $dm['avatar'] }}" alt="" class="w-full h-full object-cover">
                    @else
                      {{ $dm['initials'] ?? '?' }}
                    @endif
                  </div>
                  @if(! empty($dm['other_user_id']))
                    <div x-show="isOnline({{ $dm['other_user_id'] }})" x-cloak class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-1 ring-[var(--ui-surface)]"></div>
                  @endif
                </div>
                <span class="truncate flex-1 text-left">{{ $dm['name'] }}</span>
                @if($dm['unread'] > 0)
                  <span class="w-4 h-4 rounded-full bg-[var(--ui-primary)] text-white text-[9px] flex items-center justify-center flex-shrink-0">{{ $dm['unread'] > 9 ? '9+' : $dm['unread'] }}</span>
                @endif
              </button>
            @empty
              <div class="px-1.5 py-2 text-[10px] text-[var(--ui-muted)]">Noch keine Chats</div>
            @endforelse
          </div>
        </div>

        <!-- Context Channels — grouped by type -->
        @foreach($this->channels['context_groups'] as $groupKey => $group)
        <div class="px-2 mb-3" x-data="{ open: true }">
          <button @click="open = !open" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">
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
                  {{ $channelId === $ctx['id'] ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)]' }}"
              >
                <span class="truncate flex-1 text-left">{{ $ctx['name'] }}</span>
                @if($ctx['unread'] > 0)
                  <span class="w-4 h-4 rounded-full bg-[var(--ui-primary)] text-white text-[9px] flex items-center justify-center flex-shrink-0">{{ $ctx['unread'] > 9 ? '9+' : $ctx['unread'] }}</span>
                @endif
              </button>
            @endforeach
          </div>
        </div>
        @endforeach

        <!-- Channels Section -->
        <div class="px-2" x-data="{ channelsOpen: true }">
          <button @click="channelsOpen = !channelsOpen" class="w-full flex items-center justify-between px-1.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">
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
                  {{ $channelId === $ch['id'] ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)]' }}"
              >
                <span class="text-[var(--ui-muted)]">{{ $ch['icon'] ?? '#' }}</span>
                <span class="truncate flex-1 text-left">{{ $ch['name'] }}</span>
                @if($ch['unread'] > 0)
                  <span class="w-4 h-4 rounded-full bg-[var(--ui-primary)] text-white text-[9px] flex items-center justify-center flex-shrink-0">{{ $ch['unread'] > 9 ? '9+' : $ch['unread'] }}</span>
                @endif
              </button>
            @empty
              <div class="px-1.5 py-2 text-[10px] text-[var(--ui-muted)]">Noch keine Channels</div>
            @endforelse
          </div>
        </div>

        </div>{{-- end channel lists wrapper --}}
      </div>

      <!-- Main Chat Area — keyed per channel so editor + messages fully rebuild -->
      <div class="flex-1 min-w-0 flex flex-col" wire:key="terminal-main-{{ $channelId }}">

        @if($this->activeChannel)
          <!-- Chat Header -->
          <div class="h-11 px-4 flex items-center gap-2.5 text-xs border-b border-[var(--ui-border)]/60 flex-shrink-0">
            @if($this->activeChannel['type'] === 'dm')
              @php
                $dmOther = collect($this->activeChannel['members'])->first(fn($m) => $m['id'] !== auth()->id());
              @endphp
              <div class="relative flex-shrink-0">
                <div class="w-6 h-6 rounded-lg bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[10px] font-semibold overflow-hidden">
                  @if(! empty($this->activeChannel['avatar']))
                    <img src="{{ $this->activeChannel['avatar'] }}" alt="" class="w-full h-full object-cover">
                  @else
                    {{ $this->activeChannel['initials'] ?? '?' }}
                  @endif
                </div>
                @if($dmOther)
                  <div x-show="isOnline({{ $dmOther['id'] }})" x-cloak class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-1 ring-[var(--ui-surface)]"></div>
                @endif
              </div>
              <span class="font-bold text-[13px] text-[var(--ui-body-color)]">{{ $this->activeChannel['name'] }}</span>
              @if($dmOther)
                <span x-show="isOnline({{ $dmOther['id'] }})" x-cloak class="text-[10px] text-emerald-500 font-medium">online</span>
              @endif
            @elseif($this->activeChannel['type'] === 'context' && ! empty($this->activeChannel['context']))
              <span class="text-[14px]">{{ $this->activeChannel['context']['icon'] }}</span>
              <div class="flex flex-col leading-tight">
                @php $contextTitle = $this->activeChannel['name'] ?: $this->activeChannel['context']['title']; @endphp
                @if(! empty($this->activeChannel['context_url']))
                  <a href="{{ $this->activeChannel['context_url'] }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--ui-primary)] hover:underline transition" title="Zum Kontext springen">
                    {{ $contextTitle }}
                    <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
                  </a>
                @else
                  <span class="font-bold text-[13px] text-[var(--ui-body-color)]">{{ $contextTitle }}</span>
                @endif
                <span class="text-[10px] text-[var(--ui-muted)]">Kontext-Diskussion</span>
              </div>
            @else
              <span class="text-[var(--ui-muted)] font-bold text-[14px]">{{ $this->activeChannel['icon'] ?? '#' }}</span>
              <span class="font-bold text-[13px] text-[var(--ui-body-color)]">{{ $this->activeChannel['name'] ?? 'Kontext' }}</span>
            @endif
            @if(! empty($this->activeChannel['members']))
              <span class="text-[var(--ui-muted)]">&middot;</span>
              @php $isManageable = in_array($this->activeChannel['type'], ['channel', 'context']); @endphp
              <{{ $isManageable ? 'button' : 'div' }}
                @if($isManageable) @click.stop="$dispatch('terminal-show-members')" @endif
                class="flex items-center gap-1.5 {{ $isManageable ? 'cursor-pointer hover:opacity-80' : '' }} transition"
                @if($isManageable) title="Mitglieder verwalten" @endif
              >
                {{-- Avatar stack --}}
                <div class="flex -space-x-1.5">
                  @foreach(array_slice($this->activeChannel['members'], 0, 5) as $member)
                    <div class="w-5 h-5 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[8px] font-semibold flex-shrink-0 overflow-hidden ring-1 ring-[var(--ui-surface)]" title="{{ $member['name'] }}">
                      @if(! empty($member['avatar']))
                        <img src="{{ $member['avatar'] }}" alt="" class="w-full h-full object-cover">
                      @else
                        {{ $member['initials'] }}
                      @endif
                    </div>
                  @endforeach
                  @if($this->activeChannel['member_count'] > 5)
                    <div class="w-5 h-5 rounded-full bg-[var(--ui-muted)]/10 text-[var(--ui-muted)] flex items-center justify-center text-[8px] font-semibold flex-shrink-0 ring-1 ring-[var(--ui-surface)]">+{{ $this->activeChannel['member_count'] - 5 }}</div>
                  @endif
                </div>
                {{-- Names --}}
                <span class="text-[10px] text-[var(--ui-muted)] truncate max-w-[200px]">
                  @if($this->activeChannel['member_count'] <= 3)
                    {{ implode(', ', array_map(fn($m) => $m['name'], $this->activeChannel['members'])) }}
                  @else
                    {{ implode(', ', array_map(fn($m) => $m['name'], array_slice($this->activeChannel['members'], 0, 2))) }} +{{ $this->activeChannel['member_count'] - 2 }}
                  @endif
                </span>
              </{{ $isManageable ? 'button' : 'div' }}>
            @endif

            {{-- Channel actions (delete / leave / context actions) --}}
            <div class="ml-auto flex items-center gap-1">
              {{-- Context channel: files + tagging buttons --}}
              @if(! empty($this->activeChannel['context']))
                <button
                  wire:click="dispatchFilesContext"
                  class="text-[10px] text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition px-1.5 py-0.5 rounded hover:bg-[var(--ui-primary-5)]"
                  title="Dateien"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
                </button>
                <button
                  wire:click="dispatchTaggingContext"
                  class="text-[10px] text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition px-1.5 py-0.5 rounded hover:bg-[var(--ui-primary-5)]"
                  title="Tags & Farben"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.5 3A2.5 2.5 0 003 5.5v2.879a2.5 2.5 0 00.732 1.767l6.5 6.5a2.5 2.5 0 003.536 0l2.878-2.878a2.5 2.5 0 000-3.536l-6.5-6.5A2.5 2.5 0 008.38 3H5.5zM6 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                </button>
                <div class="w-px h-4 bg-[var(--ui-border)]/40"></div>
                <button
                  wire:click="deleteChannel"
                  wire:confirm="Kontext-Diskussion löschen? Kann jederzeit neu erstellt werden."
                  class="text-[10px] text-[var(--ui-muted)] hover:text-red-500 transition px-1.5 py-0.5 rounded hover:bg-red-50"
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
                    class="text-[10px] text-[var(--ui-muted)] hover:text-red-500 transition px-1.5 py-0.5 rounded hover:bg-red-50"
                    title="Channel loschen"
                  >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                  </button>
                @else
                  <button
                    wire:click="leaveChannel"
                    wire:confirm="Channel verlassen?"
                    class="text-[10px] text-[var(--ui-muted)] hover:text-amber-600 transition px-1.5 py-0.5 rounded hover:bg-amber-50"
                    title="Channel verlassen"
                  >
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M19 10a.75.75 0 00-.75-.75H8.704l1.048-.943a.75.75 0 10-1.004-1.114l-2.5 2.25a.75.75 0 000 1.114l2.5 2.25a.75.75 0 101.004-1.114l-1.048-.943h9.546A.75.75 0 0019 10z" clip-rule="evenodd"/></svg>
                  </button>
                @endif
              @elseif($this->activeChannel['type'] === 'dm')
                <button
                  wire:click="deleteChannel"
                  wire:confirm="Chat ausblenden? Die Nachrichten bleiben fur den anderen Teilnehmer erhalten."
                  class="text-[10px] text-[var(--ui-muted)] hover:text-red-500 transition px-1.5 py-0.5 rounded hover:bg-red-50"
                  title="Chat ausblenden"
                >
                  <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
              @endif
            </div>
          </div>

          <!-- Messages -->
          <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain text-[13px]" x-ref="body" wire:key="terminal-messages-{{ $channelId }}"
               x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
            <div class="px-4 py-2">
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
                    <div class="flex-1 h-px bg-[var(--ui-border)]/50"></div>
                    <span class="text-[11px] text-[var(--ui-muted)] font-medium px-2 select-none">{{ $msg['date'] }}</span>
                    <div class="flex-1 h-px bg-[var(--ui-border)]/50"></div>
                  </div>
                @endif

                @php $lastUserId = $msg['user_id']; $lastTime = $msg['time']; @endphp

                {{-- Message row --}}
                <div id="msg-{{ $msg['id'] }}" class="group relative {{ $isNewGroup ? 'mt-3 first:mt-0' : 'mt-px' }} -mx-4 px-4 py-0.5 hover:bg-[var(--ui-surface-hover)]/40 transition-colors" wire:key="msg-{{ $msg['id'] }}">

                  {{-- Hover action bar --}}
                  <div class="absolute -top-3 right-5 hidden group-hover:flex items-center gap-px bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-md shadow-sm z-10">
                    <button
                      wire:click="toggleReaction({{ $msg['id'] }}, '👍')"
                      class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] rounded-l-md transition text-xs"
                      title="Reagieren"
                    >👍</button>
                    <button
                      wire:click="toggleReaction({{ $msg['id'] }}, '😂')"
                      class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] transition text-xs"
                    >😂</button>
                    <button
                      wire:click="toggleReaction({{ $msg['id'] }}, '❤️')"
                      class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] transition text-xs"
                    >❤️</button>
                    <button
                      wire:click="toggleReaction({{ $msg['id'] }}, '✅')"
                      class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] transition text-xs"
                    >✅</button>
                    <div class="w-px h-4 bg-[var(--ui-border)]/40 self-center"></div>
                    <button
                      x-data="{ copied: false }"
                      @click.stop="
                        const url = location.origin + '/terminal?channel={{ $channelId }}&message={{ $msg['id'] }}';
                        navigator.clipboard.writeText(url).then(() => {
                          copied = true;
                          setTimeout(() => copied = false, 1500);
                        });
                      "
                      class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] transition {{ $msg['is_mine'] ? '' : 'rounded-r-md' }}"
                      :title="copied ? 'Kopiert!' : 'Link kopieren'"
                    >
                      <template x-if="!copied">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z"/><path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 005.656 5.656l3-3a4 4 0 00-.225-5.865z"/></svg>
                      </template>
                      <template x-if="copied">
                        <svg class="w-3.5 h-3.5 text-emerald-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                      </template>
                    </button>
                    @if($msg['is_mine'])
                      <div class="w-px h-4 bg-[var(--ui-border)]/40 self-center"></div>
                      <button
                        wire:click="startEditMessage({{ $msg['id'] }})"
                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] transition"
                        title="Bearbeiten"
                      >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z"/></svg>
                      </button>
                      <button
                        wire:click="deleteMessage({{ $msg['id'] }})"
                        wire:confirm="Nachricht unwiderruflich loschen?"
                        class="p-1 text-[var(--ui-muted)] hover:text-red-500 hover:bg-red-50 rounded-r-md transition"
                        title="Loschen"
                      >
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                      </button>
                    @endif
                  </div>

                  @if($isNewGroup)
                    {{-- Full message with avatar + name --}}
                    <div class="flex gap-2.5">
                      <div class="w-8 h-8 rounded-lg {{ $msg['is_mine'] ? 'bg-gray-100 text-gray-500' : 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' }} flex items-center justify-center text-[11px] font-semibold flex-shrink-0 overflow-hidden mt-0.5">
                        @if(! empty($msg['user_avatar']))
                          <img src="{{ $msg['user_avatar'] }}" alt="" class="w-full h-full object-cover">
                        @else
                          {{ $msg['user_initials'] }}
                        @endif
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2">
                          <span class="font-bold text-[13px] text-[var(--ui-body-color)]">{{ $msg['is_mine'] ? 'Du' : $msg['user_name'] }}</span>
                          <span class="text-[11px] text-[var(--ui-muted)] font-normal">{{ $msg['time'] }}</span>
                          @if(! empty($msg['edited_at']))
                            <span class="text-[10px] text-[var(--ui-muted)] font-normal" title="Bearbeitet am {{ $msg['edited_at'] }}">(bearbeitet)</span>
                          @endif
                        </div>
                        @if($editingMessageId === $msg['id'])
                          <div x-data="{ editText: @js($msg['body_plain']) }" class="mt-1">
                            <textarea
                              x-ref="editInput"
                              x-model="editText"
                              x-init="$nextTick(() => { $refs.editInput.focus(); $refs.editInput.selectionStart = $refs.editInput.value.length; })"
                              @keydown.enter.prevent="if(editText.trim()) $wire.editMessage({{ $msg['id'] }}, '<p>' + editText.replace(/\n/g, '</p><p>') + '</p>', editText)"
                              @keydown.escape="$wire.cancelEdit()"
                              class="w-full text-[13px] px-2.5 py-1.5 rounded border border-[var(--ui-primary)]/40 bg-transparent text-[var(--ui-body-color)] focus:border-[var(--ui-primary)] outline-none transition resize-none leading-relaxed"
                              rows="2"
                            ></textarea>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-[var(--ui-muted)]">
                              <span>Enter = Speichern</span>
                              <span>&middot;</span>
                              <span>Escape = Abbrechen</span>
                            </div>
                          </div>
                        @else
                          <div class="text-[var(--ui-body-color)] leading-relaxed prose-terminal">{!! $msg['body_html'] !!}</div>
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
                        <span class="text-[10px] text-[var(--ui-muted)] opacity-0 group-hover:opacity-100 transition-opacity select-none">{{ $msg['time'] }}</span>
                      </div>
                      <div class="flex-1 min-w-0">
                        @if($editingMessageId === $msg['id'])
                          <div x-data="{ editText: @js($msg['body_plain']) }">
                            <textarea
                              x-ref="editInput"
                              x-model="editText"
                              x-init="$nextTick(() => { $refs.editInput.focus(); $refs.editInput.selectionStart = $refs.editInput.value.length; })"
                              @keydown.enter.prevent="if(editText.trim()) $wire.editMessage({{ $msg['id'] }}, '<p>' + editText.replace(/\n/g, '</p><p>') + '</p>', editText)"
                              @keydown.escape="$wire.cancelEdit()"
                              class="w-full text-[13px] px-2.5 py-1.5 rounded border border-[var(--ui-primary)]/40 bg-transparent text-[var(--ui-body-color)] focus:border-[var(--ui-primary)] outline-none transition resize-none leading-relaxed"
                              rows="2"
                            ></textarea>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-[var(--ui-muted)]">
                              <span>Enter = Speichern</span>
                              <span>&middot;</span>
                              <span>Escape = Abbrechen</span>
                            </div>
                          </div>
                        @else
                          <div class="text-[var(--ui-body-color)] leading-relaxed prose-terminal">{!! $msg['body_html'] !!}</div>
                          @if(! empty($msg['edited_at']))
                            <span class="text-[10px] text-[var(--ui-muted)]" title="Bearbeitet am {{ $msg['edited_at'] }}">(bearbeitet)</span>
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
                            {{ $reaction['reacted'] ? 'border-[var(--ui-primary)]/30 bg-[var(--ui-primary-5)] text-[var(--ui-primary)]' : 'border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:border-[var(--ui-border)] hover:bg-[var(--ui-surface-hover)]' }}"
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
                      <button class="inline-flex items-center gap-1.5 text-[12px] text-[var(--ui-primary)] hover:underline font-medium">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5zm11 4V5a4 4 0 00-4-4H7a4 4 0 014 4h2a2 2 0 012 2v4a2 2 0 01-2 2h-1l-2 2h3l3 3v-3a2 2 0 002-2V9z" clip-rule="evenodd"/></svg>
                        {{ $msg['reply_count'] }} {{ $msg['reply_count'] === 1 ? 'Antwort' : 'Antworten' }}
                      </button>
                    </div>
                  @endif
                </div>
              @empty
                <div class="flex items-center justify-center h-full text-[var(--ui-muted)] text-sm">
                  <div class="text-center py-8">
                    <div class="text-3xl mb-3 opacity-20">💬</div>
                    <p>Noch keine Nachrichten.</p>
                    <p class="text-[var(--ui-muted)]/60 text-xs mt-1">Schreib die erste!</p>
                  </div>
                </div>
              @endforelse
            </div>
          </div>

          <!-- Typing indicator -->
          <div x-show="typingDisplay" x-cloak class="px-4 py-1 text-[11px] text-[var(--ui-muted)] italic flex items-center gap-1.5 border-t border-transparent">
            <span class="flex gap-0.5">
              <span class="w-1 h-1 rounded-full bg-[var(--ui-muted)] animate-bounce" style="animation-delay:0ms"></span>
              <span class="w-1 h-1 rounded-full bg-[var(--ui-muted)] animate-bounce" style="animation-delay:150ms"></span>
              <span class="w-1 h-1 rounded-full bg-[var(--ui-muted)] animate-bounce" style="animation-delay:300ms"></span>
            </span>
            <span x-text="typingDisplay"></span>
          </div>

          <!-- Input (Tiptap Editor) — wire:ignore prevents morph from destroying ProseMirror DOM -->
          <div wire:key="terminal-editor-{{ $channelId }}"
               wire:ignore
               class="border-t border-[var(--ui-border)]/60 flex-shrink-0"
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
                      <div class="w-12 h-12 rounded-md overflow-hidden border border-[var(--ui-border)]/60 bg-[var(--ui-surface-hover)]">
                        <img :src="file.url" alt="" class="w-full h-full object-cover">
                      </div>
                    </template>
                    <template x-if="!file.is_image">
                      <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-surface-hover)] text-[11px] text-[var(--ui-secondary)] max-w-[140px]">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
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
                  <div class="w-12 h-12 rounded-md border border-[var(--ui-border)]/60 bg-[var(--ui-surface-hover)] flex items-center justify-center">
                    <svg class="w-4 h-4 animate-spin text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                  </div>
                </template>
              </div>
            </div>

            <div class="px-4 py-2.5">
              <div class="flex items-end gap-2">
                {{-- Paperclip upload button --}}
                <button
                  type="button"
                  @click="$refs.fileInput.click()"
                  class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:bg-[var(--ui-surface-hover)] transition flex-shrink-0"
                  title="Datei anhängen"
                >
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
                </button>
                <input x-ref="fileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

                <div class="flex-1 min-w-0 rounded-lg border transition-all"
                     :class="dragOver ? 'border-[var(--ui-primary)] shadow-[0_0_0_1px_var(--ui-primary-10)] bg-[var(--ui-primary-5)]' : 'border-[var(--ui-border)]/80 focus-within:border-[var(--ui-primary)]/50 focus-within:shadow-[0_0_0_1px_var(--ui-primary-10)]'">
                  <div x-ref="editorEl"></div>
                </div>
                <div x-ref="emojiSlot" class="flex-shrink-0"></div>
                <button
                  type="button"
                  @click="submit()"
                  :disabled="!canSend"
                  :class="canSend ? 'bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary-hover)] cursor-pointer shadow-sm' : 'border border-[var(--ui-border)]/60 text-[var(--ui-muted)] opacity-40 cursor-not-allowed'"
                  class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0"
                >
                  <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                </button>
              </div>
            </div>
          </div>
        @else
          <!-- No channel selected -->
          <div class="flex-1 flex items-center justify-center text-[var(--ui-muted)] text-sm">
            <div class="text-center">
              <div class="text-3xl mb-3 opacity-20">💬</div>
              <p class="font-medium">Willkommen im Terminal</p>
              <p class="text-xs text-[var(--ui-muted)]/60 mt-1">Starte einen Chat oder tritt einem Channel bei.</p>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>

  <!-- New DM Modal -->
  <div
    x-data="{ showNewDm: false, members: [] }"
    x-on:terminal-show-new-dm.window="showNewDm = true; $wire.getTeamMembers().then(r => members = r)"
    x-show="showNewDm"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
    @click.self="showNewDm = false"
    @keydown.escape.window="showNewDm = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--ui-border)]/60 w-80 max-h-96 overflow-hidden" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--ui-border)]/60">
        <h3 class="text-sm font-medium text-[var(--ui-body-color)]">Neuer Chat</h3>
      </div>
      <div class="overflow-y-auto max-h-72">
        <template x-for="member in members" :key="member.id">
          <button
            @click="$wire.openDm(member.id); showNewDm = false"
            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)] transition"
          >
            <div class="w-7 h-7 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[10px] font-semibold flex-shrink-0 overflow-hidden">
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
          <div class="px-4 py-6 text-center text-xs text-[var(--ui-muted)]">Keine Team-Mitglieder gefunden</div>
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
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
    @click.self="showNewChannel = false"
    @keydown.escape.window="showNewChannel = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--ui-border)]/60 w-80 overflow-hidden" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--ui-border)]/60">
        <h3 class="text-sm font-medium text-[var(--ui-body-color)]">Neuer Channel</h3>
      </div>
      <div class="px-4 py-3 space-y-3">
        <div>
          <label class="block text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-1">Name</label>
          <input x-model="channelName" type="text" placeholder="z.B. general" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--ui-border)]/60 bg-transparent text-[var(--ui-body-color)] placeholder:text-[var(--ui-muted)]/50 focus:border-[var(--ui-primary)]/40 outline-none transition" @keydown.enter="if(channelName.trim()) { $wire.createChannel(channelName.trim(), channelDesc.trim() || null, null, selectedIds); showNewChannel = false; }">
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-1">Beschreibung (optional)</label>
          <input x-model="channelDesc" type="text" placeholder="Worum geht es?" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--ui-border)]/60 bg-transparent text-[var(--ui-body-color)] placeholder:text-[var(--ui-muted)]/50 focus:border-[var(--ui-primary)]/40 outline-none transition">
        </div>
        <div x-show="members.length > 0">
          <label class="block text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-1">Mitglieder einladen</label>
          <div class="max-h-36 overflow-y-auto rounded border border-[var(--ui-border)]/60">
            <template x-for="member in members" :key="member.id">
              <label class="flex items-center gap-2.5 px-2.5 py-1.5 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)] transition cursor-pointer">
                <input type="checkbox" :value="member.id" x-model.number="selectedIds" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]/30 w-3.5 h-3.5">
                <div class="w-5 h-5 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
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
          <div class="text-[10px] text-[var(--ui-muted)] mt-1" x-show="selectedIds.length > 0" x-text="selectedIds.length + ' ausgewählt'"></div>
        </div>
      </div>
      <div class="px-4 py-3 border-t border-[var(--ui-border)]/60 flex justify-end gap-2">
        <button @click="showNewChannel = false" class="text-xs px-3 py-1.5 rounded text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">Abbrechen</button>
        <button
          @click="if(channelName.trim()) { $wire.createChannel(channelName.trim(), channelDesc.trim() || null, null, selectedIds); showNewChannel = false; }"
          :disabled="!channelName.trim()"
          :class="channelName.trim() ? 'bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary-hover)]' : 'bg-[var(--ui-muted)]/20 text-[var(--ui-muted)] cursor-not-allowed'"
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
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/30"
    @click.self="showMembers = false"
    @keydown.escape.window="showMembers = false"
  >
    <div class="bg-[var(--ui-surface)] rounded-lg shadow-xl border border-[var(--ui-border)]/60 w-80 max-h-[28rem] overflow-hidden flex flex-col" @click.stop>
      <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 flex-shrink-0">
        <h3 class="text-sm font-medium text-[var(--ui-body-color)]">Mitglieder</h3>
      </div>

      <div class="flex-1 min-h-0 overflow-y-auto">
        {{-- Current members --}}
        <div class="px-2 py-2">
          <template x-if="loading">
            <div class="px-2 py-4 text-center text-xs text-[var(--ui-muted)]">Laden…</div>
          </template>
          <template x-if="!loading">
            <div class="space-y-px">
              <template x-for="member in members" :key="member.id">
                <div class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)] transition">
                  <div class="w-6 h-6 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="member.avatar">
                      <img :src="member.avatar" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!member.avatar">
                      <span x-text="member.initials"></span>
                    </template>
                  </div>
                  <span x-text="member.name" class="flex-1 text-xs truncate"></span>
                  <template x-if="member.role === 'owner'">
                    <span class="text-[9px] font-medium uppercase tracking-wider text-[var(--ui-muted)] px-1.5 py-0.5 rounded bg-[var(--ui-muted)]/10">Owner</span>
                  </template>
                  <template x-if="member.role !== 'owner'">
                    <button
                      @click="remove(member.id)"
                      class="text-[var(--ui-muted)] hover:text-red-500 transition p-0.5 rounded hover:bg-red-50"
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
            <div class="px-2 py-1.5 text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider">Hinzufügen</div>
            <div class="space-y-px">
              <template x-for="user in available" :key="user.id">
                <button
                  @click="add(user.id)"
                  class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-surface-hover)] transition"
                >
                  <div class="w-6 h-6 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="user.avatar">
                      <img :src="user.avatar" alt="" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!user.avatar">
                      <span x-text="user.initials"></span>
                    </template>
                  </div>
                  <span x-text="user.name" class="flex-1 text-xs truncate text-left"></span>
                  <svg class="w-3.5 h-3.5 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                </button>
              </template>
            </div>
          </div>
        </template>
      </div>

      <div class="px-4 py-2.5 border-t border-[var(--ui-border)]/60 flex justify-end flex-shrink-0">
        <button @click="showMembers = false" class="text-xs px-3 py-1.5 rounded text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">Schließen</button>
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
        resizing: false,
        resizingSidebar: false,
        onlineUsers: [],
        typingUsers: {},
        _startY: 0,
        _startH: 0,

        get typingDisplay() {
          const names = Object.values(this.typingUsers).map(u => u.name);
          if (names.length === 0) return '';
          if (names.length === 1) return names[0] + ' tippt…';
          if (names.length === 2) return names[0] + ' und ' + names[1] + ' tippen…';
          return names[0] + ', ' + names[1] + ' und andere tippen…';
        },

        isOnline(userId) {
          return this.onlineUsers.includes(Number(userId));
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

          // Presence heartbeat: initial + every 30s
          const refreshPresence = () => {
            $wire.heartbeat().then(ids => { this.onlineUsers = ids; });
          };
          refreshPresence();
          setInterval(refreshPresence, 30000);
        },
      };
    }
  </script>
</div>
