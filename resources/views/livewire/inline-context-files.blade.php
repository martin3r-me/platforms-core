<div>
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
                <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 gap-2">
                    @foreach($contextFiles as $file)
                        <div class="group relative">
                            {{-- Thumbnail / Icon --}}
                            @if($file['is_image'])
                                <a href="{{ $file['url'] }}" target="_blank" class="block">
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
                                </a>
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

                            {{-- Hover Overlay with actions --}}
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
</div>
