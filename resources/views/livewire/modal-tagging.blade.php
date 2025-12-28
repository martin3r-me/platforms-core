<div x-data="{ activeTab: $wire.entangle('activeTab') }">
<x-ui-modal size="lg" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-[var(--ui-primary-5)] flex items-center justify-center">
                    @svg('heroicon-o-hashtag', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Tags & Farben</h3>
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
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Tags und Farben zuordnen</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div>
        <!-- Tabs -->
        <div class="border-b border-[var(--ui-border)]/40 mb-6">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                @if($contextType && $contextId)
                    <button
                        @click="activeTab = 'tags'"
                        :class="activeTab === 'tags' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'tags')"
                    >
                        Tags
                    </button>
                    <button
                        @click="activeTab = 'color'"
                        :class="activeTab === 'color' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'color')"
                    >
                        Farbe
                    </button>
                @endif
                <button
                    @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                    class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                    wire:click="$set('activeTab', 'overview')"
                >
                    Übersicht
                </button>
            </nav>
        </div>

        @if($activeTab === 'overview')
            <!-- Übersicht: Alle Tags -->
            <div>
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                    Alle Tags
                </h4>

                <div class="overflow-hidden border border-[var(--ui-border)]/40">
                    <table class="min-w-full divide-y divide-[var(--ui-border)]/40">
                        <thead class="bg-[var(--ui-muted-5)]">
                            <tr>
                                <th scope="col" class="py-3 pl-6 pr-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Tag
                                </th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Typ
                                </th>
                                <th scope="col" class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Gesamt
                                </th>
                                <th scope="col" class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Team
                                </th>
                                <th scope="col" class="px-3 py-3 text-center text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Persönlich
                                </th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Erstellt
                                </th>
                                <th scope="col" class="px-3 py-3 text-right text-xs font-bold uppercase tracking-wider text-[var(--ui-secondary)]">
                                    Aktionen
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
                            @forelse($allTags as $tag)
                                <tr class="hover:bg-[var(--ui-muted-5)]/50 transition-colors">
                                    <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                        <div class="flex items-center gap-2">
                                            @if($tag['color'])
                                                <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></div>
                                            @endif
                                            <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $tag['label'] }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4">
                                        @if($tag['is_team_tag'])
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-[var(--ui-primary-10)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">
                                                Team
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40">
                                                Global
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center">
                                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $tag['total_count'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center">
                                        <span class="text-sm text-[var(--ui-muted)]">{{ $tag['team_count'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center">
                                        <span class="text-sm text-[var(--ui-muted)]">{{ $tag['personal_count'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            <div>{{ $tag['created_at'] }}</div>
                                            <div class="text-[var(--ui-muted)]/70">{{ $tag['created_by'] }}</div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-right">
                                        @if($tag['total_count'] === 0)
                                            <button
                                                wire:click="deleteTag({{ $tag['id'] }})"
                                                wire:confirm="Tag wirklich löschen?"
                                                class="text-xs px-3 py-1.5 text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] transition-colors"
                                            >
                                                Löschen
                                            </button>
                                        @else
                                            <span class="text-xs text-[var(--ui-muted)]">In Verwendung</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center">
                                        <p class="text-sm text-[var(--ui-muted)]">Noch keine Tags vorhanden.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($activeTab === 'color' && $contextType && $contextId)
            <!-- Farbe Tab -->
            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">
                        Farbe zuordnen
                    </h4>
                    
                    @if($contextColor)
                        <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 border border-[var(--ui-border)]/40" style="background-color: {{ $contextColor }}"></div>
                                    <div>
                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">Aktuelle Farbe</div>
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $contextColor }}</div>
                                    </div>
                                </div>
                                <button
                                    wire:click="removeColor"
                                    class="text-sm px-4 py-2 text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] transition-colors"
                                >
                                    Entfernen
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                                    Farbe auswählen
                                </label>
                                <input
                                    type="color"
                                    wire:model.live="newContextColor"
                                    class="w-full h-16 border border-[var(--ui-border)]/40 cursor-pointer"
                                />
                            </div>
                            @if($newContextColor)
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 border border-[var(--ui-border)]/40" style="background-color: {{ $newContextColor }}"></div>
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">Vorschau</div>
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $newContextColor }}</div>
                                    </div>
                                    <button
                                        wire:click="setColor"
                                        class="px-4 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors text-sm font-medium"
                                    >
                                        Farbe setzen
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

        @elseif($activeTab === 'tags' && $contextType && $contextId)
            <!-- Tags Tab -->
            <div class="space-y-6" x-data="{ showSuggestions: @entangle('showTagSuggestions') }">
                <!-- Tag hinzufügen - Autocomplete -->
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">
                        Tag hinzufügen
                    </h4>
                    
                    <div class="relative" x-on:click.away="showSuggestions = false">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="tagInput"
                                    @focus="showSuggestions = $wire.showTagSuggestions"
                                    placeholder="Tag suchen oder neu erstellen..."
                                    class="w-full px-4 py-2 border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                                />
                                
                                <!-- Vorschläge Dropdown -->
                                <div 
                                    x-show="showSuggestions && ($wire.tagSuggestions.length > 0 || $wire.tagInput.length >= 2)"
                                    x-transition
                                    class="absolute z-10 w-full mt-1 bg-[var(--ui-surface)] border border-[var(--ui-border)]/40 shadow-lg max-h-64 overflow-y-auto"
                                >
                                    @if(count($tagSuggestions) > 0)
                                        @foreach($tagSuggestions as $suggestion)
                                            <button
                                                type="button"
                                                wire:click="addTagFromSuggestion({{ $suggestion['id'] }}, false)"
                                                @click="showSuggestions = false"
                                                class="w-full flex items-center justify-between p-3 hover:bg-[var(--ui-muted-5)] transition-colors text-left"
                                            >
                                                <div class="flex items-center gap-2">
                                                    @if($suggestion['color'])
                                                        <div class="w-3 h-3 rounded-full" style="background-color: {{ $suggestion['color'] }}"></div>
                                                    @endif
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $suggestion['label'] }}</span>
                                                    @if($suggestion['is_team_tag'])
                                                        <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-muted-5)]">Team</span>
                                                    @else
                                                        <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-muted-5)]">Global</span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        wire:click.stop="addTagFromSuggestion({{ $suggestion['id'] }}, false)"
                                                        @click="showSuggestions = false"
                                                        class="text-xs px-2 py-1 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors"
                                                    >
                                                        Team
                                                    </button>
                                                    <button
                                                        type="button"
                                                        wire:click.stop="addTagFromSuggestion({{ $suggestion['id'] }}, true)"
                                                        @click="showSuggestions = false"
                                                        class="text-xs px-2 py-1 bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-10)] transition-colors border border-[var(--ui-border)]/40"
                                                    >
                                                        Persönlich
                                                    </button>
                                                </div>
                                            </button>
                                        @endforeach
                                    @endif
                                    
                                    @if(strlen($tagInput) >= 2 && count($tagSuggestions) === 0)
                                        <div class="p-3 border-t border-[var(--ui-border)]/40">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Neues Tag erstellen:</span>
                                                <div class="flex items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        wire:model="newTagIsPersonal"
                                                        id="newTagIsPersonalInline"
                                                        class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] focus:ring-[var(--ui-primary)]"
                                                    />
                                                    <label for="newTagIsPersonalInline" class="text-xs text-[var(--ui-muted)]">
                                                        Persönlich
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="color"
                                                    wire:model="newTagColor"
                                                    class="h-8 w-16 border border-[var(--ui-border)]/40 cursor-pointer"
                                                />
                                                <button
                                                    type="button"
                                                    wire:click="createAndAddTag"
                                                    @click="showSuggestions = false"
                                                    class="flex-1 px-3 py-2 bg-[var(--ui-primary)] text-white hover:bg-[var(--ui-primary)]/90 transition-colors text-sm font-medium"
                                                >
                                                    "{{ $tagInput }}" erstellen
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Zugeordnete Tags -->
                <div>
                    <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">
                        Zugeordnete Tags
                    </h4>
                    
                    <div class="space-y-2">
                        @php
                            $tagFilter = $tagFilter ?? 'all';
                        @endphp

                        <!-- Team Tags -->
                        @if($tagFilter === 'all' || $tagFilter === 'team')
                            @forelse($teamTags as $tag)
                                <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                    <div class="flex items-center gap-2">
                                        @if($tag['color'])
                                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $tag['color'] }}"></div>
                                        @endif
                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $tag['label'] }}</span>
                                        <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-surface)]">Team</span>
                                    </div>
                                    <button
                                        wire:click="toggleTag({{ $tag['id'] }}, false)"
                                        class="text-sm text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] px-3 py-1 transition-colors"
                                    >
                                        Entfernen
                                    </button>
                                </div>
                            @empty
                                @if($tagFilter === 'team')
                                    <p class="text-sm text-[var(--ui-muted)] py-3 text-center">Keine Team-Tags zugeordnet.</p>
                                @endif
                            @endforelse
                        @endif

                        <!-- Persönliche Tags -->
                        @if($tagFilter === 'all' || $tagFilter === 'personal')
                            @forelse($personalTags as $tag)
                                <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                                    <div class="flex items-center gap-2">
                                        @if($tag['color'])
                                            <div class="w-3 h-3 rounded-full" style="background-color: {{ $tag['color'] }}"></div>
                                        @endif
                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $tag['label'] }}</span>
                                        <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-surface)]">Persönlich</span>
                                    </div>
                                    <button
                                        wire:click="toggleTag({{ $tag['id'] }}, true)"
                                        class="text-sm text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 hover:bg-[var(--ui-danger-5)] px-3 py-1 transition-colors"
                                    >
                                        Entfernen
                                    </button>
                                </div>
                            @empty
                                @if($tagFilter === 'personal')
                                    <p class="text-sm text-[var(--ui-muted)] py-3 text-center">Keine persönlichen Tags zugeordnet.</p>
                                @endif
                            @endforelse
                        @endif

                        @if(empty($teamTags) && empty($personalTags))
                            <p class="text-sm text-[var(--ui-muted)] py-3 text-center">Noch keine Tags zugeordnet.</p>
                        @endif
                    </div>
                </div>

                <!-- Unter-Tabs für Filter -->
                <div class="border-t border-[var(--ui-border)]/40 pt-4">
                    <div class="border-b border-[var(--ui-border)]/40">
                        <nav class="-mb-px flex space-x-6">
                            <button
                                @click="$wire.set('tagFilter', 'all')"
                                :class="($wire.tagFilter ?? 'all') === 'all' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                                class="whitespace-nowrap border-b-2 py-2 px-1 text-xs font-medium transition-colors"
                                wire:click="$set('tagFilter', 'all')"
                            >
                                Alle
                            </button>
                            <button
                                @click="$wire.set('tagFilter', 'team')"
                                :class="($wire.tagFilter ?? 'all') === 'team' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                                class="whitespace-nowrap border-b-2 py-2 px-1 text-xs font-medium transition-colors"
                                wire:click="$set('tagFilter', 'team')"
                            >
                                Team
                            </button>
                            <button
                                @click="$wire.set('tagFilter', 'personal')"
                                :class="($wire.tagFilter ?? 'all') === 'personal' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                                class="whitespace-nowrap border-b-2 py-2 px-1 text-xs font-medium transition-colors"
                                wire:click="$set('tagFilter', 'personal')"
                            >
                                Persönlich
                            </button>
                        </nav>
                    </div>
                </div>
            </div>

        @elseif(!$contextType || !$contextId)
            <div class="text-center py-12">
                <p class="text-[var(--ui-muted)]">Kein Kontext ausgewählt.</p>
                <p class="text-sm text-[var(--ui-muted)] mt-2">Wählen Sie einen Kontext aus, um Tags und Farben zu verwalten.</p>
            </div>
        @endif
    </div>
</x-ui-modal>
</div>
