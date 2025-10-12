    <x-ui-modal size="xl" wire:model="modalShow" :escClosable="false">
        <x-slot name="header">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Täglicher Check-in</h2>
                    <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">SELBSTREFLEXION</span>
                </div>
                <div class="text-right">
                    <div class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">
                        {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('DD. MMMM YYYY') }}
                    </div>
                </div>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full">
            {{-- Kalender (1/3) --}}
            <div class="order-3 lg:order-1 h-full overflow-y-auto">
                <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-6">
                        @svg('heroicon-o-calendar-days', 'w-6 h-6 text-[var(--ui-primary)]')
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Kalender</h3>
                    </div>
                
                {{-- Monats-Navigation --}}
                <div class="flex items-center justify-between mb-6">
                    <button wire:click="previousMonth" class="group p-2 hover:bg-[var(--ui-primary)]/10 rounded-lg transition-all duration-200 hover:scale-105">
                        @svg('heroicon-o-chevron-left', 'w-5 h-5 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)]')
                    </button>
                    <div class="text-center">
                        <h4 class="text-lg font-semibold text-[var(--ui-secondary)]">
                            {{ \Carbon\Carbon::create($currentYear, $currentMonth, 1)->locale('de')->isoFormat('MMMM YYYY') }}
                        </h4>
                        <p class="text-xs text-[var(--ui-muted)] mt-1">
                            {{ count($checkins) }} Check-ins diesen Monat
                        </p>
                    </div>
                    <button wire:click="nextMonth" class="group p-2 hover:bg-[var(--ui-primary)]/10 rounded-lg transition-all duration-200 hover:scale-105">
                        @svg('heroicon-o-chevron-right', 'w-5 h-5 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)]')
                    </button>
                </div>

                {{-- Heute-Button --}}
                <div class="mb-4">
                    <x-ui-button 
                        wire:click="goToToday()" 
                        variant="secondary-outline" 
                        size="sm" 
                        class="w-full"
                    >
                        <div class="flex items-center gap-2">
                            @svg('heroicon-o-calendar-days', 'w-4 h-4')
                            Heute ({{ now()->format('d.m.') }})
                        </div>
                    </x-ui-button>
                </div>

                {{-- Kalender-Grid --}}
                <div class="grid grid-cols-7 gap-1 mb-3">
                    @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                        <div class="text-center text-xs font-semibold text-[var(--ui-muted)] py-2 uppercase tracking-wide">{{ $day }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-1">
                    @php
                        $startOfMonth = \Carbon\Carbon::create($currentYear, $currentMonth, 1);
                        $endOfMonth = $startOfMonth->copy()->endOfMonth();
                        $startOfWeek = $startOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                        $endOfWeek = $endOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                        $currentDate = $startOfWeek->copy();
                    @endphp

                    @while($currentDate->lte($endOfWeek))
                        @php
                            $isCurrentMonth = $currentDate->month === $currentMonth;
                            $isToday = $currentDate->isToday();
                            $isSelected = $currentDate->format('Y-m-d') === $selectedDate;
                            $hasCheckin = in_array($currentDate->format('Y-m-d'), $checkins);
                            $dateString = $currentDate->format('Y-m-d');
                        @endphp

                            <button
                                wire:click="selectDate('{{ $dateString }}')"
                                class="group relative p-2 text-sm rounded-lg transition-colors duration-200
                                    {{ $isCurrentMonth ? 'text-[var(--ui-secondary)]' : 'text-[var(--ui-muted)]' }}
                                    {{ $isToday ? 'bg-gradient-to-br from-[var(--ui-primary)] to-[var(--ui-primary)]/80 text-[var(--ui-on-primary)] font-semibold shadow-md' : '' }}
                                    {{ $isSelected && !$isToday ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold border border-[var(--ui-primary)]/20' : '' }}
                                    {{ !$isSelected && !$isToday ? 'hover:bg-[var(--ui-primary)]/5 hover:text-[var(--ui-primary)]' : '' }}
                                "
                            >
                            <span class="relative z-10">{{ $currentDate->day }}</span>
                            @if($hasCheckin)
                                <div class="absolute bottom-1 left-1/2 transform -translate-x-1/2 w-1.5 h-1.5 bg-[var(--ui-primary)] rounded-full shadow-sm"></div>
                            @endif
                            @if($isToday)
                                <div class="absolute inset-0 bg-gradient-to-br from-[var(--ui-primary)]/20 to-transparent rounded-lg"></div>
                            @endif
                        </button>

                        @php $currentDate->addDay(); @endphp
                    @endwhile
                </div>


            </div>
        </div>

        {{-- Check-in Formular (1/3) --}}
        <div class="order-1 lg:order-2">
            <div class="space-y-4 h-full overflow-y-auto">
                {{-- Datum und Grunddaten --}}
                <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        @svg('heroicon-o-flag', 'w-5 h-5 text-[var(--ui-primary)]')
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">
                            {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
                        </h3>
                    </div>

                    <div class="space-y-4">
                        {{-- Tagesziel --}}
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                                @svg('heroicon-o-flag', 'w-4 h-4 text-[var(--ui-primary)]')
                                Wichtigstes Ziel für heute
                            </label>
                            <textarea 
                                wire:model.live.debounce.500ms="checkinData.daily_goal"
                                placeholder="Was ist dein wichtigstes Ziel heute?"
                                class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent resize-none"
                                rows="2"
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            {{-- Stimmung --}}
                            <div>
                                <x-ui-input-select
                                    name="checkinData.mood"
                                    label="Stimmung"
                                    wire:model.live="checkinData.mood"
                                    :options="$this->getMoodOptions()"
                                    placeholder="Stimmung wählen"
                                    :errorKey="'checkinData.mood'"
                                >
                                    <x-slot name="label">
                                        <div class="flex items-center gap-2">
                                            @svg('heroicon-o-face-smile', 'w-4 h-4 text-[var(--ui-primary)]')
                                            Stimmung
                                        </div>
                                    </x-slot>
                                </x-ui-input-select>
                            </div>

                            {{-- Glücksskala --}}
                            <div>
                                <x-ui-input-select
                                    name="checkinData.happiness"
                                    label="Glück (1-10)"
                                    wire:model.live="checkinData.happiness"
                                    :options="$this->getHappinessOptions()"
                                    placeholder="Glückslevel wählen"
                                    :errorKey="'checkinData.happiness'"
                                >
                                    <x-slot name="label">
                                        <div class="flex items-center gap-2">
                                            @svg('heroicon-o-heart', 'w-4 h-4 text-[var(--ui-primary)]')
                                            Glück (1-10)
                                        </div>
                                    </x-slot>
                                </x-ui-input-select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Selbstreflexion --}}
                <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        @svg('heroicon-o-sparkles', 'w-5 h-5 text-[var(--ui-primary)]')
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Selbstreflexion</h3>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <label class="group flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox" wire:model.live="checkinData.hydrated" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-beaker', 'w-4 h-4 text-[var(--ui-primary)]')
                                <span class="text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)]">Genug getrunken</span>
                            </div>
                        </label>
                        
                        <label class="group flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox" wire:model.live="checkinData.exercised" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-fire', 'w-4 h-4 text-[var(--ui-primary)]')
                                <span class="text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)]">Sich bewegt</span>
                            </div>
                        </label>
                        
                        <label class="group flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox" wire:model.live="checkinData.slept_well" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-moon', 'w-4 h-4 text-[var(--ui-primary)]')
                                <span class="text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)]">Gut geschlafen</span>
                            </div>
                        </label>
                        
                        <label class="group flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox" wire:model.live="checkinData.focused_work" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-eye', 'w-4 h-4 text-[var(--ui-primary)]')
                                <span class="text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)]">Fokussiert gearbeitet</span>
                            </div>
                        </label>
                        
                        <label class="group flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox" wire:model.live="checkinData.social_time" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-users', 'w-4 h-4 text-[var(--ui-primary)]')
                                <span class="text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)]">Zeit mit anderen</span>
                            </div>
                        </label>
                        
                        <label class="group flex items-center gap-3 cursor-pointer p-3 rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                            <input type="checkbox" wire:model.live="checkinData.needs_support" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20">
                            <div class="flex items-center gap-2">
                                @svg('heroicon-o-question-mark-circle', 'w-4 h-4 text-[var(--ui-primary)]')
                                <span class="text-sm text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)]">Unterstützung nötig</span>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Pomodoro Timer --}}
                <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        @svg('heroicon-o-clock', 'w-5 h-5 text-[var(--ui-primary)]')
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Pomodoro Timer</h3>
                        @if($pomodoroStats['active_session'])
                            <div class="ml-auto flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full border-2 border-[var(--ui-primary)] overflow-hidden">
                                    <div 
                                        class="w-full h-full bg-[var(--ui-primary)] transition-all duration-1000"
                                        style="transform: translateY({{ 100 - $pomodoroStats['active_session']['progress_percentage'] }}%)"
                                    ></div>
                                </div>
                                <span class="text-sm text-[var(--ui-primary)] font-medium">
                                    {{ round($pomodoroStats['active_session']['progress_percentage']) }}%
                                </span>
                            </div>
                        @endif
                    </div>

        <div x-data="pomodoroTimer()" x-init="init()" class="text-center" 
             x-pomodoro-session='@json($pomodoroStats["active_session"])'
             x-pomodoro-stats='@json($pomodoroStats)'>
                        {{-- Timer Display --}}
                        <div class="mb-6">
                            <div class="text-4xl font-bold text-[var(--ui-primary)] mb-2">
                                <span x-text="formatTime(timeLeft)"></span> Min
                            </div>
                            <div class="text-sm text-[var(--ui-muted)]">Fokuszeit</div>
                        </div>

                        {{-- Progress Ring --}}
                        <div class="relative w-32 h-32 mx-auto mb-6">
                            <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="45" stroke="var(--ui-border)" stroke-width="8" fill="none"/>
                                <circle 
                                    cx="50" 
                                    cy="50" 
                                    r="45" 
                                    stroke="var(--ui-primary)" 
                                    stroke-width="8" 
                                    fill="none"
                                    stroke-dasharray="283"
                                    :stroke-dashoffset="283 - (283 * progress)"
                                    class="transition-all duration-1000 ease-linear"
                                />
                            </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-[var(--ui-secondary)]" x-text="formatTime(timeLeft)"></div>
                                        <div class="text-xs text-[var(--ui-muted)]">Min</div>
                                    </div>
                                </div>
                        </div>

                        {{-- Time Selection --}}
                        <div class="flex items-center justify-center gap-2 mb-4">
                            <button 
                                @click="setTime(5)" 
                                class="px-4 py-2 text-sm bg-[var(--ui-muted)] text-[var(--ui-secondary)] rounded-lg hover:bg-[var(--ui-primary)] hover:text-white transition-all duration-200 font-medium"
                            >
                                5 Min
                            </button>
                            <button 
                                @click="setTime(15)" 
                                class="px-4 py-2 text-sm bg-[var(--ui-muted)] text-[var(--ui-secondary)] rounded-lg hover:bg-[var(--ui-primary)] hover:text-white transition-all duration-200 font-medium"
                            >
                                15 Min
                            </button>
                            <button 
                                @click="setTime(25)" 
                                class="px-4 py-2 text-sm bg-[var(--ui-muted)] text-[var(--ui-secondary)] rounded-lg hover:bg-[var(--ui-primary)] hover:text-white transition-all duration-200 font-medium"
                            >
                                25 Min
                            </button>
                            <button 
                                @click="setTime(45)" 
                                class="px-4 py-2 text-sm bg-[var(--ui-muted)] text-[var(--ui-secondary)] rounded-lg hover:bg-[var(--ui-primary)] hover:text-white transition-all duration-200 font-medium"
                            >
                                45 Min
                            </button>
                            <button 
                                @click="setTime(60)" 
                                class="px-4 py-2 text-sm bg-[var(--ui-muted)] text-[var(--ui-secondary)] rounded-lg hover:bg-[var(--ui-primary)] hover:text-white transition-all duration-200 font-medium"
                            >
                                60 Min
                            </button>
                        </div>

                        {{-- Controls --}}
                        <div class="flex items-center justify-center gap-3 mb-4">
                            <button 
                                @click="startTimer()" 
                                x-show="!isRunning"
                                class="px-6 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/90 transition-colors"
                            >
                                Start
                            </button>
                            <button 
                                @click="pauseTimer()" 
                                x-show="isRunning"
                                wire:click="stopPomodoro()"
                                class="px-6 py-2 bg-[var(--ui-secondary)] text-white rounded-lg hover:bg-[var(--ui-secondary)]/90 transition-colors"
                            >
                                Pause
                            </button>
                            <button 
                                @click="resetTimer()" 
                                wire:click="stopActivePomodoro()"
                                class="px-6 py-2 bg-[var(--ui-muted)] text-[var(--ui-secondary)] rounded-lg hover:bg-[var(--ui-muted)]/80 transition-colors"
                            >
                                Reset
                            </button>
                        </div>

                        {{-- Session Info --}}
                        <div class="text-sm text-[var(--ui-muted)]">
                            <div>Pomodoros heute: <span x-text="pomodoroCount"></span></div>
                            <div>Session: <span x-text="sessionCount"></span></div>
                        </div>

                        {{-- Clear Data Button --}}
                        <div class="mt-4 pt-4 border-t border-[var(--ui-border)]">
                            <button 
                                wire:click="clearPomodoroData()" 
                                class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                            >
                                Daten löschen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Aufgaben (1/3) --}}
        <div class="order-2 lg:order-3">
            <div class="h-full overflow-y-auto">
                {{-- To-Do Liste --}}
                <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        @svg('heroicon-o-clipboard-document-list', 'w-5 h-5 text-[var(--ui-primary)]')
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Tagesaufgaben</h3>
                    </div>

                    {{-- Neue Aufgabe hinzufügen --}}
                    <div class="flex items-center gap-2 mb-4" x-data="{ todoTitle: '' }" @keydown.enter.stop>
                        <x-ui-input-text
                            name="newTodoTitle"
                            x-model="todoTitle"
                            placeholder="Neue Aufgabe hinzufügen..."
                            class="flex-1"
                            @keydown.enter="$wire.set('newTodoTitle', todoTitle); $wire.addTodo(); todoTitle = '';"
                        />
                        <x-ui-button wire:click="addTodo" variant="primary" iconOnly>
                            @svg('heroicon-o-plus', 'w-4 h-4')
                        </x-ui-button>
                    </div>

                    {{-- To-Do Liste --}}
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @forelse($todos as $todo)
                            <div class="group flex items-center gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg hover:bg-[var(--ui-primary)]/5 transition-colors">
                                <input
                                    type="checkbox"
                                    wire:click="toggleTodo({{ $todo['id'] }})"
                                    {{ $todo['done'] ? 'checked' : '' }}
                                    class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)] focus:ring-2 focus:ring-[var(--ui-primary)]/20"
                                >
                                <span
                                    wire:click="toggleTodo({{ $todo['id'] }})"
                                    class="flex-1 text-sm cursor-pointer {{ $todo['done'] ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }} hover:text-[var(--ui-primary)] transition-colors"
                                >
                                    {{ $todo['title'] }}
                                </span>
                                <button
                                    wire:click="deleteTodo({{ $todo['id'] }})"
                                    class="opacity-0 group-hover:opacity-100 p-1 hover:bg-[var(--ui-danger)] hover:text-[var(--ui-on-danger)] rounded transition-all duration-200"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <div class="text-center py-6">
                                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                                    @svg('heroicon-o-clipboard-document-list', 'w-6 h-6 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm text-[var(--ui-muted)]">Noch keine Aufgaben für heute</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Notizen --}}
                <div class="bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 p-6 shadow-sm mt-6">
                    <div class="flex items-center gap-3 mb-4">
                        @svg('heroicon-o-document-text', 'w-5 h-5 text-[var(--ui-primary)]')
                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Zusätzliche Notizen</h3>
                    </div>
                    <textarea
                        wire:model.live.debounce.500ms="checkinData.notes"
                        placeholder="Weitere Gedanken oder Notizen..."
                        class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent resize-none"
                        rows="3"
                    ></textarea>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-between gap-4">
            <x-ui-button variant="secondary-outline" wire:click="$set('modalShow', false)">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    Schließen
                </div>
            </x-ui-button>
            <x-ui-button variant="primary" wire:click="save">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    Check-in speichern
                </div>
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>

<script>
function pomodoroTimer() {
    return {
        // Timer Settings
        workTime: 25 * 60, // 25 minutes (default)
        
        // Current State
        timeLeft: 25 * 60,
        isRunning: false,
        sessionCount: 1,
        pomodoroCount: 0,
        
        // Timer Interval
        timer: null,
        
        // Computed Properties
        get progress() {
            return (this.workTime - this.timeLeft) / this.workTime;
        },
        
        init() {
            this.loadFromServer();
            
            // Listen for Livewire updates
            this.$el.addEventListener('livewire:updated', () => {
                this.loadFromServer();
            });
            
            // Listen for timer expiration
            this.$el.addEventListener('timer-expired', () => {
                this.completeSession();
            });
            
            // No polling needed
        },
        
            startSmartPolling() {
                // No polling needed
            },
            
            stopSmartPolling() {
                // No polling needed
            },
        
        loadFromServer() {
            // Load from server data
            const sessionData = this.$el.getAttribute('x-pomodoro-session');
            const statsData = this.$el.getAttribute('x-pomodoro-stats');
            
            if (sessionData && sessionData !== 'null') {
                const session = JSON.parse(sessionData);
                this.timeLeft = (session.remaining_minutes || 0) * 60; // Convert to seconds for internal calculation
                this.isRunning = session.is_active;
                this.pomodoroCount = statsData ? JSON.parse(statsData).today_count : 0;
                
                // Start timer if session is active and has time left
                if (this.isRunning && this.timeLeft > 0) {
                    // Only start if not already running
                    if (!this.timer) {
                        this.startTimer();
                    }
                } else if (this.isRunning && this.timeLeft <= 0) {
                    // Session expired, complete it
                    this.completeSession();
                }
            } else {
                this.resetTimer();
            }
            
            this.updateDisplay();
        },
        
            startTimer() {
                if (this.isRunning) return;
                
                // Get the current time setting and start Livewire session
                const minutes = Math.ceil(this.timeLeft / 60);
                this.$wire.startPomodoro('work', minutes);
                
                this.isRunning = true;
                this.timer = setInterval(() => {
                    this.timeLeft -= 30; // Update every 30 seconds
                    this.updateDisplay();
                    
                    if (this.timeLeft <= 0) {
                        this.completeSession();
                    }
                }, 30000); // Update every 30 seconds
                
                // Timer started
            },
        
        pauseTimer() {
            this.isRunning = false;
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            this.updateDisplay();
        },
        
        resetTimer() {
            this.pauseTimer();
            this.timeLeft = this.workTime;
            this.sessionCount = 1;
            this.updateDisplay();
        },
        
        setTime(minutes) {
            this.pauseTimer();
            this.timeLeft = minutes * 60;
            this.workTime = minutes * 60;
            this.updateDisplay();
        },
        
            completeSession() {
                this.pauseTimer();
                
                // Play notification sound (if available)
                this.playNotification();
                
                // Just increment counters, no break logic
                this.pomodoroCount++;
                this.sessionCount++;
                
                // Trigger Livewire to update database
                this.$wire.stopPomodoro();
                this.updateDisplay();
            },
        
        formatTime(seconds) {
            const minutes = Math.ceil(seconds / 60);
            return `${minutes}`;
        },
        
            updateDisplay() {
                // No tab title updates - timer only in sidebar
            },
        
        playNotification() {
            // Try to play notification sound
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBC13yO/eizEIHWq+8+OWT');
                audio.volume = 0.3;
                audio.play().catch(() => {
                    // Fallback: use system beep
                    console.log('\u0007'); // ASCII bell character
                });
            } catch (e) {
                console.log('\u0007'); // Fallback beep
            }
        },
        
        clearData() {
            if (confirm('Alle Pomodoro-Daten löschen?')) {
                this.pomodoroCount = 0;
                this.sessionCount = 1;
                this.timeLeft = this.workTime;
                this.pauseTimer();
                this.updateDisplay();
            }
        }
    }
}
</script>
