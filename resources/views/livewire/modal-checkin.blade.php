    <x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Täglicher Check-in</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">SELBSTREFLEXION</span>
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
                            class="group relative p-2 text-sm rounded-lg transition-all duration-200 hover:scale-105
                                {{ $isCurrentMonth ? 'text-[var(--ui-secondary)]' : 'text-[var(--ui-muted)]' }}
                                {{ $isToday ? 'bg-gradient-to-br from-[var(--ui-primary)] to-[var(--ui-primary)]/80 text-[var(--ui-on-primary)] font-semibold shadow-md' : '' }}
                                {{ $isSelected && !$isToday ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-semibold border border-[var(--ui-primary)]/20' : '' }}
                                {{ !$isSelected && !$isToday ? 'hover:bg-[var(--ui-muted-5)] hover:border hover:border-[var(--ui-border)]/40' : '' }}
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

                {{-- Check-in Status --}}
                <div class="mt-4 pt-4 border-t border-[var(--ui-border)]/60">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-[var(--ui-muted)]">Heute:</span>
                        <div class="flex items-center gap-2">
                            @if(in_array(now()->format('Y-m-d'), $checkins))
                                <div class="flex items-center gap-1 text-[var(--ui-success)]">
                                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                                    <span class="font-medium">Check-in erledigt</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1 text-[var(--ui-warning)]">
                                    @svg('heroicon-o-clock', 'w-4 h-4')
                                    <span class="font-medium">Noch ausstehend</span>
                                </div>
                            @endif
                        </div>
                    </div>
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
                    <div class="flex gap-2 mb-4">
                        <input
                            type="text"
                            wire:model="newTodoTitle"
                            wire:keydown.enter="addTodo"
                            placeholder="Neue Aufgabe hinzufügen..."
                            class="flex-1 px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                        >
                        <x-ui-button wire:click="addTodo" variant="primary" class="px-4">
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
