@php
  $images = collect($attachments)->filter(fn ($a) => $a['is_image']);
  $files = collect($attachments)->filter(fn ($a) => ! $a['is_image']);
@endphp

{{-- Image attachments --}}
@if($images->isNotEmpty())
  <div class="flex flex-wrap gap-2 mt-1.5">
    @foreach($images as $img)
      <a href="{{ $img['url'] }}" target="_blank" rel="noopener" class="block rounded-lg overflow-hidden border border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/40 transition max-w-[300px]">
        <img src="{{ $img['url'] }}" alt="{{ $img['original_name'] }}" class="max-w-[300px] max-h-[200px] object-cover" loading="lazy">
      </a>
    @endforeach
  </div>
@endif

{{-- File attachments --}}
@if($files->isNotEmpty())
  <div class="flex flex-col gap-1 mt-1.5">
    @foreach($files as $file)
      <a href="{{ $file['download_url'] }}" target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-md border border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/40 hover:bg-[var(--ui-surface-hover)] transition text-[12px] text-[var(--ui-secondary)] max-w-fit">
        <svg class="w-4 h-4 flex-shrink-0 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
        <span class="truncate max-w-[180px]">{{ $file['original_name'] }}</span>
        <span class="text-[10px] text-[var(--ui-muted)] flex-shrink-0">{{ number_format($file['file_size'] / 1024, 0) }} KB</span>
        <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
      </a>
    @endforeach
  </div>
@endif
