<div
  x-data="terminalShell()"
  x-init="init()"
  x-on:toggle-terminal.window="toggle()"
  class="w-full flex-shrink-0"
  wire:key="terminal-root"
>
  <!-- Always-visible strip (50px) — click to toggle -->
  <div
    class="h-[50px] w-full bg-[var(--ui-surface)]/95 backdrop-blur border-t border-[var(--ui-border)]/60 flex items-center px-3 cursor-pointer select-none hover:bg-[var(--ui-surface)]"
    @click="toggle()"
    wire:key="terminal-strip"
  >
    <div class="flex items-center gap-5 text-[11px] font-mono text-[var(--ui-muted)] truncate w-full">
      @php $ch = $this->channels; @endphp
      @foreach(collect($ch['dms'])->merge($ch['channels'])->sortByDesc('unread')->take(3) as $preview)
        <span class="flex items-center gap-1.5">
          @if($preview['unread'] > 0)
            <span class="w-2 h-2 rounded-full bg-[var(--ui-primary)] inline-block"></span>
          @else
            <span class="w-2 h-2 rounded-full bg-[var(--ui-muted)]/30 inline-block"></span>
          @endif
          {{ $preview['name'] }}
          @if($preview['last_message'])
            — {{ $preview['last_message'] }}
          @endif
          @if($preview['last_at'])
            <span class="opacity-60">{{ $preview['last_at'] }}</span>
          @endif
        </span>
      @endforeach
      @if(collect($ch['dms'])->merge($ch['channels'])->isEmpty())
        <span class="opacity-60">Keine Unterhaltungen</span>
      @endif
    </div>
    <div class="ml-auto flex-shrink-0 text-[var(--ui-muted)]">
      <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd" />
      </svg>
    </div>
  </div>

  <!-- Slide container -->
  <div
    class="w-full border-t border-[var(--ui-border)]/60 bg-[var(--ui-surface)]/95 backdrop-blur overflow-hidden transition-[height] duration-300 ease-out flex flex-col"
    x-bind:style="open ? 'height: 20rem; max-height: 50vh' : 'height: 0px'"
    style="height: 0px;"
    wire:key="terminal-slide"
  >
    <!-- Panel Content: Sidebar + Main -->
    <div class="flex-1 min-h-0 flex opacity-100 transition-opacity duration-200"
         :class="open ? 'opacity-100' : 'opacity-0'"
         wire:key="terminal-content">

      <!-- Sidebar (240px) -->
      <div class="w-[240px] flex-shrink-0 border-r border-[var(--ui-border)]/60 overflow-y-auto overscroll-contain py-2 flex flex-col" wire:key="terminal-sidebar">

        <!-- New Chat / Channel buttons -->
        <div class="px-2 mb-2 flex gap-1">
          <button
            x-data x-on:click="$dispatch('terminal-show-new-dm')"
            class="flex-1 text-[10px] px-2 py-1 rounded border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] hover:border-[var(--ui-border)] transition"
          >+ Chat</button>
          <button
            x-data x-on:click="$dispatch('terminal-show-new-channel')"
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
                <div class="w-5 h-5 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                  @if(! empty($dm['avatar']))
                    <img src="{{ $dm['avatar'] }}" alt="" class="w-full h-full object-cover">
                  @else
                    {{ $dm['initials'] ?? '?' }}
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
      </div>

      <!-- Main Chat Area — keyed per channel so editor + messages fully rebuild -->
      <div class="flex-1 min-w-0 flex flex-col" wire:key="terminal-main-{{ $channelId }}">

        @if($this->activeChannel)
          <!-- Chat Header -->
          <div class="h-10 px-3 flex items-center gap-2 text-xs border-b border-[var(--ui-border)]/60 flex-shrink-0">
            @if($this->activeChannel['type'] === 'dm')
              <div class="w-5 h-5 rounded-full bg-[var(--ui-primary-10)] text-[var(--ui-primary)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                @if(! empty($this->activeChannel['avatar']))
                  <img src="{{ $this->activeChannel['avatar'] }}" alt="" class="w-full h-full object-cover">
                @else
                  {{ $this->activeChannel['initials'] ?? '?' }}
                @endif
              </div>
              <span class="font-medium text-[var(--ui-body-color)]">{{ $this->activeChannel['name'] }}</span>
            @else
              <span class="text-[var(--ui-muted)] font-medium">{{ $this->activeChannel['icon'] ?? '#' }}</span>
              <span class="font-medium text-[var(--ui-body-color)]">{{ $this->activeChannel['name'] ?? 'Kontext' }}</span>
            @endif
            @if($this->activeChannel['member_count'] > 0)
              <span class="text-[var(--ui-muted)]">&middot;</span>
              <span class="text-[10px] text-[var(--ui-muted)]">{{ $this->activeChannel['member_count'] }} {{ $this->activeChannel['member_count'] === 1 ? 'Mitglied' : 'Mitglieder' }}</span>
            @endif

            {{-- Channel actions (delete / leave) --}}
            <div class="ml-auto flex items-center gap-1">
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
          <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-3 py-3 text-xs" x-ref="body" wire:key="terminal-messages-{{ $channelId }}">
            <div class="space-y-3">
              @php $lastDate = null; @endphp
              @forelse($this->messages as $msg)
                {{-- Date separator --}}
                @if($msg['date'] !== $lastDate)
                  @php $lastDate = $msg['date']; @endphp
                  <div class="flex items-center gap-2 py-1">
                    <div class="flex-1 h-px bg-[var(--ui-border)]/40"></div>
                    <span class="text-[9px] text-[var(--ui-muted)] font-medium">{{ $msg['date'] }}</span>
                    <div class="flex-1 h-px bg-[var(--ui-border)]/40"></div>
                  </div>
                @endif

                <div class="group flex gap-2 hover:bg-[var(--ui-surface-hover)]/50 -mx-1.5 px-1.5 py-0.5 rounded" wire:key="msg-{{ $msg['id'] }}">
                  <div class="w-6 h-6 rounded-full {{ $msg['is_mine'] ? 'bg-gray-100 text-gray-600' : 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' }} flex items-center justify-center text-[10px] font-semibold flex-shrink-0 overflow-hidden">
                    @if(! empty($msg['user_avatar']))
                      <img src="{{ $msg['user_avatar'] }}" alt="" class="w-full h-full object-cover">
                    @else
                      {{ $msg['user_initials'] }}
                    @endif
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2">
                      <span class="font-medium text-[var(--ui-body-color)]">{{ $msg['is_mine'] ? 'Du' : $msg['user_name'] }}</span>
                      <span class="text-[10px] text-[var(--ui-muted)]">{{ $msg['time'] }}</span>
                    </div>
                    <div class="mt-0.5 text-[var(--ui-secondary)] prose-terminal">{!! $msg['body_html'] !!}</div>

                    {{-- Reactions --}}
                    @if(! empty($msg['reactions']))
                      <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($msg['reactions'] as $reaction)
                          <button
                            wire:click="toggleReaction({{ $msg['id'] }}, '{{ $reaction['emoji'] }}')"
                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] border transition
                              {{ $reaction['reacted'] ? 'border-[var(--ui-primary)]/30 bg-[var(--ui-primary-5)] text-[var(--ui-primary)]' : 'border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:border-[var(--ui-border)]' }}"
                          >
                            <span>{{ $reaction['emoji'] }}</span>
                            <span>{{ $reaction['count'] }}</span>
                          </button>
                        @endforeach
                      </div>
                    @endif

                    {{-- Thread indicator --}}
                    @if($msg['reply_count'] > 0)
                      <button class="mt-1 text-[10px] text-[var(--ui-primary)] hover:underline">
                        {{ $msg['reply_count'] }} {{ $msg['reply_count'] === 1 ? 'Antwort' : 'Antworten' }}
                      </button>
                    @endif
                  </div>
                </div>
              @empty
                <div class="flex items-center justify-center h-full text-[var(--ui-muted)] text-xs">
                  Noch keine Nachrichten. Schreib die erste!
                </div>
              @endforelse
            </div>
          </div>

          <!-- Input (Tiptap Editor) — wire:ignore prevents morph from destroying ProseMirror DOM -->
          <div wire:key="terminal-editor-{{ $channelId }}"
               wire:ignore
               class="px-3 py-2 border-t border-[var(--ui-border)]/60 flex-shrink-0"
               x-data="tiptapEditor({
                 placeholder: '{{ $this->activeChannel['type'] === 'dm' ? 'Nachricht an ' . e($this->activeChannel['name']) . ' …' : 'Nachricht schreiben …' }}',
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
                   $wire.sendMessage(html, text, null, mentions);
                 },
               })">
            <div class="flex items-end gap-2">
              <div class="flex-1 min-w-0 rounded-md border border-[var(--ui-border)]/60 focus-within:border-[var(--ui-primary)]/40 transition-[border-color]">
                <div x-ref="editorEl"></div>
              </div>
              <div x-ref="emojiSlot" class="flex-shrink-0"></div>
              <button
                type="button"
                @click="submit()"
                :disabled="isEmpty"
                :class="!isEmpty ? 'bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary-hover)] cursor-pointer' : 'border border-[var(--ui-border)]/60 text-[var(--ui-muted)] opacity-60 cursor-not-allowed'"
                class="inline-flex items-center justify-center h-[34px] px-3 rounded-md text-xs transition flex-shrink-0"
              >
                Senden
              </button>
            </div>
          </div>
        @else
          <!-- No channel selected -->
          <div class="flex-1 flex items-center justify-center text-[var(--ui-muted)] text-xs">
            <div class="text-center">
              <div class="text-2xl mb-2 opacity-30">💬</div>
              <p>Starte einen Chat oder tritt einem Channel bei.</p>
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
    x-data="{ showNewChannel: false, channelName: '', channelDesc: '' }"
    x-on:terminal-show-new-channel.window="showNewChannel = true; channelName = ''; channelDesc = ''"
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
          <input x-model="channelName" type="text" placeholder="z.B. general" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--ui-border)]/60 bg-transparent text-[var(--ui-body-color)] placeholder:text-[var(--ui-muted)]/50 focus:border-[var(--ui-primary)]/40 outline-none transition" @keydown.enter="if(channelName.trim()) { $wire.createChannel(channelName.trim(), channelDesc.trim() || null); showNewChannel = false; }">
        </div>
        <div>
          <label class="block text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-1">Beschreibung (optional)</label>
          <input x-model="channelDesc" type="text" placeholder="Worum geht es?" class="w-full text-sm px-2.5 py-1.5 rounded border border-[var(--ui-border)]/60 bg-transparent text-[var(--ui-body-color)] placeholder:text-[var(--ui-muted)]/50 focus:border-[var(--ui-primary)]/40 outline-none transition">
        </div>
      </div>
      <div class="px-4 py-3 border-t border-[var(--ui-border)]/60 flex justify-end gap-2">
        <button @click="showNewChannel = false" class="text-xs px-3 py-1.5 rounded text-[var(--ui-muted)] hover:text-[var(--ui-body-color)] transition">Abbrechen</button>
        <button
          @click="if(channelName.trim()) { $wire.createChannel(channelName.trim(), channelDesc.trim() || null); showNewChannel = false; }"
          :disabled="!channelName.trim()"
          :class="channelName.trim() ? 'bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary-hover)]' : 'bg-[var(--ui-muted)]/20 text-[var(--ui-muted)] cursor-not-allowed'"
          class="text-xs px-3 py-1.5 rounded transition"
        >Erstellen</button>
      </div>
    </div>
  </div>

  <script>
    function terminalShell(){
      return {
        get open(){ return Alpine?.store('page')?.terminalOpen ?? false; },
        toggle(){ if(Alpine?.store('page')) Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen; },
        init(){
          this.$nextTick(() => {
            const c = this.$refs.body;
            if (c && this.open) c.scrollTop = c.scrollHeight;
          });
          // Auto-scroll on new messages
          Livewire.hook('morph.updated', ({el}) => {
            if (el === this.$refs.body || el.closest('[x-ref="body"]')) {
              this.$nextTick(() => {
                const c = this.$refs.body;
                if (c) c.scrollTop = c.scrollHeight;
              });
            }
          });
        },
      };
    }
  </script>
</div>
