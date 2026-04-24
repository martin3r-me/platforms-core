<div class="flex-1 min-h-0 flex flex-col"
     x-data="{
       typingUsers: {},
       _typingChannel: null,
       _lastTypingSent: 0,

       get typingDisplay() {
         const names = Object.values(this.typingUsers).map(u => u.name);
         if (names.length === 0) return '';
         if (names.length === 1) return names[0] + ' tippt…';
         if (names.length === 2) return names[0] + ' und ' + names[1] + ' tippen…';
         return names[0] + ', ' + names[1] + ' und andere tippen…';
       },

       setupTypingListener(channelId) {
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
     }"
     x-init="
       $watch('$wire.channelId', (id) => {
         typingUsers = {};
         setupTypingListener(id);
       });
       @if($channelId)
         setupTypingListener({{ $channelId }});
       @endif
     "
     x-on:scroll-to-message.window="$nextTick(() => { const el = document.getElementById('msg-' + $event.detail.messageId); if(el) { el.scrollIntoView({behavior:'smooth',block:'center'}); el.classList.add('!bg-amber-500/15'); setTimeout(() => el.classList.remove('!bg-amber-500/15'), 2000); } })"
>

  <!-- Messages -->
  <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain text-[13px]" x-ref="chatBody" wire:key="terminal-messages-{{ $channelId }}"
       x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
    <div class="py-2 px-4">
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
                   x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
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
                  <button wire:click="startEditMessage({{ $msg['id'] }})" @click.stop="showMore = false"
                          class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-[var(--t-text)] hover:bg-white/5 transition">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z"/></svg>
                    <span>Bearbeiten</span>
                  </button>
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

  <!-- Input (Tiptap Editor) -->
  @if($this->activeChannel)
  <div wire:key="terminal-editor-{{ $channelId }}"
       wire:ignore
       class="border-t border-[var(--t-border)]/60 flex-shrink-0"
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
       x-on:keydown="sendTypingWhisper($wire.channelId)"
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
        <button type="button" @click="$refs.fileInput.click()"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition flex-shrink-0"
          title="Datei anhängen">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
        </button>
        <input x-ref="fileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

        <div class="flex-1 min-w-0 rounded-lg border transition-all"
             :class="dragOver ? 'border-[var(--t-accent)] shadow-[0_0_0_1px_var(--t-accent)] bg-[var(--t-accent)]/10' : 'border-[var(--t-border)]/80 focus-within:border-[var(--t-accent)]/50 focus-within:shadow-[0_0_0_1px_var(--t-accent)]'">
          <div x-ref="editorEl"></div>
        </div>
        <div x-ref="emojiSlot" class="flex-shrink-0"></div>
        <button type="button" @click="submit()" :disabled="!canSend"
          :class="canSend ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 cursor-pointer shadow-sm' : 'border border-[var(--t-border)]/60 text-[var(--t-text-muted)] opacity-40 cursor-not-allowed'"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
        </button>
      </div>
    </div>
  </div>
  @endif

  <!-- Forward Message Modal -->
  <div
    wire:ignore
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
                    <template x-if="target.avatar"><img :src="target.avatar" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!target.avatar"><span x-text="target.initials || '?'"></span></template>
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

  <!-- Pins Panel Modal -->
  <div
    wire:ignore
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
                    <template x-if="pin.user_avatar"><img :src="pin.user_avatar" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!pin.user_avatar"><span x-text="pin.user_initials"></span></template>
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

  <!-- Channel Members Modal -->
  <div
    wire:ignore
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
        <div class="px-2 py-2">
          <template x-if="loading">
            <div class="px-2 py-4 text-center text-xs text-[var(--t-text-muted)]">Laden…</div>
          </template>
          <template x-if="!loading">
            <div class="space-y-px">
              <template x-for="member in members" :key="member.id">
                <div class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-[var(--t-text)] hover:bg-white/5 transition">
                  <div class="w-6 h-6 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="member.avatar"><img :src="member.avatar" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!member.avatar"><span x-text="member.initials"></span></template>
                  </div>
                  <span x-text="member.name" class="flex-1 text-xs truncate"></span>
                  <template x-if="member.role === 'owner'">
                    <span class="text-[9px] font-medium uppercase tracking-wider text-[var(--t-text-muted)] px-1.5 py-0.5 rounded bg-[var(--t-text-muted)]/10">Owner</span>
                  </template>
                  <template x-if="member.role !== 'owner'">
                    <button @click="remove(member.id)" class="text-[var(--t-text-muted)] hover:text-red-500 transition p-0.5 rounded hover:bg-red-500/10" title="Entfernen">
                      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                    </button>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>
        <template x-if="!loading && available.length > 0">
          <div class="px-2 pb-2">
            <div class="px-2 py-1.5 text-[10px] font-medium text-[var(--t-text-muted)] uppercase tracking-wider">Hinzufügen</div>
            <div class="space-y-px">
              <template x-for="user in available" :key="user.id">
                <button @click="add(user.id)" class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-[var(--t-text)] hover:bg-white/5 transition">
                  <div class="w-6 h-6 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[9px] font-semibold flex-shrink-0 overflow-hidden">
                    <template x-if="user.avatar"><img :src="user.avatar" alt="" class="w-full h-full object-cover"></template>
                    <template x-if="!user.avatar"><span x-text="user.initials"></span></template>
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
</div>
