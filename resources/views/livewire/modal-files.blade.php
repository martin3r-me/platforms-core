<div>
<x-ui-modal size="lg" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-[var(--ui-primary-5)] flex items-center justify-center">
                    @svg('heroicon-o-paper-clip', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Dateien</h3>
                @if($contextType && $contextId && $this->contextBreadcrumb)
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        @foreach($this->contextBreadcrumb as $index => $crumb)
                            <div class="flex items-center gap-2">
                                @if($index > 0)
                                    @svg('heroicon-o-chevron-right', 'w-3 h-3 text-[var(--ui-muted)]')
                                @endif
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/40">
                                    <span class="text-[var(--ui-muted)]">{{ $crumb['type'] }}:</span>
                                    <span class="font-semibold">{{ $crumb['label'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Dateien kontextbezogen hochladen</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div>
        @if($contextType && $contextId)
            <!-- Datei-Upload Bereich -->
            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Dateien hochladen
                    </h4>
                    
                    <div class="border-2 border-dashed border-[var(--ui-border)]/60 rounded-xl p-8 text-center bg-[var(--ui-muted-5)]/30">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-primary-5)] flex items-center justify-center">
                            @svg('heroicon-o-cloud-arrow-up', 'w-8 h-8 text-[var(--ui-primary)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-2">
                            Dateien hier ablegen oder klicken zum Auswählen
                        </p>
                        <p class="text-xs text-[var(--ui-muted)] mb-4">
                            Mehrere Dateien können gleichzeitig hochgeladen werden
                        </p>
                        <input
                            type="file"
                            multiple
                            wire:model="files"
                            class="hidden"
                            id="file-upload-input"
                        />
                        <label
                            for="file-upload-input"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors rounded-lg cursor-pointer text-sm font-medium"
                        >
                            @svg('heroicon-o-folder-plus', 'w-4 h-4')
                            Dateien auswählen
                        </label>
                    </div>

                    @if(count($files) > 0)
                        <div class="mt-4 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <p class="text-sm font-medium text-[var(--ui-secondary)] mb-3">
                                {{ count($files) }} Datei(en) ausgewählt
                            </p>
                            <div class="space-y-2 mb-4">
                                @foreach($files as $index => $file)
                                    <div class="flex items-center justify-between text-xs text-[var(--ui-muted)]">
                                        <span>{{ $file->getClientOriginalName() }}</span>
                                        <span>{{ number_format($file->getSize() / 1024, 2) }} KB</span>
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- Optionen für Bilder -->
                            <div class="space-y-2 mb-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        wire:model="generateVariants"
                                        class="rounded border-[var(--ui-border)]/60 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                    />
                                    <span class="text-sm text-[var(--ui-secondary)]">Bildvarianten generieren (Standard: Thumbnail)</span>
                                </label>
                                @if($generateVariants)
                                    <label class="flex items-center gap-2 cursor-pointer ml-6">
                                        <input
                                            type="checkbox"
                                            wire:model="keepOriginal"
                                            class="rounded border-[var(--ui-border)]/60 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                        />
                                        <span class="text-sm text-[var(--ui-secondary)]">Original behalten + weitere Varianten</span>
                                    </label>
                                @endif
                            </div>

                            <x-ui-button
                                variant="primary"
                                wire:click="uploadFiles"
                                wire:loading.attr="disabled"
                                class="w-full"
                            >
                                <span wire:loading.remove wire:target="uploadFiles">
                                    Hochladen
                                </span>
                                <span wire:loading wire:target="uploadFiles" class="inline-flex items-center gap-2">
                                    @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                    Wird hochgeladen...
                                </span>
                            </x-ui-button>
                        </div>
                    @endif
                </div>

                <!-- Hochgeladene Dateien -->
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Hochgeladene Dateien
                    </h4>
                    
                    <div class="space-y-2">
                        @forelse($uploadedFiles as $file)
                            <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg">
                                <div class="flex items-start gap-4">
                                    @if($file['is_image'] && $file['thumbnail'])
                                        <img
                                            src="{{ $file['thumbnail'] }}"
                                            alt="{{ $file['original_name'] }}"
                                            class="w-16 h-16 object-cover rounded border border-[var(--ui-border)]/40"
                                        />
                                    @else
                                        <div class="w-16 h-16 bg-[var(--ui-primary-5)] rounded border border-[var(--ui-border)]/40 flex items-center justify-center">
                                            @svg('heroicon-o-document', 'w-8 h-8 text-[var(--ui-primary)]')
                                        </div>
                                    @endif
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-[var(--ui-secondary)] truncate">
                                            {{ $file['original_name'] }}
                                        </p>
                                        <p class="text-xs text-[var(--ui-muted)] mt-1">
                                            {{ number_format($file['file_size'] / 1024, 2) }} KB
                                            @if($file['width'] && $file['height'])
                                                • {{ $file['width'] }}×{{ $file['height'] }}
                                            @endif
                                            • {{ $file['created_at'] }}
                                        </p>
                                        @if(count($file['variants']) > 0)
                                            <div class="mt-2 flex gap-2 flex-wrap">
                                                @foreach($file['variants'] as $variant)
                                                    <a
                                                        href="{{ $variant['url'] }}"
                                                        target="_blank"
                                                        class="text-xs px-2 py-1 bg-[var(--ui-surface)] text-[var(--ui-secondary)] rounded border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]"
                                                    >
                                                        {{ $variant['type'] }} ({{ $variant['width'] }}×{{ $variant['height'] }})
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <a
                                            href="{{ $file['download_url'] }}"
                                            download="{{ $file['original_name'] }}"
                                            class="p-2 text-[var(--ui-primary)] hover:bg-[var(--ui-primary-5)] rounded transition-colors"
                                            title="Download"
                                        >
                                            @svg('heroicon-o-arrow-down-tray', 'w-5 h-5')
                                        </a>
                                        <button
                                            wire:click="deleteFile({{ $file['id'] }})"
                                            wire:confirm="Datei wirklich löschen?"
                                            class="p-2 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded transition-colors"
                                            title="Löschen"
                                        >
                                            @svg('heroicon-o-trash', 'w-5 h-5')
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg text-center">
                                <p class="text-sm text-[var(--ui-muted)]">
                                    Noch keine Dateien hochgeladen
                                </p>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">
                                    Dateien werden hier angezeigt, sobald sie hochgeladen wurden
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                    @svg('heroicon-o-paper-clip', 'w-8 h-8 text-[var(--ui-muted)]')
                </div>
                <p class="text-[var(--ui-muted)]">Kein Kontext ausgewählt.</p>
                <p class="text-sm text-[var(--ui-muted)] mt-2">Wählen Sie einen Kontext aus, um Dateien hochzuladen.</p>
            </div>
        @endif
    </div>

    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-ui-button variant="secondary" wire:click="close">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>

