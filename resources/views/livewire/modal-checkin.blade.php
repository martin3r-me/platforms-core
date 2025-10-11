<x-ui-modal size="full" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Täglicher Check-in</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">SELBSTREFLEXION</span>
            </div>
        </div>
    </x-slot>

    <div class="grid grid-cols-12 gap-6 h-full">
        {{-- Kalender --}}
        <div class="col-span-5">
            <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Kalender</h3>
                
                {{-- Monats-Navigation --}}
                <div class="flex items-center justify-between mb-4">
                    <button wire:click="previousMonth" class="p-2 hover:bg-[var(--ui-muted-5)] rounded-lg transition-colors">
                        @svg('heroicon-o-chevron-left', 'w-5 h-5 text-[var(--ui-muted)]')
                    </button>
                    <h4 class="text-lg font-semibold text-[var(--ui-secondary)]">
                        {{ \Carbon\Carbon::create($currentYear, $currentMonth, 1)->locale('de')->isoFormat('MMMM YYYY') }}
                    </h4>
                    <button wire:click="nextMonth" class="p-2 hover:bg-[var(--ui-muted-5)] rounded-lg transition-colors">
                        @svg('heroicon-o-chevron-right', 'w-5 h-5 text-[var(--ui-muted)]')
                    </button>
                </div>

                {{-- Kalender-Grid --}}
                <div class="grid grid-cols-7 gap-1 mb-2">
                    @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $day)
                        <div class="text-center text-sm font-medium text-[var(--ui-muted)] py-2">{{ $day }}</div>
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
                            class="relative p-2 text-sm rounded-lg transition-colors
                                {{ $isCurrentMonth ? 'text-[var(--ui-secondary)]' : 'text-[var(--ui-muted)]' }}
                                {{ $isToday ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] font-semibold' : '' }}
                                {{ $isSelected && !$isToday ? 'bg-[var(--ui-muted-5)] text-[var(--ui-primary)] font-semibold' : '' }}
                                {{ !$isSelected && !$isToday ? 'hover:bg-[var(--ui-muted-5)]' : '' }}
                            "
                        >
                            {{ $currentDate->day }}
                            @if($hasCheckin)
                                <div class="absolute bottom-0 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-[var(--ui-primary)] rounded-full"></div>
                            @endif
                        </button>

                        @php $currentDate->addDay(); @endphp
                    @endwhile
                </div>
            </div>
        </div>

        {{-- Check-in Formular --}}
        <div class="col-span-7">
            <div class="space-y-6">
                {{-- Datum und Grunddaten --}}
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">
                        Check-in für {{ \Carbon\Carbon::parse($selectedDate)->locale('de')->isoFormat('dddd, DD. MMMM YYYY') }}
                    </h3>

                    <div class="grid grid-cols-2 gap-4">
                        {{-- Tagesziel --}}
                        <div class="col-span-2">
                            <x-ui-input-text
                                name="checkinData.daily_goal"
                                label="Wichtigstes Ziel für heute"
                                wire:model.live.debounce.500ms="checkinData.daily_goal"
                                placeholder="Was ist dein wichtigstes Ziel heute?"
                                :errorKey="'checkinData.daily_goal'"
                            />
                        </div>

                        {{-- Stimmung --}}
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Stimmung</label>
                            <select wire:model.live="checkinData.mood" class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent">
                                <option value="">Stimmung wählen</option>
                                @foreach($this->getMoodOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Glücksskala --}}
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Glück (1-10)</label>
                            <select wire:model.live="checkinData.happiness" class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent">
                                <option value="">Glückslevel wählen</option>
                                @foreach($this->getHappinessOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $value }} - {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Selbstreflexion --}}
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Selbstreflexion</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="checkinData.hydrated" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Genug getrunken</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="checkinData.exercised" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Sich bewegt</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="checkinData.slept_well" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Gut geschlafen</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="checkinData.focused_work" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Fokussiert gearbeitet</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="checkinData.social_time" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Zeit mit anderen</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" wire:model.live="checkinData.needs_support" class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]">
                            <span class="text-sm text-[var(--ui-secondary)]">Unterstützung nötig</span>
                        </label>
                    </div>
                </div>

                {{-- To-Do Liste --}}
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6">
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Tagesaufgaben</h3>
                    
                    {{-- Neue Aufgabe hinzufügen --}}
                    <div class="flex gap-2 mb-4">
                        <input 
                            type="text" 
                            wire:model="newTodoTitle" 
                            wire:keydown.enter="addTodo"
                            placeholder="Neue Aufgabe hinzufügen..."
                            class="flex-1 px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-transparent"
                        >
                        <x-ui-button wire:click="addTodo" variant="primary">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                        </x-ui-button>
                    </div>

                    {{-- To-Do Liste --}}
                    <div class="space-y-2">
                        @forelse($todos as $todo)
                            <div class="flex items-center gap-3 p-3 bg-[var(--ui-muted-5)] rounded-lg">
                                <input 
                                    type="checkbox" 
                                    wire:click="toggleTodo({{ $todo['id'] }})"
                                    {{ $todo['done'] ? 'checked' : '' }}
                                    class="w-4 h-4 text-[var(--ui-primary)] rounded border-[var(--ui-border)]"
                                >
                                <span class="flex-1 text-sm {{ $todo['done'] ? 'line-through text-[var(--ui-muted)]' : 'text-[var(--ui-secondary)]' }}">
                                    {{ $todo['title'] }}
                                </span>
                                <button 
                                    wire:click="deleteTodo({{ $todo['id'] }})"
                                    class="p-1 hover:bg-[var(--ui-danger)] hover:text-[var(--ui-on-danger)] rounded transition-colors"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-[var(--ui-muted)] text-center py-4">Noch keine Aufgaben für heute</p>
                        @endforelse
                    </div>
                </div>

                {{-- Notizen --}}
                <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6">
                    <x-ui-input-text
                        name="checkinData.notes"
                        label="Zusätzliche Notizen"
                        wire:model.live.debounce.500ms="checkinData.notes"
                        placeholder="Weitere Gedanken oder Notizen..."
                        :errorKey="'checkinData.notes'"
                        type="textarea"
                        rows="3"
                    />
                </div>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-between">
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
