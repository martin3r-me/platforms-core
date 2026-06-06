<x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            @svg('heroicon-o-sun', 'w-5 h-5 text-[var(--ui-primary)]')
            <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Täglicher Check-in</h2>
            <span class="ml-auto text-xs text-[var(--ui-muted)]">
                {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
            </span>
        </div>
    </x-slot>

    {{-- Tabs --}}
    <div class="flex items-center gap-1 mb-4 border-b border-[var(--ui-border)]/60">
        <button type="button"
            wire:click="setTab('today')"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 transition -mb-px
                {{ $activeTab === 'today'
                    ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]'
                    : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}">
            @svg('heroicon-o-sun', 'w-4 h-4')
            Heute
        </button>
        <button type="button"
            wire:click="setTab('trends')"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 transition -mb-px
                {{ $activeTab === 'trends'
                    ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]'
                    : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}">
            @svg('heroicon-o-chart-bar', 'w-4 h-4')
            Trends
            <span class="text-[10px] text-[var(--ui-muted)]">30 Tage</span>
        </button>
    </div>

    @if($activeTab === 'trends')
        @include('platform::livewire.partials.modal-checkin-trends')
    @else

    {{-- 7-Tage-Strip --}}
    <div class="flex items-center gap-1 mb-4">
        <button type="button" wire:click="previousWeek"
            class="inline-flex items-center justify-center w-9 h-12 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition"
            title="Vorherige Woche">
            @svg('heroicon-o-chevron-left', 'w-4 h-4')
        </button>

        <div class="flex-1 grid grid-cols-7 gap-1">
            @foreach($this->visibleDays as $day)
                <button type="button"
                    wire:click="selectDate('{{ $day['date'] }}')"
                    @disabled($day['is_future'])
                    class="relative flex flex-col items-center justify-center h-12 rounded-md text-xs transition
                        {{ $day['is_selected']
                            ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] font-semibold shadow-sm'
                            : ($day['is_future']
                                ? 'text-[var(--ui-muted)] opacity-40 cursor-not-allowed'
                                : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]') }}
                        {{ $day['is_today'] && !$day['is_selected'] ? 'ring-1 ring-[var(--ui-primary)]/40' : '' }}">
                    <span class="text-[10px] uppercase tracking-wider {{ $day['is_selected'] ? 'opacity-90' : 'text-[var(--ui-muted)]' }}">{{ $day['weekday'] }}</span>
                    <span class="text-sm font-medium leading-tight">{{ $day['day'] }}</span>
                    @if($day['has_checkin'] && !$day['is_selected'])
                        <span class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-[var(--ui-primary)]"></span>
                    @endif
                </button>
            @endforeach
        </div>

        <button type="button" wire:click="nextWeek"
            class="inline-flex items-center justify-center w-9 h-12 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition"
            title="Nächste Woche">
            @svg('heroicon-o-chevron-right', 'w-4 h-4')
        </button>

        <button type="button" wire:click="goToToday"
            class="inline-flex items-center gap-1 h-12 px-3 ml-1 rounded-md text-xs font-medium text-[var(--ui-secondary)] border border-[var(--ui-border)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-primary)] transition"
            title="Heute">
            @svg('heroicon-o-calendar-days', 'w-4 h-4')
            Heute
        </button>
    </div>

    {{-- 2 Spalten: Form | To-Dos + Notes --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Spalte 1: Check-in Formular --}}
        <div class="space-y-4">
            {{-- Tagesziel + Kategorie + Mood + Energie --}}
            <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-5 space-y-4">

                {{-- Tagesziel --}}
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-[var(--ui-secondary)] mb-2">
                        @svg('heroicon-o-flag', 'w-4 h-4 text-[var(--ui-primary)]')
                        Wichtigstes Ziel für heute
                    </label>
                    <textarea
                        wire:model.live.debounce.500ms="checkinData.daily_goal"
                        placeholder="Was ist heute wichtig?"
                        class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent resize-none text-sm"
                        rows="2"
                    ></textarea>
                </div>

                {{-- Zielkategorie --}}
                <div>
                    <x-ui-input-select
                        name="checkinData.goal_category"
                        label="Kategorie"
                        wire:model.live="checkinData.goal_category"
                        :options="$this->getGoalCategoryOptions()"
                        placeholder="Kategorie wählen (optional)"
                        :errorKey="'checkinData.goal_category'"
                    />
                </div>

                {{-- Stimmung --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-[var(--ui-secondary)]">
                            @svg('heroicon-o-face-smile', 'w-4 h-4 text-[var(--ui-primary)]')
                            Stimmung
                        </label>
                        @if(isset($checkinData['mood_score']) && $checkinData['mood_score'] !== null)
                            <span class="text-[11px] text-[var(--ui-muted)]">{{ \Platform\Core\Models\Checkin::getMoodScoreOptions()[$checkinData['mood_score']] ?? '' }}</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-5 gap-1">
                        @foreach([0, 1, 2, 3, 4] as $score)
                            <button type="button"
                                wire:click="$set('checkinData.mood_score', {{ $score }})"
                                class="py-2 rounded-md text-sm font-medium transition
                                    {{ isset($checkinData['mood_score']) && (int) $checkinData['mood_score'] === $score
                                        ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] shadow-sm'
                                        : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-primary)]/10 hover:text-[var(--ui-primary)]' }}">
                                {{ $score }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Energie --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="flex items-center gap-2 text-sm font-medium text-[var(--ui-secondary)]">
                            @svg('heroicon-o-bolt', 'w-4 h-4 text-[var(--ui-primary)]')
                            Energie
                        </label>
                        @if(isset($checkinData['energy_score']) && $checkinData['energy_score'] !== null)
                            <span class="text-[11px] text-[var(--ui-muted)]">{{ \Platform\Core\Models\Checkin::getEnergyScoreOptions()[$checkinData['energy_score']] ?? '' }}</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-5 gap-1">
                        @foreach([0, 1, 2, 3, 4] as $score)
                            <button type="button"
                                wire:click="$set('checkinData.energy_score', {{ $score }})"
                                class="py-2 rounded-md text-sm font-medium transition
                                    {{ isset($checkinData['energy_score']) && (int) $checkinData['energy_score'] === $score
                                        ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] shadow-sm'
                                        : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] hover:bg-[var(--ui-primary)]/10 hover:text-[var(--ui-primary)]' }}">
                                {{ $score }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Selbstreflexion-Checkboxen --}}
            <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-5">
                <div class="grid grid-cols-2 gap-2">
                    @php
                        $habits = [
                            'hydrated' => ['Getrunken', 'heroicon-o-beaker'],
                            'exercised' => ['Bewegt', 'heroicon-o-fire'],
                            'slept_well' => ['Geschlafen', 'heroicon-o-moon'],
                            'focused_work' => ['Fokussiert', 'heroicon-o-eye'],
                            'social_time' => ['Sozial', 'heroicon-o-users'],
                            'needs_support' => ['Brauche Hilfe', 'heroicon-o-question-mark-circle'],
                        ];
                    @endphp
                    @foreach($habits as $key => [$label, $icon])
                        <label class="flex items-center gap-2.5 cursor-pointer p-2 rounded-md hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox"
                                wire:model.live="checkinData.{{ $key }}"
                                class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <x-dynamic-component :component="$icon" class="w-4 h-4 text-[var(--ui-primary)]" />
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Spalte 2: To-Dos + Notes --}}
        <div class="space-y-4">

            {{-- To-Do Liste --}}
            <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-5">
                <div class="flex items-center gap-2 mb-3">
                    @svg('heroicon-o-clipboard-document-list', 'w-4 h-4 text-[var(--ui-primary)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Tagesaufgaben</h3>
                </div>

                {{-- Neue Aufgabe --}}
                <div class="flex items-center gap-2 mb-3" x-data="{ todoTitle: '' }" @keydown.enter.stop>
                    <x-ui-input-text
                        name="newTodoTitle"
                        x-model="todoTitle"
                        placeholder="Neue Aufgabe…"
                        class="flex-1"
                        @keydown.enter="$wire.set('newTodoTitle', todoTitle); $wire.addTodo(); todoTitle = '';"
                    />
                    <x-ui-button wire:click="addTodo" variant="primary" iconOnly>
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </x-ui-button>
                </div>

                {{-- Liste --}}
                <div class="space-y-1.5 max-h-72 overflow-y-auto">
                    @forelse($todos as $todo)
                        <div class="group flex items-center gap-2 p-2 bg-[var(--ui-muted-5)] rounded-md hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox"
                                wire:click="toggleTodo({{ $todo['id'] }})"
                                {{ $todo['done'] ? 'checked' : '' }}
                                class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <span wire:click="toggleTodo({{ $todo['id'] }})"
                                class="flex-1 text-sm cursor-pointer {{ $todo['done'] ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }} hover:text-[var(--ui-primary)] transition-colors">
                                {{ $todo['title'] }}
                            </span>
                            <button wire:click="postponeTodo({{ $todo['id'] }})"
                                class="opacity-0 group-hover:opacity-100 p-1 hover:bg-[var(--ui-warning)] hover:text-[var(--ui-on-warning)] rounded transition-all"
                                title="Auf morgen verschieben">
                                @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')
                            </button>
                            <button wire:click="deleteTodo({{ $todo['id'] }})"
                                class="opacity-0 group-hover:opacity-100 p-1 hover:bg-[var(--ui-danger)] hover:text-[var(--ui-on-danger)] rounded transition-all"
                                title="Löschen">
                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-6 text-xs text-[var(--ui-muted)]">
                            Noch keine Aufgaben für diesen Tag.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Notizen --}}
            <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-5">
                <div class="flex items-center gap-2 mb-3">
                    @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-primary)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)] m-0">Notizen</h3>
                </div>
                <textarea
                    wire:model.live.debounce.500ms="checkinData.notes"
                    placeholder="Weitere Gedanken…"
                    class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent resize-none text-sm"
                    rows="3"
                ></textarea>
            </div>
        </div>
    </div>
    @endif

    <x-slot name="footer">
        <div class="flex justify-between gap-4">
            <x-ui-button variant="secondary-outline" wire:click="$set('modalShow', false)">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    Schließen
                </div>
            </x-ui-button>
            @if($activeTab === 'today')
                <x-ui-button variant="primary" wire:click="save">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        Check-in speichern
                    </div>
                </x-ui-button>
            @endif
        </div>
    </x-slot>
</x-ui-modal>
