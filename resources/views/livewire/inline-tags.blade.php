<div class="space-y-3" x-data="{ showSuggestions: @entangle('showSuggestions') }">
    {{-- Zugeordnete Tags + Farbe in einer kompakten Zeile --}}
    <div class="flex items-center gap-2 flex-wrap min-h-[28px]">
        {{-- Farbindikator (unabhängig von Tags) --}}
        @if($contextColor)
            <button
                type="button"
                wire:click="toggleColorPicker"
                class="group flex items-center gap-1 px-1.5 py-0.5 rounded border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 transition-colors"
                title="Farbe bearbeiten"
            >
                <span class="w-3 h-3 rounded-full flex-shrink-0 border border-black/10" style="background-color: {{ $contextColor }}"></span>
                <span class="text-[10px] text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors">Farbe</span>
            </button>
        @endif

        {{-- Tag-Badges --}}
        @foreach($assignedTags as $tag)
            <span
                wire:key="tag-{{ $tag['id'] }}-{{ $tag['is_personal'] ? 'p' : 't' }}"
                class="group inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40 transition-colors"
            >
                @if($tag['color'])
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                @endif
                @if($editingTagId === $tag['id'])
                    <input
                        type="text"
                        wire:model="editTagLabel"
                        wire:keydown.enter="saveEditTag"
                        wire:keydown.escape="cancelEditTag"
                        class="w-20 bg-transparent border-none p-0 text-xs focus:ring-0 focus:outline-none text-[var(--ui-secondary)]"
                        autofocus
                    />
                    <input
                        type="color"
                        wire:model="editTagColor"
                        class="w-4 h-4 border-none cursor-pointer p-0"
                    />
                    <button type="button" wire:click="saveEditTag" class="text-[var(--ui-success)] hover:text-[var(--ui-success)]/80">
                        @svg('heroicon-o-check', 'w-3 h-3')
                    </button>
                    <button type="button" wire:click="cancelEditTag" class="text-[var(--ui-muted)] hover:text-[var(--ui-danger)]">
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @else
                    <span class="cursor-default" wire:click="startEditTag({{ $tag['id'] }})" title="Klicken zum Bearbeiten">{{ $tag['label'] }}</span>
                    @if($tag['is_personal'])
                        <span class="text-[9px] text-[var(--ui-muted)]">(P)</span>
                    @endif
                    <button
                        type="button"
                        wire:click="removeTag({{ $tag['id'] }}, {{ $tag['is_personal'] ? 'true' : 'false' }})"
                        class="opacity-0 group-hover:opacity-100 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-all"
                        title="Tag entfernen"
                    >
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
            </span>
        @endforeach

        {{-- "+" Button für neues Tag --}}
        <div class="relative" x-on:click.away="showSuggestions = false">
            <div class="flex items-center gap-1">
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="tagInput"
                        @focus="if($wire.tagInput.length > 0) showSuggestions = true"
                        @keydown.enter.prevent="$wire.createAndAddTag()"
                        placeholder="+ Tag..."
                        class="w-24 focus:w-40 px-2 py-0.5 text-xs border border-dashed border-[var(--ui-border)]/60 rounded bg-transparent text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:border-[var(--ui-primary)]/60 focus:outline-none focus:ring-0 transition-all duration-200"
                    />

                    {{-- Vorschläge Dropdown --}}
                    <div
                        x-show="showSuggestions"
                        x-transition
                        class="absolute z-20 left-0 mt-1 w-64 bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 rounded-lg shadow-lg max-h-48 overflow-y-auto"
                    >
                        @if(count($tagSuggestions) > 0)
                            @foreach($tagSuggestions as $suggestion)
                                <button
                                    type="button"
                                    wire:click="addTag({{ $suggestion['id'] }})"
                                    @click="showSuggestions = false"
                                    class="w-full flex items-center gap-2 px-3 py-2 hover:bg-[var(--ui-muted-5)] transition-colors text-left"
                                >
                                    @if($suggestion['color'])
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $suggestion['color'] }}"></span>
                                    @endif
                                    <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $suggestion['label'] }}</span>
                                    @if($suggestion['is_team_tag'])
                                        <span class="text-[9px] text-[var(--ui-muted)] px-1 bg-[var(--ui-muted-5)] rounded">Team</span>
                                    @endif
                                </button>
                            @endforeach
                        @endif

                        @if(strlen($tagInput) >= 2)
                            <button
                                type="button"
                                wire:click="createAndAddTag"
                                @click="showSuggestions = false"
                                class="w-full flex items-center gap-2 px-3 py-2 border-t border-[var(--ui-border)]/40 hover:bg-[var(--ui-primary-5)] transition-colors text-left"
                            >
                                @svg('heroicon-o-plus', 'w-3 h-3 text-[var(--ui-primary)]')
                                <span class="text-xs font-medium text-[var(--ui-primary)]">"{{ $tagInput }}" erstellen</span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Farb-Button --}}
                @if(!$contextColor)
                    <button
                        type="button"
                        wire:click="toggleColorPicker"
                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors rounded"
                        title="Farbe setzen"
                    >
                        @svg('heroicon-o-swatch', 'w-3.5 h-3.5')
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Farb-Picker (kompakt, inline) --}}
    @if($showColorPicker)
        <div class="flex items-center gap-2 p-2 rounded border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
            <input
                type="color"
                wire:model.live="newColor"
                class="w-8 h-8 border border-[var(--ui-border)]/40 rounded cursor-pointer p-0"
            />
            @if($newColor)
                <span class="w-6 h-6 rounded border border-black/10" style="background-color: {{ $newColor }}"></span>
                <span class="text-xs text-[var(--ui-muted)] font-mono">{{ $newColor }}</span>
            @endif
            <div class="flex items-center gap-1 ml-auto">
                <button
                    type="button"
                    wire:click="setColor"
                    class="px-2 py-1 text-xs font-medium bg-[var(--ui-primary)] text-white rounded hover:bg-[var(--ui-primary)]/90 transition-colors"
                >
                    Setzen
                </button>
                @if($contextColor)
                    <button
                        type="button"
                        wire:click="removeColor"
                        class="px-2 py-1 text-xs text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded transition-colors"
                    >
                        Entfernen
                    </button>
                @endif
                <button
                    type="button"
                    wire:click="toggleColorPicker"
                    class="px-2 py-1 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition-colors"
                >
                    Abbrechen
                </button>
            </div>
        </div>
    @endif
</div>
