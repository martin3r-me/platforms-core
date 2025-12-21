<div x-data="{ activeTab: $wire.entangle('activeTab') }">
<x-ui-modal size="lg" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                    @svg('heroicon-o-tag', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Tags verwalten</h3>
                @if($contextType && $contextId && $this->contextBreadcrumb)
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        @foreach($this->contextBreadcrumb as $index => $crumb)
                            <div class="flex items-center gap-2">
                                @if($index > 0)
                                    @svg('heroicon-o-chevron-right', 'w-3 h-3 text-[var(--ui-muted)]')
                                @endif
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/40">
                                    <span class="text-[var(--ui-muted)]">{{ $crumb['type'] }}:</span>
                                    <span class="font-semibold">{{ $crumb['label'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[var(--ui-muted)] mt-1">Tags zuordnen und verwalten</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div>
        @if(!$contextType || !$contextId)
            <div class="text-center py-12">
                <p class="text-[var(--ui-muted)]">Kein Kontext ausgewählt.</p>
            </div>
        @else
            <!-- Tabs -->
            <div class="border-b border-[var(--ui-border)]/60 mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button
                        @click="activeTab = 'all'"
                        :class="activeTab === 'all' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'all')"
                    >
                        Alle
                    </button>
                    <button
                        @click="activeTab = 'team'"
                        :class="activeTab === 'team' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'team')"
                    >
                        Team
                    </button>
                    <button
                        @click="activeTab = 'personal'"
                        :class="activeTab === 'personal' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition-colors"
                        wire:click="$set('activeTab', 'personal')"
                    >
                        Persönlich
                    </button>
                </nav>
            </div>

            <!-- Zugeordnete Tags -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">
                    Zugeordnete Tags
                </h4>
                
                <div class="space-y-2">
                    <!-- Team Tags -->
                    @if($activeTab === 'all' || $activeTab === 'team')
                        @forelse($teamTags as $tag)
                            <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-2">
                                    @if($tag['color'])
                                        <div class="w-3 h-3 rounded-full" style="background-color: {{ $tag['color'] }}"></div>
                                    @endif
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $tag['label'] }}</span>
                                    <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-surface)] rounded">Team</span>
                                </div>
                                <button
                                    wire:click="toggleTag({{ $tag['id'] }}, false)"
                                    class="text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 text-sm font-medium"
                                >
                                    Entfernen
                                </button>
                            </div>
                        @empty
                            @if($activeTab === 'team')
                                <p class="text-sm text-[var(--ui-muted)] py-2">Keine Team-Tags zugeordnet.</p>
                            @endif
                        @endforelse
                    @endif

                    <!-- Persönliche Tags -->
                    @if($activeTab === 'all' || $activeTab === 'personal')
                        @forelse($personalTags as $tag)
                            <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <div class="flex items-center gap-2">
                                    @if($tag['color'])
                                        <div class="w-3 h-3 rounded-full" style="background-color: {{ $tag['color'] }}"></div>
                                    @endif
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $tag['label'] }}</span>
                                    <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-surface)] rounded">Persönlich</span>
                                </div>
                                <button
                                    wire:click="toggleTag({{ $tag['id'] }}, true)"
                                    class="text-[var(--ui-danger)] hover:text-[var(--ui-danger)]/80 text-sm font-medium"
                                >
                                    Entfernen
                                </button>
                            </div>
                        @empty
                            @if($activeTab === 'personal')
                                <p class="text-sm text-[var(--ui-muted)] py-2">Keine persönlichen Tags zugeordnet.</p>
                            @endif
                        @endforelse
                    @endif

                    @if(empty($teamTags) && empty($personalTags))
                        <p class="text-sm text-[var(--ui-muted)] py-2">Noch keine Tags zugeordnet.</p>
                    @endif
                </div>
            </div>

            <!-- Verfügbare Tags -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">
                    Verfügbare Tags
                </h4>

                <!-- Suche -->
                <div class="mb-3">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="Tags durchsuchen..."
                        class="w-full px-4 py-2 border border-[var(--ui-border)]/60 rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                    />
                </div>

                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @forelse($availableTags as $tag)
                        <div class="flex items-center justify-between p-3 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)] transition-colors">
                            <div class="flex items-center gap-2">
                                @if($tag['color'])
                                    <div class="w-3 h-3 rounded-full" style="background-color: {{ $tag['color'] }}"></div>
                                @endif
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $tag['label'] }}</span>
                                @if($tag['is_team_tag'])
                                    <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-muted-5)] rounded">Team</span>
                                @else
                                    <span class="text-xs text-[var(--ui-muted)] px-2 py-0.5 bg-[var(--ui-muted-5)] rounded">Global</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    wire:click="toggleTag({{ $tag['id'] }}, false)"
                                    class="text-xs px-3 py-1.5 bg-[var(--ui-primary)] text-white rounded-md hover:bg-[var(--ui-primary)]/90 transition-colors"
                                >
                                    Als Team
                                </button>
                                <button
                                    wire:click="toggleTag({{ $tag['id'] }}, true)"
                                    class="text-xs px-3 py-1.5 bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] rounded-md hover:bg-[var(--ui-muted-10)] transition-colors border border-[var(--ui-border)]/40"
                                >
                                    Persönlich
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--ui-muted)] py-2">
                            @if($searchQuery)
                                Keine Tags gefunden.
                            @else
                                Keine verfügbaren Tags.
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>

            <!-- Neues Tag erstellen -->
            <div class="border-t border-[var(--ui-border)]/60 pt-6">
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">
                    Neues Tag erstellen
                </h4>

                <form wire:submit.prevent="createTag" class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                            Label
                        </label>
                        <input
                            type="text"
                            wire:model="newTagLabel"
                            placeholder="z.B. Wichtig, Dringend, Review"
                            class="w-full px-4 py-2 border border-[var(--ui-border)]/60 rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                        />
                        @error('newTagLabel')
                            <p class="mt-1 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">
                            Farbe (optional)
                        </label>
                        <input
                            type="color"
                            wire:model="newTagColor"
                            class="w-full h-10 border border-[var(--ui-border)]/60 rounded-lg cursor-pointer"
                        />
                        @error('newTagColor')
                            <p class="mt-1 text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            wire:model="newTagIsPersonal"
                            id="newTagIsPersonal"
                            class="w-4 h-4 text-[var(--ui-primary)] border-[var(--ui-border)] rounded focus:ring-[var(--ui-primary)]"
                        />
                        <label for="newTagIsPersonal" class="text-sm text-[var(--ui-secondary)]">
                            Als persönliches Tag erstellen
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="w-full px-4 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/90 transition-colors font-medium"
                    >
                        Tag erstellen und zuordnen
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-ui-modal>
</div>

