<div class="flex-1 min-h-0 flex flex-col">
  {{-- Scrollable activity list --}}
  <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
    <div class="py-4 space-y-1.5 px-4">
      @php
        $filteredActivities = $this->activityFilter === 'all'
          ? $this->contextActivities
          : array_filter($this->contextActivities, fn($a) => ($a['activity_type'] ?? 'system') === $this->activityFilter);
      @endphp
      @forelse($filteredActivities as $act)
        @if(($act['activity_type'] ?? 'system') === 'manual')
          {{-- Manual note --}}
          <div class="group flex items-start gap-2.5 py-2 px-3 rounded-lg hover:bg-white/[0.06] transition-colors" wire:key="act-{{ $act['id'] }}">
            <div class="flex-shrink-0 mt-0.5">
              <div class="w-7 h-7 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)] flex items-center justify-center text-[10px] font-semibold overflow-hidden">
                @if(! empty($act['user_avatar']))
                  <img src="{{ $act['user_avatar'] }}" alt="" class="w-full h-full object-cover">
                @else
                  {{ $act['user_initials'] ?? '?' }}
                @endif
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5 mb-0.5">
                <span class="text-xs font-semibold text-[var(--t-text)]">{{ $act['user'] }}</span>
                <span class="text-[10px] text-[var(--t-text-muted)]">{{ $act['time'] }}</span>
              </div>
              <p class="text-sm text-[var(--t-text)] leading-snug whitespace-pre-line">{{ $act['title'] }}</p>
              @if(! empty($act['attachments']))
                <div class="flex flex-wrap gap-1.5 mt-1.5">
                  @foreach($act['attachments'] as $att)
                    @if($att['is_image'])
                      <a href="{{ $att['url'] }}" target="_blank" class="block w-20 h-20 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5 hover:opacity-80 transition">
                        <img src="{{ $att['url'] }}" alt="{{ $att['original_name'] }}" class="w-full h-full object-cover">
                      </a>
                    @else
                      <a href="{{ $att['download_url'] }}" target="_blank" class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--t-border)]/60 bg-white/5 text-[11px] text-[var(--t-text)] hover:bg-white/[0.06] transition max-w-[180px]">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
                        <span class="truncate">{{ $att['original_name'] }}</span>
                      </a>
                    @endif
                  @endforeach
                </div>
              @endif
            </div>
            @if($act['is_mine'])
              <button
                wire:click="deleteActivityNote({{ $act['id'] }})"
                wire:confirm="Notiz wirklich löschen?"
                class="flex-shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-[var(--t-text-muted)] hover:text-red-500 hover:bg-red-500/10 transition"
                title="Notiz löschen"
              >
                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
              </button>
            @endif
          </div>
        @else
          {{-- System activity --}}
          <div class="flex items-start gap-2.5 py-1.5 px-3" wire:key="act-{{ $act['id'] }}">
            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-[var(--t-text-muted)]/5 flex items-center justify-center mt-0.5">
              @svg('heroicon-o-cog-6-tooth', 'w-3.5 h-3.5 text-[var(--t-text-muted)]/60')
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs text-[var(--t-text-muted)] leading-snug">{{ $act['title'] }}</p>
              <span class="text-[10px] text-[var(--t-text-muted)]/50">{{ $act['time'] }}</span>
            </div>
          </div>
        @endif
      @empty
        <div class="py-8 text-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-[var(--t-text-muted)]/5 mb-3">
            @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--t-text-muted)]')
          </div>
          <p class="text-sm text-[var(--t-text-muted)]">Noch keine Aktivitäten</p>
          <p class="text-xs text-[var(--t-text-muted)]/60 mt-1">Änderungen werden hier angezeigt</p>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Note input --}}
  <div class="border-t border-[var(--t-border)]/60 flex-shrink-0"
       x-data="{
         noteText: '',
         uploadedFiles: [],
         uploading: false,
         dragOver: false,
         get canSend() {
           return this.noteText.trim().length > 0 || this.uploadedFiles.length > 0;
         },
         submitNote() {
           if (!this.canSend) return;
           const ids = this.uploadedFiles.map(f => f.id);
           $wire.addActivityNote(this.noteText.trim(), null, ids);
           this.noteText = '';
           this.uploadedFiles = [];
         },
         handleFiles(files) {
           if (!files || !files.length) return;
           this.uploading = true;
           $wire.uploadMultiple('pendingFiles', Array.from(files), () => {
             $wire.uploadAttachments().then(results => {
               this.uploadedFiles = [...this.uploadedFiles, ...results];
               this.uploading = false;
             });
           }, () => { this.uploading = false; });
         },
         removeFile(index) {
           this.uploadedFiles.splice(index, 1);
         },
       }"
       x-on:dragover.prevent="dragOver = true"
       x-on:dragleave.prevent="dragOver = false"
       x-on:drop.prevent="dragOver = false; handleFiles($event.dataTransfer.files)"
  >
    {{-- Upload preview bar --}}
    <div x-show="uploadedFiles.length > 0 || uploading" x-cloak class="px-4 pt-2 pb-1">
      <div class="flex flex-wrap gap-2">
        <template x-for="(file, index) in uploadedFiles" :key="file.id">
          <div class="relative group/file">
            <template x-if="file.is_image">
              <div class="w-12 h-12 rounded-md overflow-hidden border border-[var(--t-border)]/60 bg-white/5">
                <img :src="file.url" alt="" class="w-full h-full object-cover">
              </div>
            </template>
            <template x-if="!file.is_image">
              <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-md border border-[var(--t-border)]/60 bg-white/5 text-[11px] text-[var(--t-text)] max-w-[140px]">
                <svg class="w-3.5 h-3.5 flex-shrink-0 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3 3.5A1.5 1.5 0 014.5 2h6.879a1.5 1.5 0 011.06.44l4.122 4.12A1.5 1.5 0 0117 7.622V16.5a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 16.5v-13z"/></svg>
                <span class="truncate" x-text="file.original_name"></span>
              </div>
            </template>
            <button
              @click="removeFile(index)"
              class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-red-500 text-white flex items-center justify-center text-[10px] opacity-0 group-hover/file:opacity-100 transition"
            >&times;</button>
          </div>
        </template>
        <template x-if="uploading">
          <div class="w-12 h-12 rounded-md border border-[var(--t-border)]/60 bg-white/5 flex items-center justify-center">
            <svg class="w-4 h-4 animate-spin text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
          </div>
        </template>
      </div>
    </div>

    <div class="px-4 py-2.5">
      <div class="flex items-end gap-2">
        {{-- Paperclip upload button --}}
        <button
          type="button"
          @click="$refs.noteFileInput.click()"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition flex-shrink-0"
          title="Datei anhängen"
        >
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.52 9.52l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.242z" clip-rule="evenodd"/></svg>
        </button>
        <input x-ref="noteFileInput" type="file" multiple class="hidden" @change="handleFiles($event.target.files); $event.target.value = ''">

        <textarea
          x-model="noteText"
          @keydown.enter="if(!$event.shiftKey) { $event.preventDefault(); submitNote(); }"
          placeholder="Notiz hinzufügen…"
          rows="1"
          class="flex-1 min-h-[36px] max-h-24 resize-none rounded-lg border border-[var(--t-border)]/60 bg-[var(--t-glass-surface)] px-3 py-2 text-sm text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:outline-none focus:border-[var(--t-accent)]/50 focus:ring-1 focus:ring-[var(--t-accent)]/20 transition"
        ></textarea>
        <button
          type="button"
          @click="submitNote()"
          :disabled="!canSend"
          :class="canSend ? 'bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 cursor-pointer shadow-sm' : 'border border-[var(--t-border)]/60 text-[var(--t-text-muted)] opacity-40 cursor-not-allowed'"
          class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0"
        >
          <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
        </button>
      </div>
    </div>
  </div>
</div>
