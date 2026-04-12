{{-- Context header bar for non-chat terminal apps --}}
@if($pageContext)
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
      <span class="text-[10px] text-[var(--t-text-muted)]">{{ $appLabel ?? $pageContext['label'] }}</span>
    </div>
  </div>
@endif
