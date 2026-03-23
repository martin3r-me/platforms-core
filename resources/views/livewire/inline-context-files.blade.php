<div
    x-data="{
        lightboxOpen: false,
        lightboxIndex: 0,
        lightboxFiles: [],
        init() {
            this.lightboxFiles = Array.from($el.querySelectorAll('[data-lightbox-url]')).map(el => ({
                url: el.dataset.lightboxUrl,
                name: el.dataset.lightboxName,
                size: el.dataset.lightboxSize,
                date: el.dataset.lightboxDate,
                type: el.dataset.lightboxType,
            }));
        },
        openLightbox(index) {
            this.lightboxIndex = index;
            this.lightboxOpen = true;
        },
        next() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.lightboxFiles.length;
        },
        prev() {
            this.lightboxIndex = (this.lightboxIndex - 1 + this.lightboxFiles.length) % this.lightboxFiles.length;
        },
        get currentFile() {
            return this.lightboxFiles[this.lightboxIndex] || {};
        },
        get isPdf() {
            return this.currentFile.type === 'pdf';
        },
    }"
    @keydown.escape.window="lightboxOpen = false"
    @keydown.arrow-right.window="if (lightboxOpen) next()"
    @keydown.arrow-left.window="if (lightboxOpen) prev()"
>
    <div class="bg-white rounded-lg border border-[var(--ui-border)]/60">
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--ui-border)]/40">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-[var(--ui-primary-5)] rounded-lg flex items-center justify-center">
                    @svg('heroicon-o-paper-clip', 'w-4 h-4 text-[var(--ui-primary)]')
                </div>
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Dateien</h4>
                @if(count($contextFiles) > 0)
                    <span class="px-1.5 py-0.5 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] rounded">
                        {{ count($contextFiles) }}
                    </span>
                @endif
            </div>
            <div>
                <label
                    for="inline-files-upload-{{ $contextId }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 rounded-lg cursor-pointer transition-colors"
                >
                    @svg('heroicon-o-arrow-up-tray', 'w-3.5 h-3.5')
                    Hochladen
                </label>
                <input
                    type="file"
                    multiple
                    wire:model="pendingFiles"
                    class="hidden"
                    id="inline-files-upload-{{ $contextId }}"
                />
            </div>
        </div>

        {{-- Upload Loading State --}}
        <div wire:loading wire:target="pendingFiles" class="px-4 py-3 border-b border-[var(--ui-border)]/40 bg-[var(--ui-primary-5)]/30">
            <div class="flex items-center gap-2 text-sm text-[var(--ui-primary)]">
                @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                <span>Dateien werden hochgeladen...</span>
            </div>
        </div>

        {{-- Content --}}
        @if(count($contextFiles) > 0)
            {{-- File Grid --}}
            <div class="p-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @php $previewIndex = 0; @endphp
                    @foreach($contextFiles as $file)
                        @php
                            $isPdf = str_ends_with(strtolower($file['original_name']), '.pdf') || ($file['mime_type'] ?? '') === 'application/pdf';
                            $isPreviewable = $file['is_image'] || $isPdf;
                        @endphp
                        <div class="group relative">
                            {{-- Thumbnail / Icon --}}
                            @if($file['is_image'])
                                <div
                                    data-lightbox-url="{{ $file['url'] }}"
                                    data-lightbox-name="{{ $file['original_name'] }}"
                                    data-lightbox-size="{{ $file['file_size'] }}"
                                    data-lightbox-date="{{ $file['created_at'] }}"
                                    data-lightbox-type="image"
                                >
                                    <button
                                        type="button"
                                        @click="openLightbox({{ $previewIndex }})"
                                        class="block w-full text-left cursor-pointer"
                                    >
                                        @if($file['thumbnail'])
                                            <div class="aspect-square rounded-lg overflow-hidden bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                                <img
                                                    src="{{ $file['thumbnail'] }}"
                                                    alt="{{ $file['original_name'] }}"
                                                    class="w-full h-full object-cover"
                                                    loading="lazy"
                                                />
                                            </div>
                                        @else
                                            <div class="aspect-square rounded-lg overflow-hidden bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                                <img
                                                    src="{{ $file['url'] }}"
                                                    alt="{{ $file['original_name'] }}"
                                                    class="w-full h-full object-cover"
                                                    loading="lazy"
                                                />
                                            </div>
                                        @endif
                                    </button>
                                </div>
                                @php $previewIndex++; @endphp
                            @elseif($isPdf)
                                <div
                                    data-lightbox-url="{{ $file['url'] }}"
                                    data-lightbox-name="{{ $file['original_name'] }}"
                                    data-lightbox-size="{{ $file['file_size'] }}"
                                    data-lightbox-date="{{ $file['created_at'] }}"
                                    data-lightbox-type="pdf"
                                >
                                    <button
                                        type="button"
                                        @click="openLightbox({{ $previewIndex }})"
                                        class="block w-full text-left cursor-pointer"
                                    >
                                        <div class="aspect-square rounded-lg bg-red-50 border border-[var(--ui-border)]/40 flex flex-col items-center justify-center gap-1">
                                            @svg('heroicon-o-document-text', 'w-8 h-8 text-red-400')
                                            <span class="text-[9px] font-bold uppercase text-red-400 tracking-wide">PDF</span>
                                        </div>
                                    </button>
                                </div>
                                @php $previewIndex++; @endphp
                            @else
                                <a href="{{ $file['download_url'] }}" download="{{ $file['original_name'] }}" class="block">
                                    <div class="aspect-square rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 flex flex-col items-center justify-center gap-1">
                                        @svg('heroicon-o-document', 'w-8 h-8 text-[var(--ui-muted)]')
                                        @php
                                            $ext = pathinfo($file['original_name'], PATHINFO_EXTENSION);
                                        @endphp
                                        @if($ext)
                                            <span class="text-[9px] font-bold uppercase text-[var(--ui-muted)] tracking-wide">{{ $ext }}</span>
                                        @endif
                                    </div>
                                </a>
                            @endif

                            {{-- Hover Overlay --}}
                            @if($isPreviewable)
                                {{-- Previewable: Expand + Download + Delete --}}
                                <div class="absolute inset-0 rounded-lg bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100">
                                    <button
                                        type="button"
                                        @click="openLightbox({{ $previewIndex - 1 }})"
                                        class="p-1.5 bg-white/90 rounded-lg text-[var(--ui-secondary)] hover:bg-white transition-colors"
                                        title="Vergrößern"
                                    >
                                        @svg('heroicon-o-arrows-pointing-out', 'w-3.5 h-3.5')
                                    </button>
                                    <a
                                        href="{{ $file['download_url'] }}"
                                        download="{{ $file['original_name'] }}"
                                        class="p-1.5 bg-white/90 rounded-lg text-[var(--ui-secondary)] hover:bg-white transition-colors"
                                        title="Herunterladen"
                                    >
                                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                                    </a>
                                    <button
                                        wire:click="deleteFile({{ $file['id'] }})"
                                        wire:confirm="Datei wirklich löschen?"
                                        class="p-1.5 bg-white/90 rounded-lg text-[var(--ui-danger)] hover:bg-white transition-colors"
                                        title="Löschen"
                                    >
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            @else
                                {{-- Non-previewable: Download + Delete --}}
                                <div class="absolute inset-0 rounded-lg bg-black/0 group-hover:bg-black/40 transition-colors flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100">
                                    <a
                                        href="{{ $file['download_url'] }}"
                                        download="{{ $file['original_name'] }}"
                                        class="p-1.5 bg-white/90 rounded-lg text-[var(--ui-secondary)] hover:bg-white transition-colors"
                                        title="Herunterladen"
                                    >
                                        @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5')
                                    </a>
                                    <button
                                        wire:click="deleteFile({{ $file['id'] }})"
                                        wire:confirm="Datei wirklich löschen?"
                                        class="p-1.5 bg-white/90 rounded-lg text-[var(--ui-danger)] hover:bg-white transition-colors"
                                        title="Löschen"
                                    >
                                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                    </button>
                                </div>
                            @endif

                            {{-- Filename --}}
                            <p class="mt-1 text-[10px] text-[var(--ui-muted)] truncate px-0.5" title="{{ $file['original_name'] }}">
                                {{ $file['original_name'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Empty State --}}
            <div class="px-4 py-8">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 bg-[var(--ui-muted-5)] rounded-xl flex items-center justify-center mb-2">
                        @svg('heroicon-o-paper-clip', 'w-5 h-5 text-[var(--ui-muted)]/50')
                    </div>
                    <p class="text-xs text-[var(--ui-muted)]">Noch keine Dateien</p>
                    <p class="text-[10px] text-[var(--ui-muted)]/70 mt-0.5">Klicken Sie auf "Hochladen" um Dateien hinzuzufügen</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Lightbox Modal --}}
    <template x-teleport="body">
        <div
            x-show="lightboxOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80"
            @click.self="lightboxOpen = false"
            x-cloak
        >
            {{-- Close Button --}}
            <button
                @click="lightboxOpen = false"
                class="absolute top-4 right-4 p-2 text-white/70 hover:text-white transition-colors z-10"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Prev Button --}}
            <button
                x-show="lightboxFiles.length > 1"
                @click="prev()"
                class="absolute left-4 p-2 text-white/70 hover:text-white transition-colors z-10"
            >
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- Next Button --}}
            <button
                x-show="lightboxFiles.length > 1"
                @click="next()"
                class="absolute right-4 p-2 text-white/70 hover:text-white transition-colors z-10"
            >
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            {{-- Content: Image or PDF --}}
            <div class="max-w-[90vw] max-h-[90vh] flex flex-col items-center">
                {{-- Image --}}
                <img
                    x-show="!isPdf"
                    x-bind:src="currentFile.url"
                    x-bind:alt="currentFile.name"
                    class="max-w-full max-h-[80vh] object-contain rounded-lg"
                />
                {{-- PDF --}}
                <iframe
                    x-show="isPdf"
                    x-bind:src="lightboxOpen && isPdf ? currentFile.url : ''"
                    class="w-[80vw] h-[80vh] rounded-lg bg-white"
                ></iframe>
                {{-- Info --}}
                <div class="mt-3 text-center">
                    <p class="text-sm text-white font-medium" x-text="currentFile.name"></p>
                    <p class="text-xs text-white/60 mt-0.5">
                        <span x-text="currentFile.date"></span>
                        <span class="mx-1">&middot;</span>
                        <span x-text="(lightboxIndex + 1) + ' / ' + lightboxFiles.length"></span>
                    </p>
                </div>
            </div>
        </div>
    </template>
</div>
