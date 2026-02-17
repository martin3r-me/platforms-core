{{--
    Inline File-Upload Komponente – Premium-Optik
    Drag & Drop, Fortschrittsanzeige, Dateivorschau, Lösch-/Austausch-Option.
    Jede Instanz hat isolierten State via wire:key.
--}}
<div
    x-data="{
        isDragging: false,
        uploadProgress: 0,
        isProcessing: @entangle('isUploading'),

        handleDragEnter(e) {
            e.preventDefault();
            this.isDragging = true;
        },
        handleDragOver(e) {
            e.preventDefault();
            this.isDragging = true;
        },
        handleDragLeave(e) {
            e.preventDefault();
            // Nur wenn wirklich die Drop-Zone verlassen wird
            if (!e.currentTarget.contains(e.relatedTarget)) {
                this.isDragging = false;
            }
        },
        handleDrop(e) {
            e.preventDefault();
            this.isDragging = false;
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                // Livewire file upload via Input-Element
                const input = this.$refs.fileInput;
                const dt = new DataTransfer();
                const maxFiles = {{ $multiple ? '999' : '1' }};
                for (let i = 0; i < Math.min(files.length, maxFiles); i++) {
                    dt.items.add(files[i]);
                }
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    }"
    class="space-y-2"
>
    {{-- Label --}}
    @if($showLabel && $label)
        <div class="flex items-center gap-2 mb-1">
            @svg('heroicon-o-paper-clip', 'w-4 h-4 text-[var(--ui-primary)]')
            <span class="text-xs font-semibold text-[var(--ui-secondary)]">{{ $label }}</span>
            @if($multiple)
                <span class="text-[10px] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-md font-medium">Mehrfach</span>
            @endif
        </div>
    @endif

    {{-- Bereits hochgeladene Dateien --}}
    @if(count($uploadedFilesData) > 0)
        <div class="space-y-1.5">
            @foreach($uploadedFilesData as $idx => $file)
                <div
                    wire:key="uploaded-file-{{ $file['id'] }}"
                    class="group relative flex items-center gap-3 px-3 py-2.5 rounded-xl border border-[var(--ui-border)]/30 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/30 hover:shadow-sm transition-all duration-200"
                >
                    {{-- Thumbnail / Icon --}}
                    <div class="relative flex-shrink-0">
                        @if($file['is_image'] && $file['thumbnail_url'])
                            <img
                                src="{{ $file['thumbnail_url'] }}"
                                alt="{{ $file['original_name'] }}"
                                class="w-10 h-10 rounded-lg object-cover ring-1 ring-black/5 shadow-sm"
                                loading="lazy"
                            />
                        @elseif($file['is_image'])
                            <img
                                src="{{ $file['url'] }}"
                                alt="{{ $file['original_name'] }}"
                                class="w-10 h-10 rounded-lg object-cover ring-1 ring-black/5 shadow-sm"
                                loading="lazy"
                            />
                        @else
                            <div class="w-10 h-10 rounded-lg bg-[var(--ui-muted-5)] ring-1 ring-black/5 flex items-center justify-center">
                                @php
                                    $icon = match(true) {
                                        str_contains($file['mime_type'], 'pdf') => 'heroicon-o-document-text',
                                        str_contains($file['mime_type'], 'spreadsheet') || str_contains($file['mime_type'], 'excel') => 'heroicon-o-table-cells',
                                        str_contains($file['mime_type'], 'word') || str_contains($file['mime_type'], 'document') => 'heroicon-o-document',
                                        str_contains($file['mime_type'], 'video') => 'heroicon-o-film',
                                        str_contains($file['mime_type'], 'audio') => 'heroicon-o-musical-note',
                                        str_contains($file['mime_type'], 'zip') || str_contains($file['mime_type'], 'archive') => 'heroicon-o-archive-box',
                                        default => 'heroicon-o-document',
                                    };
                                @endphp
                                @svg($icon, 'w-5 h-5 text-[var(--ui-muted)]')
                            </div>
                        @endif
                        {{-- Nummer-Badge bei mehreren Dateien --}}
                        @if($multiple && count($uploadedFilesData) > 1)
                            <span class="absolute -top-1 -left-1 w-4 h-4 rounded-full bg-[var(--ui-primary)] text-white text-[9px] font-bold flex items-center justify-center ring-2 ring-[var(--ui-surface)]">
                                {{ $idx + 1 }}
                            </span>
                        @endif
                    </div>

                    {{-- Datei-Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-[var(--ui-secondary)] truncate" title="{{ $file['original_name'] }}">
                            {{ $file['original_name'] }}
                        </p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] text-[var(--ui-muted)]">
                                {{ $this->formatFileSize($file['file_size']) }}
                            </span>
                            @if($file['is_image'] && $file['width'] && $file['height'])
                                <span class="text-[10px] text-[var(--ui-muted)]">
                                    {{ $file['width'] }}×{{ $file['height'] }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Aktionen --}}
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                        {{-- Austauschen --}}
                        <button
                            type="button"
                            wire:click="replaceFile({{ $file['id'] }})"
                            class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5 rounded-lg transition-all duration-150"
                            title="Datei austauschen"
                        >
                            @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                        </button>
                        {{-- Löschen --}}
                        <button
                            type="button"
                            wire:click="removeFile({{ $file['id'] }})"
                            class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger)]/5 rounded-lg transition-all duration-150"
                            title="Datei entfernen"
                        >
                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Drop-Zone / Upload-Bereich --}}
    @if($multiple || count($uploadedFilesData) === 0)
        <div
            x-on:dragenter="handleDragEnter($event)"
            x-on:dragover="handleDragOver($event)"
            x-on:dragleave="handleDragLeave($event)"
            x-on:drop="handleDrop($event)"
            x-on:click="$refs.fileInput.click()"
            :class="{
                'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5 shadow-sm shadow-[var(--ui-primary)]/10': isDragging,
                'border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/30 hover:border-[var(--ui-primary)]/40 hover:bg-[var(--ui-primary)]/3': !isDragging,
            }"
            class="relative flex flex-col items-center justify-center gap-2 px-4 py-5 rounded-xl border-2 border-dashed cursor-pointer transition-all duration-300 group"
        >
            {{-- Fortschrittsanzeige --}}
            <div
                x-show="isProcessing"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-xl bg-[var(--ui-surface)]/95 backdrop-blur-sm z-10"
            >
                <div class="relative w-10 h-10">
                    <svg class="animate-spin w-10 h-10 text-[var(--ui-primary)]" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <span class="text-xs font-medium text-[var(--ui-primary)]">Wird hochgeladen...</span>

                {{-- Progress Bar --}}
                <div
                    wire:loading.flex
                    wire:target="pendingFiles"
                    class="w-2/3 h-1.5 bg-[var(--ui-muted-5)] rounded-full overflow-hidden"
                >
                    <div class="h-full bg-[var(--ui-primary)] rounded-full transition-all duration-300 animate-pulse" style="width: 60%"></div>
                </div>
            </div>

            {{-- Upload Icon --}}
            <div
                :class="{
                    'bg-[var(--ui-primary)]/15 text-[var(--ui-primary)] scale-110': isDragging,
                    'bg-[var(--ui-muted-5)] text-[var(--ui-muted)] group-hover:bg-[var(--ui-primary)]/10 group-hover:text-[var(--ui-primary)]': !isDragging,
                }"
                class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300"
            >
                <template x-if="isDragging">
                    <span>@svg('heroicon-o-arrow-down-tray', 'w-5 h-5')</span>
                </template>
                <template x-if="!isDragging">
                    <span>@svg('heroicon-o-cloud-arrow-up', 'w-5 h-5')</span>
                </template>
            </div>

            {{-- Text --}}
            <div class="text-center">
                <p
                    :class="{ 'text-[var(--ui-primary)]': isDragging }"
                    class="text-xs font-medium text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] transition-colors duration-200"
                >
                    <template x-if="isDragging">
                        <span>Datei hier ablegen</span>
                    </template>
                    <template x-if="!isDragging">
                        <span>
                            <span class="text-[var(--ui-primary)] font-semibold">Klicken</span>
                            oder Datei hierher ziehen
                        </span>
                    </template>
                </p>
                <p class="text-[10px] text-[var(--ui-muted)] mt-0.5">
                    Max. {{ $maxFileSizeMb }} MB
                    @if($accept)
                        · {{ $accept }}
                    @endif
                </p>
            </div>

            {{-- Hidden File Input --}}
            <input
                x-ref="fileInput"
                type="file"
                wire:model="pendingFiles"
                @if($multiple) multiple @endif
                @if($accept) accept="{{ $accept }}" @endif
                class="hidden"
            />
        </div>
    @endif

    {{-- Single-Datei: Upload-Button wenn bereits Datei vorhanden --}}
    @if(!$multiple && count($uploadedFilesData) > 0)
        <button
            type="button"
            wire:click="$set('showDropZone', true)"
            x-on:click="$nextTick(() => $refs.fileInput?.click())"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-[var(--ui-muted)] hover:text-[var(--ui-primary)] border border-dashed border-[var(--ui-border)]/30 hover:border-[var(--ui-primary)]/30 rounded-lg hover:bg-[var(--ui-primary)]/3 transition-all duration-200"
        >
            @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
            Datei austauschen
        </button>
    @endif

    {{-- Fehler-Anzeige --}}
    @error('pendingFiles.*')
        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--ui-danger)]/5 border border-[var(--ui-danger)]/20">
            @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-danger)] flex-shrink-0')
            <span class="text-xs text-[var(--ui-danger)]">{{ $message }}</span>
        </div>
    @enderror
</div>
