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
                </div>

                <!-- Hochgeladene Dateien (Platzhalter) -->
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Hochgeladene Dateien
                    </h4>
                    
                    <div class="space-y-2">
                        <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 rounded-lg text-center">
                            <p class="text-sm text-[var(--ui-muted)]">
                                Noch keine Dateien hochgeladen
                            </p>
                            <p class="text-xs text-[var(--ui-muted)] mt-1">
                                Dateien werden hier angezeigt, sobald sie hochgeladen wurden
                            </p>
                        </div>
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

