{{-- Agenda Kanban Card (used in backlog + custom slot columns) --}}
@php
  $borderColor = match($item['color'] ?? '') {
    'red' => 'border-l-2 border-l-red-500',
    'orange' => 'border-l-2 border-l-orange-500',
    'amber' => 'border-l-2 border-l-amber-500',
    'green' => 'border-l-2 border-l-green-500',
    'blue' => 'border-l-2 border-l-blue-500',
    'purple' => 'border-l-2 border-l-purple-500',
    'pink' => 'border-l-2 border-l-pink-500',
    default => '',
  };
@endphp
<x-ui-kanban-card :title="''" :sortable-id="$item['id']" @class([$borderColor])>
  <div class="flex items-start gap-2">
    {{-- Checkbox --}}
    <button wire:click="toggleAgendaItemDone({{ $item['id'] }})" class="mt-0.5 w-3.5 h-3.5 rounded border flex-shrink-0 flex items-center justify-center transition border-[var(--ui-border)] hover:border-[var(--ui-primary)]"></button>

    <div class="flex-1 min-w-0">
      {{-- Title row with icon --}}
      <div class="flex items-center gap-1.5">
        @if($item['linked_icon'])<span class="text-xs flex-shrink-0">{{ $item['linked_icon'] }}</span>@endif
        <div class="text-xs font-medium text-[var(--ui-text)] leading-tight truncate">{{ $item['title'] }}</div>
      </div>

      {{-- Description (from notes or linked entity) --}}
      @php $desc = $item['notes'] ?: ($item['linked_description'] ?? null); @endphp
      @if($desc)
        <div class="text-[10px] text-[var(--ui-muted)] mt-0.5 line-clamp-2">{{ $desc }}</div>
      @endif

      {{-- Badges row --}}
      <div class="flex items-center gap-1 mt-1.5 flex-wrap">
        {{-- Type badge --}}
        @if(!empty($item['is_linked']))
          <span class="text-[9px] px-1.5 py-0.5 rounded bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] inline-flex items-center gap-0.5">
            <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364l1.757 1.757"/></svg>
            {{ $item['agendable_type_label'] }}
          </span>
        @endif

        {{-- Status badge --}}
        @if($item['linked_status'])
          @php
            $statusColors = match($item['linked_status_color'] ?? '') {
              'green' => 'bg-green-500/15 text-green-600',
              'red' => 'bg-red-500/15 text-red-600',
              'yellow' => 'bg-amber-500/15 text-amber-600',
              'orange' => 'bg-orange-500/15 text-orange-600',
              'blue' => 'bg-blue-500/15 text-blue-600',
              default => 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]',
            };
          @endphp
          <span class="text-[9px] px-1.5 py-0.5 rounded {{ $statusColors }}">{{ $item['linked_status'] }}</span>
        @endif

        {{-- Date badge --}}
        @if($item['date_label'])
          <span class="text-[9px] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $item['date_label'] }}</span>
        @endif

        {{-- Time badge --}}
        @if($item['time_start'])
          <span class="text-[9px] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)] font-mono">{{ $item['time_start'] }}@if($item['time_end'])–{{ $item['time_end'] }}@endif</span>
        @endif

        {{-- Meta: due_date --}}
        @if(!empty($item['linked_meta']['due_date']))
          <span class="text-[9px] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">Fällig: {{ \Carbon\Carbon::parse($item['linked_meta']['due_date'])->translatedFormat('d. M') }}</span>
        @endif

        {{-- Meta: story_points --}}
        @if(!empty($item['linked_meta']['story_points']))
          <span class="text-[9px] px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $item['linked_meta']['story_points'] }} SP</span>
        @endif
      </div>

      {{-- Link to entity --}}
      @if($item['linked_url'])
        <a href="{{ $item['linked_url'] }}" class="inline-flex items-center gap-0.5 text-[9px] text-[var(--ui-primary)] hover:underline mt-1 transition">
          <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
          Öffnen
        </a>
      @endif
    </div>
  </div>
</x-ui-kanban-card>
