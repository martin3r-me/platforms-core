{{-- Global context header — always visible at top of main content area --}}
@if($pageContext)
  @php
    $appLabels = [
      'activity' => 'Aktivitäten',
      'tags' => 'Tags & Farben',
      'time' => 'Zeiterfassung',
      'okr' => 'OKR KeyResults',
    ];
    $appLabel = $appLabels[$activeApp] ?? null;
  @endphp
  <div class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
       :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
    <span class="text-[14px]">{{ $pageContext['icon'] }}</span>
    <div class="flex flex-col leading-tight min-w-0 flex-1">
      @if($contextUrl)
        <a href="{{ $contextUrl }}" class="inline-flex items-center gap-1 font-bold text-[13px] text-[var(--t-accent)] hover:underline transition truncate" title="Zum Kontext springen">
          {{ $pageContext['title'] }}
          <svg class="w-3 h-3 flex-shrink-0 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.25 5.5a.75.75 0 00-.75.75v8.5c0 .414.336.75.75.75h8.5a.75.75 0 00.75-.75v-4a.75.75 0 011.5 0v4A2.25 2.25 0 0112.75 17h-8.5A2.25 2.25 0 012 14.75v-8.5A2.25 2.25 0 014.25 4h5a.75.75 0 010 1.5h-5zm7.25-.75a.75.75 0 01.75-.75h3.5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0V6.31l-5.47 5.47a.75.75 0 11-1.06-1.06l5.47-5.47H12.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
        </a>
      @else
        <span class="font-bold text-[13px] text-[var(--t-text)] truncate">{{ $pageContext['title'] }}</span>
      @endif
      <span class="text-[10px] text-[var(--t-text-muted)]">{{ $pageContext['label'] }}@if($appLabel) · {{ $appLabel }}@endif</span>
    </div>
    {{-- Context-Thread button — always visible regardless of active app --}}
    @if($activeApp === 'chat'
        && ($this->activeChannel['type'] ?? '') === 'context'
        && ($this->activeChannel['context_type'] ?? '') === $contextType
        && ($this->activeChannel['context_id'] ?? null) == $contextId)
      <span class="p-1 text-[var(--t-accent)] flex-shrink-0" title="Kontext-Diskussion aktiv">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
      </span>
    @else
      <button wire:click="openContextChannel"
              class="p-1 rounded hover:bg-[var(--t-accent)]/20 text-[var(--t-text-muted)] hover:text-[var(--t-accent)] transition flex-shrink-0"
              title="Kontext-Diskussion öffnen">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.48.432.447.74 1.04.586 1.641a4.483 4.483 0 0 1-.923 1.785A5.969 5.969 0 0 0 6 21c1.282 0 2.47-.402 3.445-1.087.81.22 1.668.337 2.555.337Z"/></svg>
      </button>
    @endif

    {{-- App-specific actions --}}
    @if($activeApp === 'agenda' && $activeAgendaId && $this->canAttachContextToAgenda())
      @if($this->isContextAttachedToAgenda())
        <span class="p-1 text-[var(--t-accent)] flex-shrink-0" title="Bereits in Agenda">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
        </span>
      @else
        <button wire:click="attachContextToAgenda"
                class="p-1 rounded hover:bg-[var(--t-accent)]/20 text-[var(--t-text-muted)] hover:text-[var(--t-accent)] transition flex-shrink-0"
                title="In Agenda aufnehmen">
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        </button>
      @endif
    @endif
    @if($activeApp === 'comms')
      <button wire:click="openCommsNewMessage"
              class="p-1 rounded hover:bg-[var(--t-accent)]/20 text-[var(--t-text-muted)] hover:text-[var(--t-accent)] transition flex-shrink-0"
              title="Neue Nachricht">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
      </button>
      <button wire:click="openCommsSettings"
              class="p-1 rounded hover:bg-[var(--t-accent)]/20 text-[var(--t-text-muted)] hover:text-[var(--t-accent)] transition flex-shrink-0"
              title="Comms-Einstellungen">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
      </button>
    @endif
  </div>
@elseif(in_array($activeApp, ['activity', 'tags', 'time', 'okr']))
  @php
    $noContextLabels = [
      'activity' => ['icon' => 'heroicon-o-clock', 'label' => 'Aktivitäten'],
      'tags' => ['icon' => null, 'label' => 'Tags & Farben'],
      'time' => ['icon' => null, 'label' => 'Zeiterfassung'],
      'okr' => ['icon' => null, 'label' => 'OKR KeyResults'],
    ];
    $ncInfo = $noContextLabels[$activeApp];
  @endphp
  <div class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
       :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
    @if($activeApp === 'activity')
      @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--t-text-muted)]')
    @elseif($activeApp === 'tags')
      <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5-3.9 19.5m-2.1-19.5-3.9 19.5"/></svg>
    @elseif($activeApp === 'time')
      <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
    @elseif($activeApp === 'okr')
      <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>
    @endif
    <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $ncInfo['label'] }}</span>
  </div>
@endif
