{{--
    Agenda-Navigation (linke Terminal-Leiste). Liste + „Mein Tag" + Anlegen.
    Auswahl dispatcht terminal-agenda-state ans Content-Kind (core.terminal.agenda).
--}}
<div class="flex-1 min-h-0 flex flex-col overflow-y-auto py-2"
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
