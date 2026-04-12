{{-- ═══ Sidebar: Comms ═══ --}}
<div x-show="$wire.activeApp === 'comms'" class="flex-1 min-h-0 flex flex-col">

  {{-- New Message Button --}}
  <div class="px-3 pt-3 pb-2">
    <button wire:click="$set('commsView', 'new')"
            class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-md text-[11px] font-semibold transition
              {{ $commsView === 'new' ? 'bg-[var(--t-accent)] text-white' : 'bg-white/10 text-[var(--t-text)] hover:bg-white/15' }}">
      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      Neue Nachricht
    </button>
  </div>

  {{-- Thread List --}}
  <div class="flex-1 min-h-0 overflow-y-auto px-2 pb-2 space-y-0.5">
    @forelse($allContextThreads as $idx => $thread)
      <button wire:click="switchToContextThread({{ $idx }}); $set('commsView', 'timeline')"
              class="w-full text-left rounded-md px-2.5 py-2 transition
                {{ $activeContextThreadIndex === $idx && $commsView === 'timeline'
                    ? 'bg-white/15 ring-1 ring-[var(--t-accent)]/30'
                    : 'hover:bg-white/5' }}">
        <div class="flex items-center gap-2 min-w-0">
          @if($thread['type'] === 'email')
            <div class="w-5 h-5 rounded-md bg-blue-500/20 text-blue-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </div>
          @else
            <div class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
            </div>
          @endif
          <div class="flex-1 min-w-0">
            <div class="text-[11px] font-semibold text-[var(--t-text)] truncate">{{ $thread['label'] }}</div>
            <div class="text-[10px] text-[var(--t-text-muted)] truncate">{{ $thread['counterpart'] }}</div>
          </div>
          <span class="text-[9px] text-[var(--t-text-muted)] whitespace-nowrap flex-shrink-0">{{ $thread['last_at'] }}</span>
        </div>
        @if($thread['channel_label'])
          <div class="mt-0.5 text-[9px] text-[var(--t-text-muted)]/60 truncate pl-7">{{ $thread['channel_label'] }}</div>
        @endif
      </button>
    @empty
      <div class="px-3 py-6 text-center">
        <div class="text-[var(--t-text-muted)]/40 text-lg mb-1">
          <svg class="w-6 h-6 mx-auto opacity-40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
        </div>
        <p class="text-[10px] text-[var(--t-text-muted)]">Noch keine Threads</p>
      </div>
    @endforelse
  </div>

  {{-- Channel overview + Settings link --}}
  <div class="border-t border-[var(--t-border)]/40 px-3 py-2">
    <div class="flex items-center justify-between">
      <span class="text-[9px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]/60">Kanäle</span>
      <button wire:click="openCommsSettings"
              class="text-[10px] text-[var(--t-accent)] hover:underline">
        Verwalten
      </button>
    </div>
    @if(!empty($emailChannels))
      <div class="mt-1 space-y-0.5">
        @foreach(array_slice($emailChannels, 0, 3) as $ec)
          <div class="text-[10px] text-[var(--t-text-muted)] truncate flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-400/50 flex-shrink-0"></span>
            {{ $ec['label'] ?? $ec['sender_identifier'] ?? '' }}
          </div>
        @endforeach
      </div>
    @endif
    @if(!empty($whatsappChannels))
      <div class="mt-1 space-y-0.5">
        @foreach(array_slice($whatsappChannels, 0, 3) as $wc)
          <div class="text-[10px] text-[var(--t-text-muted)] truncate flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400/50 flex-shrink-0"></span>
            {{ $wc['name'] ?? $wc['label'] ?? '' }}
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
