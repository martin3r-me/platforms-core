{{-- ═══ Sidebar: Comms ═══ --}}
<div x-show="$wire.activeApp === 'comms'" class="flex-1 min-h-0 flex flex-col">

  {{-- New Message Button --}}
  <div class="px-3 pt-3 pb-2">
    <button wire:click="openCommsNewMessage"
            class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] font-semibold transition
              {{ $commsShowNewMessage
                  ? 'bg-[var(--t-accent)] text-white shadow-sm shadow-[var(--t-accent)]/20'
                  : 'bg-white/8 text-[var(--t-text)] hover:bg-white/12 border border-[var(--t-border)]/20' }}">
      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
      Neue Nachricht
    </button>
  </div>

  {{-- Thread Lists --}}
  <div class="flex-1 min-h-0 overflow-y-auto px-2 pb-2 relative">
    {{-- Loading overlay --}}
    <div wire:loading.delay.shortest wire:target="switchToContextThread, switchToOtherThread"
         class="absolute inset-0 bg-black/10 backdrop-blur-[1px] z-10 flex items-start justify-center pt-8">
      <svg class="w-4 h-4 animate-spin text-[var(--t-accent)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
    </div>

    {{-- ── Context Threads ── --}}
    @if(!empty($allContextThreads))
      <div class="mb-1">
        <div class="space-y-0.5">
          @foreach($allContextThreads as $idx => $thread)
            @php
              $isActive = $activeContextThreadIndex === $idx;
              $isEmail = $thread['type'] === 'email';
            @endphp
            <button wire:click="switchToContextThread({{ $idx }})"
                    class="w-full text-left rounded-lg px-2.5 py-2 transition group
                      {{ $isActive
                          ? 'bg-white/12 ring-1 ring-[var(--t-accent)]/30'
                          : 'hover:bg-white/5' }}">
              <div class="flex items-center gap-2 min-w-0">
                @if($isEmail)
                  <div class="w-6 h-6 rounded-lg {{ $isActive ? 'bg-blue-500/20' : 'bg-blue-500/10' }} text-blue-400 flex items-center justify-center flex-shrink-0 transition">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                  </div>
                @else
                  <div class="w-6 h-6 rounded-lg {{ $isActive ? 'bg-emerald-500/20' : 'bg-emerald-500/10' }} text-emerald-400 flex items-center justify-center flex-shrink-0 transition">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                  </div>
                @endif
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1">
                    <span class="text-[11px] font-semibold text-[var(--t-text)] truncate">{{ $thread['label'] }}</span>
                    <div class="flex items-center gap-1 flex-shrink-0">
                      @if(!$isEmail && ($thread['window_open'] ?? false))
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" title="24h-Fenster offen"></span>
                      @endif
                      <span class="text-[9px] text-[var(--t-text-muted)]/50 whitespace-nowrap">{{ $thread['last_at'] }}</span>
                    </div>
                  </div>
                  <div class="text-[10px] text-[var(--t-text-muted)] truncate">{{ $thread['counterpart'] }}</div>
                </div>
              </div>
              @if($thread['channel_label'])
                <div class="mt-0.5 text-[9px] text-[var(--t-text-muted)]/40 truncate pl-8">via {{ $thread['channel_label'] }}</div>
              @endif
            </button>
          @endforeach
        </div>
      </div>
    @else
      <div class="px-3 py-6 text-center">
        <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-[var(--t-accent)]/10 flex items-center justify-center">
          <svg class="w-5 h-5 text-[var(--t-accent)]/30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
        </div>
        <p class="text-[10px] text-[var(--t-text-muted)]">Noch keine Threads</p>
        <p class="text-[9px] text-[var(--t-text-muted)]/50 mt-0.5">Neue Nachricht verfassen um zu starten.</p>
      </div>
    @endif

    {{-- ── Other Recent Threads ── --}}
    @if(!empty($otherRecentThreads))
      <div class="mt-1 border-t border-[var(--t-border)]/20 pt-1">
        <button wire:click="$toggle('showOtherThreads')"
                class="w-full px-2 py-1.5 flex items-center justify-between group">
          <div class="flex items-center gap-1.5">
            <span class="text-[9px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]/50">Weitere</span>
            <span class="text-[9px] text-[var(--t-text-muted)]/30">{{ count($otherRecentThreads) }}</span>
          </div>
          <svg class="w-3 h-3 text-[var(--t-text-muted)]/40 transition-transform duration-150 {{ $showOtherThreads ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
        @if($showOtherThreads)
          <div class="space-y-0.5 mt-0.5">
            @foreach($otherRecentThreads as $idx => $thread)
              @php
                $isEmail = $thread['type'] === 'email';
                $isOtherActive = $activeOtherThreadIndex === $idx;
              @endphp
              <button wire:click="switchToOtherThread({{ $idx }})"
                      class="w-full text-left rounded-lg px-2.5 py-1.5 transition group
                        {{ $isOtherActive ? 'bg-white/10 ring-1 ring-[var(--t-accent)]/20' : 'hover:bg-white/5' }}">
                <div class="flex items-center gap-2 min-w-0">
                  @if($isEmail)
                    <div class="w-5 h-5 rounded-md {{ $isOtherActive ? 'bg-blue-500/15' : 'bg-blue-500/8' }} text-blue-400/60 flex items-center justify-center flex-shrink-0 transition">
                      <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                    </div>
                  @else
                    <div class="w-5 h-5 rounded-md {{ $isOtherActive ? 'bg-emerald-500/15' : 'bg-emerald-500/8' }} text-emerald-400/60 flex items-center justify-center flex-shrink-0 transition">
                      <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                    </div>
                  @endif
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-1">
                      <span class="text-[10px] font-medium {{ $isOtherActive ? 'text-[var(--t-text)]' : 'text-[var(--t-text)]/70' }} truncate group-hover:text-[var(--t-text)] transition">{{ $thread['label'] }}</span>
                      <span class="text-[8px] text-[var(--t-text-muted)]/40 whitespace-nowrap flex-shrink-0">{{ $thread['last_at'] }}</span>
                    </div>
                    <div class="text-[9px] text-[var(--t-text-muted)]/60 truncate">{{ $thread['counterpart'] }}</div>
                  </div>
                </div>
              </button>
            @endforeach
          </div>
        @endif
      </div>
    @endif
  </div>

  {{-- Channel footer + Settings --}}
  <div class="border-t border-[var(--t-border)]/30 px-3 py-2">
    <div class="flex items-center justify-between mb-1">
      <span class="text-[9px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]/40">Kanäle</span>
      <button wire:click="openCommsSettings"
              class="inline-flex items-center gap-1 text-[9px] font-semibold transition
                {{ $commsShowSettings ? 'text-[var(--t-accent)]' : 'text-[var(--t-text-muted)]/50 hover:text-[var(--t-accent)]' }}">
        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
      </button>
    </div>
    @if(!empty($emailChannels) || !empty($whatsappChannels))
      <div class="space-y-0.5">
        @foreach(array_slice($emailChannels ?? [], 0, 2) as $ec)
          <div class="text-[9px] text-[var(--t-text-muted)]/60 truncate flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-400/40 flex-shrink-0"></span>
            {{ $ec['label'] ?? $ec['sender_identifier'] ?? '' }}
          </div>
        @endforeach
        @foreach(array_slice($whatsappChannels ?? [], 0, 2) as $wc)
          <div class="text-[9px] text-[var(--t-text-muted)]/60 truncate flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400/40 flex-shrink-0"></span>
            {{ $wc['name'] ?? $wc['label'] ?? '' }}
          </div>
        @endforeach
      </div>
    @else
      <div class="text-[9px] text-[var(--t-text-muted)]/40">Keine Kanäle konfiguriert.</div>
    @endif
  </div>
</div>
