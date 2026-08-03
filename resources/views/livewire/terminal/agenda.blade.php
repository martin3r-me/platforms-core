{{--
    Agenda-App — aus der Terminal-Shell extrahiert (App-Komponente).
    Selbst-enthaltend: Header + zweispaltig Agenda-Liste (war Shell-Sidebar) |
    Content (Kanban/„Mein Tag"). Alle ef… äh agenda*-Methoden liegen im Kind.
    Alpine-Var `fullscreen` erbt aus dem Shell-Root-Scope.
--}}
<div class="flex-1 min-h-0 flex flex-col"
     x-data="{
       newItemTitle: '',
       newItemDate: '',
       newItemTimeStart: '',
       newItemTimeEnd: '',
       newItemColor: '',
       colors: ['', 'red', 'orange', 'amber', 'green', 'blue', 'purple', 'pink'],
     }">

  {{-- ── Header (war Shell-Main-Header) ── --}}
  <div class="relative px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
       :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'"
       x-data="{
         showAgendaMembers: false,
         agendaMembers: [],
         loadingMembers: false,
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
        <button wire:click="navigateDay('today')" class="px-2 py-0.5 rounded text-[10px] font-medium hover:bg-[var(--t-glass-hover)] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition" title="Heute">Heute</button>
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

  {{-- ── Zweispaltig: Agenda-Liste | Content ── --}}
  <div class="flex-1 min-h-0 flex">

    {{-- Agenda-Liste (war Shell-Sidebar) --}}
    <aside class="w-60 flex-shrink-0 border-r border-[var(--t-border)]/60 flex flex-col overflow-y-auto py-2"
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
    </aside>

    {{-- Content (war Kind-Body) --}}
    <main class="flex-1 min-w-0 flex flex-col">

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
    <div class="flex-1 min-h-0 overflow-y-auto p-4">

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

    </main>

  </div>{{-- /zweispaltig --}}
</div>
