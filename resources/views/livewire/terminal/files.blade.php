<div class="flex-1 min-h-0 flex flex-col">
  {{-- Scrollable file list --}}
  <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
    <div class="py-4 space-y-1 px-4">
      @php
        $filteredFiles = match($this->filesFilter) {
          'images' => array_filter($this->contextFiles, fn($f) => $f['is_image']),
          'documents' => array_filter($this->contextFiles, fn($f) => ! $f['is_image']),
          default => $this->contextFiles,
        };
      @endphp
      @forelse($filteredFiles as $file)
        @php $isSelected = in_array($file['id'], $this->filePickerSelected); @endphp
        <div
          class="group flex items-center gap-3 py-2 px-3 rounded-lg transition-colors
            {{ $this->filePickerActive ? 'cursor-pointer' : '' }}
            {{ $isSelected ? 'bg-[var(--t-accent)]/10 ring-2 ring-[var(--t-accent)]/40' : 'hover:bg-white/[0.06]' }}"
          wire:key="ctxfile-{{ $file['id'] }}"
          @if($this->filePickerActive) wire:click="toggleFilePickerSelection({{ $file['id'] }})" @endif
        >
          {{-- Selection indicator (picker mode) --}}
          @if($this->filePickerActive)
            <div class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition
              {{ $isSelected ? 'border-[var(--t-accent)] bg-[var(--t-accent)] text-white' : 'border-[var(--t-border)]' }}">
              @if($isSelected)
                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
              @endif
            </div>
          @endif
          {{-- Thumbnail / Icon --}}
          <div class="flex-shrink-0 w-10 h-10 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5 flex items-center justify-center">
            @if($file['is_image'] && ($file['thumbnail'] ?? $file['url']))
              <img src="{{ $file['thumbnail'] ?? $file['url'] }}" alt="" class="w-full h-full object-cover">
            @else
              <svg class="w-5 h-5 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
            @endif
          </div>
          {{-- File info --}}
          <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-[var(--t-text)] truncate" title="{{ $file['original_name'] }}">{{ $file['original_name'] }}</p>
            <div class="flex items-center gap-2 mt-0.5">
              <span class="text-[10px] text-[var(--t-text-muted)]">{{ \Illuminate\Support\Number::fileSize($file['file_size']) }}</span>
              <span class="text-[10px] text-[var(--t-text-muted)]">&middot;</span>
              <span class="text-[10px] text-[var(--t-text-muted)]">{{ $file['uploaded_by'] }}</span>
              <span class="text-[10px] text-[var(--t-text-muted)]">&middot;</span>
              <span class="text-[10px] text-[var(--t-text-muted)]">{{ $file['created_at'] }}</span>
            </div>
          </div>
          {{-- Actions (hidden in picker mode) --}}
          @if(! $this->filePickerActive)
            <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
              @if($file['is_image'] && $file['url'])
                <a href="{{ $file['url'] }}" target="_blank" class="p-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition" title="Ansehen">
                  @svg('heroicon-o-eye', 'w-3.5 h-3.5')
                </a>
              @endif
              <a href="{{ $file['download_url'] }}" target="_blank" class="p-1.5 rounded text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition" title="Download">
                @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
              </a>
              <button
                @click.stop="if(confirm('Datei wirklich löschen?')) $wire.deleteContextFile({{ $file['id'] }})"
                class="p-1.5 rounded text-[var(--t-text-muted)] hover:text-red-500 hover:bg-red-500/10 transition"
                title="Löschen"
              >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
              </button>
            </div>
          @endif
        </div>
      @empty
        <div class="py-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--t-text-muted)]/5 mb-3">
            @svg('heroicon-o-paper-clip', 'w-6 h-6 text-[var(--t-text-muted)]')
          </div>
          <p class="text-sm text-[var(--t-text-muted)]">Keine Dateien</p>
          <p class="text-xs text-[var(--t-text-muted)]/60 mt-1">Lade Dateien hoch per Drag & Drop oder Button</p>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Bottom bar: Picker confirmation OR Upload area --}}
  @if($this->filePickerActive)
    {{-- Picker selection bar --}}
    <div class="border-t border-[var(--t-border)]/60 flex-shrink-0 px-4 py-2.5">
      <div class="flex items-center gap-3">
        <div class="flex-1 text-xs">
          @if(count($this->filePickerSelected) > 0)
            <span class="font-medium text-[var(--t-accent)]">{{ count($this->filePickerSelected) }} {{ count($this->filePickerSelected) === 1 ? 'Datei' : 'Dateien' }} ausgewählt</span>
          @else
            <span class="text-[var(--t-text-muted)]">Dateien auswählen…</span>
          @endif
        </div>
        <button
          wire:click="cancelFilePicker"
          class="px-3 py-1.5 rounded text-xs text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition"
        >Abbrechen</button>
        <button
          wire:click="confirmFilePicker"
          @if(empty($this->filePickerSelected)) disabled @endif
          class="px-3 py-1.5 rounded text-xs font-medium transition
            {{ ! empty($this->filePickerSelected) ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/90' : 'bg-[var(--t-text-muted)]/10 text-[var(--t-text-muted)] cursor-not-allowed' }}"
        >Auswählen</button>
      </div>
    </div>
  @else
    {{-- Upload area --}}
    <div class="border-t border-[var(--t-border)]/60 flex-shrink-0"
         x-data="{
           selectedFiles: [],
           uploading: false,
           dragOver: false,
           handleFiles(files) {
             if (!files || !files.length) return;
             this.uploading = true;
             $wire.uploadMultiple('pendingFiles', Array.from(files), () => {
               $wire.uploadContextFiles().then(() => {
                 this.uploading = false;
                 this.selectedFiles = [];
               });
             }, () => { this.uploading = false; });
           },
         }"
         x-on:dragover.prevent="dragOver = true"
         x-on:dragleave.prevent="dragOver = false"
         x-on:drop.prevent="dragOver = false; handleFiles($event.dataTransfer.files)"
    >
      <div class="px-4 py-2.5">
        <div class="flex items-center gap-2"
             :class="dragOver ? 'ring-2 ring-[var(--t-accent)]/40 ring-offset-1 rounded-lg' : ''"
        >
          <button
            type="button"
            @click="$refs.contextFileInput.click()"
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition flex-shrink-0"
            title="Dateien auswählen"
          >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
          </button>
          <input x-ref="contextFileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

          <div class="flex-1 text-xs text-[var(--t-text-muted)]" x-show="!uploading">
            <span :class="dragOver ? 'text-[var(--t-accent)] font-medium' : ''">
              <span x-show="dragOver">Dateien hier ablegen</span>
              <span x-show="!dragOver">Dateien hochladen per Drag & Drop oder Klick</span>
            </span>
          </div>
          <div class="flex-1 flex items-center gap-2 text-xs text-[var(--t-text-muted)]" x-show="uploading" x-cloak>
            <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            <span>Wird hochgeladen…</span>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
