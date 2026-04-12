{{-- ═══ Sidebar: Comms ═══ --}}
<div x-show="$wire.activeApp === 'comms'" class="flex-1 min-h-0 flex flex-col">

  {{-- Action Buttons --}}
  <div class="px-3 pt-3 pb-2 space-y-1">
    {{-- Back to Timeline (shown when in settings or new) --}}
    @if($commsView === 'settings' || $commsView === 'new')
      <button wire:click="commsBackToTimeline"
              class="w-full flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/8 transition">
        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        Zurück zu Threads
      </button>
    @endif

    {{-- New Message Button --}}
    <button wire:click="openCommsNewMessage"
            class="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-[11px] font-semibold transition
              {{ $commsView === 'new'
                  ? 'bg-[var(--t-accent)] text-white shadow-sm shadow-[var(--t-accent)]/20'
                  : 'bg-white/8 text-[var(--t-text)] hover:bg-white/12 border border-[var(--t-border)]/20' }}">
      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
      Neue Nachricht
    </button>
  </div>

  {{-- Thread List --}}
  <div class="flex-1 min-h-0 overflow-y-auto px-2 pb-2 space-y-0.5">
    @forelse($allContextThreads as $idx => $thread)
      @php
        $isActive = $activeContextThreadIndex === $idx && $commsView === 'timeline';
        $isEmail = $thread['type'] === 'email';
      @endphp
      <button wire:click="switchToContextThread({{ $idx }}); commsBackToTimeline()"
              class="w-full text-left rounded-lg px-2.5 py-2 transition group
                {{ $isActive
                    ? 'bg-white/12 ring-1 ring-[var(--t-accent)]/30'
                    : 'hover:bg-white/5' }}">
        <div class="flex items-center gap-2 min-w-0">
          {{-- Type Icon --}}
          @if($isEmail)
            <div class="w-6 h-6 rounded-lg {{ $isActive ? 'bg-blue-500/20' : 'bg-blue-500/10' }} text-blue-400 flex items-center justify-center flex-shrink-0 transition">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </div>
          @else
            <div class="w-6 h-6 rounded-lg {{ $isActive ? 'bg-emerald-500/20' : 'bg-emerald-500/10' }} text-emerald-400 flex items-center justify-center flex-shrink-0 transition">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
            </div>
          @endif
          {{-- Thread Info --}}
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
    @empty
      <div class="px-3 py-8 text-center">
        <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-[var(--t-accent)]/10 flex items-center justify-center">
          <svg class="w-5 h-5 text-[var(--t-accent)]/30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
        </div>
        <p class="text-[10px] text-[var(--t-text-muted)]">Noch keine Threads</p>
        <p class="text-[9px] text-[var(--t-text-muted)]/50 mt-0.5">Neue Nachricht verfassen um zu starten.</p>
      </div>
    @endforelse
  </div>

  {{-- Channel overview + Settings link --}}
  <div class="border-t border-[var(--t-border)]/30 px-3 py-2">
    <div class="flex items-center justify-between mb-1">
      <span class="text-[9px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]/40">Kanäle</span>
      @if($commsView === 'settings')
        <span class="text-[9px] text-[var(--t-accent)] font-semibold">Einstellungen aktiv</span>
      @else
        <button wire:click="openCommsSettings"
                class="text-[9px] text-[var(--t-accent)] hover:text-[var(--t-accent)]/80 transition font-semibold">
          Verwalten
        </button>
      @endif
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
