<div
    class="space-y-3"
    x-data="{
        showSuggestions: @entangle('showSuggestions'),
        showTagBrowser: @entangle('showTagBrowser'),
        showColorPicker: @entangle('showColorPicker'),
    }"
>
    {{-- Zugeordnete Tags + Farbe in einer kompakten Zeile --}}
    <div class="flex items-center gap-1.5 flex-wrap min-h-[32px]">
        {{-- Farbindikator (unabhängig von Tags) --}}
        @if($contextColor)
            <button
                type="button"
                wire:click="toggleColorPicker"
                class="group inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-[var(--ui-border)]/30 hover:border-[var(--ui-primary)]/50 hover:shadow-sm bg-[var(--ui-surface)] transition-all duration-200"
                title="Farbe bearbeiten"
            >
                <span class="w-3.5 h-3.5 rounded-full flex-shrink-0 ring-2 ring-white/80 shadow-sm" style="background-color: {{ $contextColor }}"></span>
                <span class="text-[10px] font-medium text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors duration-200">Farbe</span>
            </button>
        @endif

        {{-- Tag-Badges (zugeordnete Tags) --}}
        @foreach($assignedTags as $tag)
            <span
                wire:key="tag-{{ $tag['id'] }}-{{ $tag['is_personal'] ? 'p' : 't' }}"
                class="group inline-flex items-center gap-1.5 pl-1.5 pr-2 py-1 text-xs font-medium rounded-lg transition-all duration-200
                    {{ $tag['color']
                        ? 'border shadow-sm hover:shadow-md'
                        : 'bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/40'
                    }}"
                @if($tag['color'])
                    style="background-color: {{ $tag['color'] }}12; border-color: {{ $tag['color'] }}40; color: {{ $tag['color'] }}"
                @endif
            >
                @if($tag['color'])
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 ring-1 ring-white/60 shadow-sm" style="background-color: {{ $tag['color'] }}"></span>
                @else
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-[var(--ui-muted)]/30"></span>
                @endif

                @if($editingTagId === $tag['id'])
                    {{-- Inline-Edit-Modus --}}
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
                        class="w-4 h-4 border-none cursor-pointer p-0 rounded"
                    />
                    <button type="button" wire:click="saveEditTag" class="text-[var(--ui-success)] hover:text-[var(--ui-success)]/80 transition-colors">
                        @svg('heroicon-o-check', 'w-3.5 h-3.5')
                    </button>
                    <button type="button" wire:click="cancelEditTag" class="text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors">
                        @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                    </button>
                @else
                    <span
                        class="cursor-default select-none {{ !$tag['color'] ? 'text-[var(--ui-secondary)]' : '' }}"
                        wire:click="startEditTag({{ $tag['id'] }})"
                        title="Klicken zum Bearbeiten"
                    >{{ $tag['label'] }}</span>
                    @if($tag['is_personal'])
                        <span class="text-[9px] opacity-60 font-normal">(P)</span>
                    @endif
                    <button
                        type="button"
                        wire:click="removeTag({{ $tag['id'] }}, {{ $tag['is_personal'] ? 'true' : 'false' }})"
                        class="opacity-0 group-hover:opacity-100 ml-0.5 -mr-0.5 p-0.5 rounded-full hover:bg-black/10 text-current transition-all duration-150"
                        title="Tag entfernen"
                    >
                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                    </button>
                @endif
            </span>
        @endforeach

        {{-- Tag-Aktionen: Suche + Multi-Select Browser --}}
        <div class="relative flex items-center gap-1" x-on:click.away="showSuggestions = false">
            {{-- Inline-Suchfeld --}}
            <div class="relative">
                <input
                    type="text"
                    wire:model.live.debounce.250ms="tagInput"
                    @focus="if($wire.tagInput.length > 0) showSuggestions = true"
                    @keydown.enter.prevent="$wire.createAndAddTag()"
                    placeholder="+ Tag..."
                    class="w-20 focus:w-36 px-2 py-1 text-xs border border-dashed border-[var(--ui-border)]/50 rounded-lg bg-transparent text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]/60 focus:border-[var(--ui-primary)]/50 focus:bg-[var(--ui-surface)] focus:outline-none focus:ring-0 transition-all duration-200"
                />

                {{-- Vorschläge Dropdown --}}
                <div
                    x-show="showSuggestions"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-1"
                    class="absolute z-30 left-0 mt-1.5 w-64 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40 rounded-xl shadow-xl shadow-black/8 max-h-52 overflow-y-auto ring-1 ring-black/5"
                >
                    @if(count($tagSuggestions) > 0)
                        <div class="p-1">
                            @foreach($tagSuggestions as $suggestion)
                                <button
                                    type="button"
                                    wire:click="addTag({{ $suggestion['id'] }})"
                                    @click="showSuggestions = false"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors duration-150 text-left group"
                                >
                                    @if($suggestion['color'])
                                        <span class="w-3 h-3 rounded-full flex-shrink-0 ring-1 ring-black/10 shadow-sm" style="background-color: {{ $suggestion['color'] }}"></span>
                                    @else
                                        <span class="w-3 h-3 rounded-full flex-shrink-0 bg-[var(--ui-muted)]/20 ring-1 ring-black/5"></span>
                                    @endif
                                    <span class="text-xs font-medium text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] transition-colors">{{ $suggestion['label'] }}</span>
                                    @if($suggestion['is_team_tag'])
                                        <span class="text-[9px] text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] rounded-md font-medium">Team</span>
                                    @endif
                                    <span class="ml-auto opacity-0 group-hover:opacity-100 transition-opacity">
                                        @svg('heroicon-o-plus', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if(strlen($tagInput) >= 2)
                        <div class="border-t border-[var(--ui-border)]/30 p-1">
                            <button
                                type="button"
                                wire:click="createAndAddTag"
                                @click="showSuggestions = false"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-[var(--ui-primary)]/8 transition-colors duration-150 text-left"
                            >
                                <span class="w-5 h-5 rounded-full bg-[var(--ui-primary)]/10 flex items-center justify-center">
                                    @svg('heroicon-o-plus', 'w-3 h-3 text-[var(--ui-primary)]')
                                </span>
                                <span class="text-xs font-medium text-[var(--ui-primary)]">"{{ $tagInput }}" erstellen</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Multi-Select Browser Button --}}
            <button
                type="button"
                wire:click="openTagBrowser"
                class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5 rounded-lg transition-all duration-200"
                title="Alle Tags durchblättern (Multi-Select)"
            >
                @svg('heroicon-o-tag', 'w-3.5 h-3.5')
            </button>

            {{-- Farb-Button --}}
            @if(!$contextColor)
                <button
                    type="button"
                    wire:click="toggleColorPicker"
                    class="p-1.5 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5 rounded-lg transition-all duration-200"
                    title="Farbe setzen"
                >
                    @svg('heroicon-o-swatch', 'w-3.5 h-3.5')
                </button>
            @endif
        </div>
    </div>

    {{-- Tag-Browser: Multi-Select Panel --}}
    @if($showTagBrowser)
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-[0.98]"
            x-transition:enter-end="opacity-100 scale-100"
            class="rounded-xl border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] shadow-lg shadow-black/5 overflow-hidden"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--ui-border)]/30 bg-gradient-to-r from-[var(--ui-primary)]/3 to-transparent">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-tag', 'w-4 h-4 text-[var(--ui-primary)]')
                    <span class="text-xs font-semibold text-[var(--ui-secondary)]">Tags verwalten</span>
                    <span class="text-[10px] text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-1.5 py-0.5 rounded-md font-medium">Multi-Select</span>
                </div>
                <button
                    type="button"
                    wire:click="closeTagBrowser"
                    class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded-lg transition-all duration-150"
                >
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                </button>
            </div>

            {{-- Suchfeld --}}
            <div class="px-4 py-2.5 border-b border-[var(--ui-border)]/20">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                        @svg('heroicon-o-magnifying-glass', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                    </span>
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="browserSearch"
                        placeholder="Tags durchsuchen..."
                        class="w-full pl-8 pr-3 py-1.5 text-xs border border-[var(--ui-border)]/30 rounded-lg bg-[var(--ui-muted-5)]/50 text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]/60 focus:border-[var(--ui-primary)]/40 focus:bg-[var(--ui-surface)] focus:outline-none focus:ring-0 transition-all duration-200"
                    />
                </div>
            </div>

            {{-- Tag-Liste --}}
            <div class="max-h-56 overflow-y-auto p-1.5">
                @if(count($availableTags) > 0)
                    <div class="space-y-0.5">
                        @foreach($availableTags as $availTag)
                            <button
                                type="button"
                                wire:click="toggleTag({{ $availTag['id'] }})"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg transition-all duration-150 text-left group
                                    {{ $availTag['is_assigned']
                                        ? 'bg-[var(--ui-primary)]/8 border border-[var(--ui-primary)]/20'
                                        : 'hover:bg-[var(--ui-muted-5)] border border-transparent'
                                    }}"
                            >
                                {{-- Checkbox-Indikator --}}
                                <span class="w-4 h-4 rounded flex-shrink-0 flex items-center justify-center transition-all duration-150
                                    {{ $availTag['is_assigned']
                                        ? 'bg-[var(--ui-primary)] text-white shadow-sm'
                                        : 'border border-[var(--ui-border)]/60 group-hover:border-[var(--ui-primary)]/40'
                                    }}"
                                >
                                    @if($availTag['is_assigned'])
                                        @svg('heroicon-o-check', 'w-3 h-3')
                                    @endif
                                </span>

                                {{-- Farb-Dot --}}
                                @if($availTag['color'])
                                    <span class="w-3 h-3 rounded-full flex-shrink-0 ring-1 ring-black/10 shadow-sm" style="background-color: {{ $availTag['color'] }}"></span>
                                @else
                                    <span class="w-3 h-3 rounded-full flex-shrink-0 bg-[var(--ui-muted)]/20 ring-1 ring-black/5"></span>
                                @endif

                                {{-- Label --}}
                                <span class="text-xs font-medium flex-1 {{ $availTag['is_assigned'] ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)]' }}">
                                    {{ $availTag['label'] }}
                                </span>

                                {{-- Team-Badge --}}
                                @if($availTag['is_team_tag'])
                                    <span class="text-[9px] text-[var(--ui-muted)] px-1.5 py-0.5 bg-[var(--ui-muted-5)] rounded-md font-medium">Team</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-6 text-center">
                        @svg('heroicon-o-tag', 'w-8 h-8 text-[var(--ui-muted)]/30 mb-2')
                        <span class="text-xs text-[var(--ui-muted)]">Keine Tags gefunden</span>
                    </div>
                @endif
            </div>

            {{-- Neues Tag erstellen --}}
            @if(!empty($browserSearch))
                <div class="border-t border-[var(--ui-border)]/20 p-2">
                    <button
                        type="button"
                        wire:click="createAndAddTagFromBrowser"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors duration-150 text-left"
                    >
                        <span class="w-5 h-5 rounded-full bg-[var(--ui-primary)]/10 flex items-center justify-center">
                            @svg('heroicon-o-plus', 'w-3 h-3 text-[var(--ui-primary)]')
                        </span>
                        <span class="text-xs font-medium text-[var(--ui-primary)]">"{{ $browserSearch }}" als neues Tag erstellen</span>
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Premium Farb-Picker --}}
    @if($showColorPicker)
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-[0.98]"
            x-transition:enter-end="opacity-100 scale-100"
            class="rounded-xl border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] shadow-lg shadow-black/5 overflow-hidden"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-[var(--ui-border)]/30 bg-gradient-to-r from-[var(--ui-primary)]/3 to-transparent">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-swatch', 'w-4 h-4 text-[var(--ui-primary)]')
                    <span class="text-xs font-semibold text-[var(--ui-secondary)]">Farbe wählen</span>
                </div>
                <button
                    type="button"
                    wire:click="toggleColorPicker"
                    class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded-lg transition-all duration-150"
                >
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                </button>
            </div>

            <div class="p-4 space-y-4">
                {{-- Vorschau --}}
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <span
                            class="block w-10 h-10 rounded-xl shadow-inner border border-black/10 transition-colors duration-300"
                            style="background-color: {{ $newColor ?? '#e2e8f0' }}"
                        ></span>
                        @if($newColor)
                            <span class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full bg-[var(--ui-success)] ring-2 ring-[var(--ui-surface)] flex items-center justify-center">
                                @svg('heroicon-o-check', 'w-2 h-2 text-white')
                            </span>
                        @endif
                    </div>
                    <div>
                        <span class="block text-xs font-medium text-[var(--ui-secondary)]">
                            {{ $newColor ? 'Ausgewählte Farbe' : 'Keine Farbe gewählt' }}
                        </span>
                        @if($newColor)
                            <span class="text-[10px] text-[var(--ui-muted)] font-mono tracking-wider">{{ strtoupper($newColor) }}</span>
                        @endif
                    </div>
                </div>

                {{-- Farbpalette Swatches --}}
                <div>
                    <span class="block text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider mb-2">Farbpalette</span>
                    <div class="grid grid-cols-10 gap-1.5">
                        @foreach($colorPresets as $preset)
                            <button
                                type="button"
                                wire:click="selectPresetColor('{{ $preset }}')"
                                class="group relative w-full aspect-square rounded-lg transition-all duration-150 hover:scale-110 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/40 focus:ring-offset-1
                                    {{ $newColor === $preset ? 'ring-2 ring-[var(--ui-primary)] ring-offset-1 scale-110 shadow-md' : 'ring-1 ring-black/10 hover:ring-black/20' }}"
                                style="background-color: {{ $preset }}"
                                title="{{ $preset }}"
                            >
                                @if($newColor === $preset)
                                    <span class="absolute inset-0 flex items-center justify-center">
                                        @svg('heroicon-o-check', 'w-3.5 h-3.5 text-white drop-shadow-sm')
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Custom Color Input --}}
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-medium text-[var(--ui-muted)] uppercase tracking-wider">Eigene Farbe</span>
                    <div class="flex items-center gap-2 flex-1">
                        <input
                            type="color"
                            wire:model.live="newColor"
                            class="w-7 h-7 border border-[var(--ui-border)]/30 rounded-lg cursor-pointer p-0.5 bg-transparent"
                        />
                        <input
                            type="text"
                            wire:model.live="newColor"
                            placeholder="#000000"
                            maxlength="7"
                            class="flex-1 px-2 py-1 text-xs font-mono border border-[var(--ui-border)]/30 rounded-lg bg-[var(--ui-muted-5)]/50 text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]/40 focus:border-[var(--ui-primary)]/40 focus:outline-none focus:ring-0 transition-all"
                        />
                    </div>
                </div>

                {{-- Aktionen --}}
                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="button"
                        wire:click="setColor"
                        class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/90 shadow-sm hover:shadow-md transition-all duration-200"
                    >
                        @svg('heroicon-o-check', 'w-3.5 h-3.5')
                        Farbe setzen
                    </button>
                    @if($contextColor)
                        <button
                            type="button"
                            wire:click="removeColor"
                            class="px-3 py-2 text-xs font-medium text-[var(--ui-danger)] hover:bg-[var(--ui-danger)]/5 border border-[var(--ui-danger)]/20 rounded-lg transition-all duration-200"
                        >
                            Entfernen
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="toggleColorPicker"
                        class="px-3 py-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] rounded-lg transition-all duration-200"
                    >
                        Abbrechen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
