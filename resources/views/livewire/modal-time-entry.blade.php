<x-ui-modal size="lg" wire:model="open" :closeButton="true" x-on:close-modal-time-entry.window="$wire.close()">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                    @svg('heroicon-o-clock', 'w-5 h-5 text-[var(--ui-primary)]')
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">Zeiterfassung</h3>
                <p class="text-sm text-[var(--ui-muted)]">Zeiten erfassen und verwalten</p>
            </div>
        </div>
    </x-slot>

    <div x-data="{ activeTab: $wire.entangle('activeTab') }">
        <!-- Tabs -->
        <div class="flex gap-1 border-b border-[var(--ui-border)]/60 mb-6">
            <button
                @click="activeTab = 'entry'"
                :class="activeTab === 'entry' 
                    ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] font-semibold' 
                    : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'"
                class="px-4 py-2 text-sm transition-colors"
            >
                <span class="inline-flex items-center gap-2">
                    @svg('heroicon-o-plus-circle', 'w-4 h-4')
                    Neue Zeit erfassen
                </span>
            </button>
            <button
                @click="activeTab = 'overview'"
                :class="activeTab === 'overview' 
                    ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)] font-semibold' 
                    : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'"
                class="px-4 py-2 text-sm transition-colors"
            >
                <span class="inline-flex items-center gap-2">
                    @svg('heroicon-o-list-bullet', 'w-4 h-4')
                    Übersicht
                </span>
            </button>
        </div>

        <!-- Tab Content: Entry -->
        <div x-show="activeTab === 'entry'" x-cloak>
            <div class="space-y-6">
                <!-- Quick Time Buttons -->
                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-3">
                        Schnellauswahl
                    </label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach([15, 30, 60, 120] as $quickMinutes)
                            <button
                                type="button"
                                wire:click="$set('minutes', {{ $quickMinutes }})"
                                :class="$wire.minutes === {{ $quickMinutes }}
                                    ? 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] border-[var(--ui-primary)]'
                                    : 'bg-[var(--ui-surface)] text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:border-[var(--ui-primary)]/40'"
                                class="px-4 py-3 rounded-lg border-2 font-semibold transition-all duration-200 hover:scale-105"
                            >
                                {{ number_format($quickMinutes / 60, 1, ',', '.') }}h
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Form Fields -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-ui-input-date
                            name="workDate"
                            label="Datum"
                            wire:model.live="workDate"
                            :errorKey="'workDate'"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                            Dauer
                        </label>
                        <select
                            wire:model.live="minutes"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20 focus:border-[var(--ui-primary)]"
                        >
                            @foreach($this->minuteOptions as $option)
                                <option value="{{ $option }}">{{ number_format($option / 60, 2, ',', '.') }} h</option>
                            @endforeach
                        </select>
                        @error('minutes')
                            <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui-input-text
                        name="rate"
                        label="Stundensatz (optional)"
                        wire:model.live="rate"
                        placeholder="z. B. 95,00"
                        :errorKey="'rate'"
                    />

                    <x-ui-input-textarea
                        name="note"
                        label="Notiz"
                        wire:model.live="note"
                        rows="3"
                        placeholder="Optionaler Kommentar zur erfassten Zeit"
                        :errorKey="'note'"
                    />
                </div>

                <!-- Preview -->
                @if($rate && $minutes)
                    @php
                        $normalized = str_replace([' ', "'"], '', $rate);
                        $normalized = str_replace(',', '.', $normalized);
                        $rateFloat = is_numeric($normalized) && (float)$normalized > 0 ? (float)$normalized : null;
                        $amountCents = $rateFloat !== null ? (int) round($rateFloat * 100 * ($minutes / 60)) : null;
                    @endphp
                    @if($amountCents)
                        <div class="p-4 bg-gradient-to-br from-[var(--ui-primary-5)] to-[var(--ui-primary-10)] rounded-lg border border-[var(--ui-primary)]/20">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-[var(--ui-secondary)]">Geschätzter Betrag:</span>
                                <span class="text-lg font-bold text-[var(--ui-primary)]">{{ number_format($amountCents / 100, 2, ',', '.') }} €</span>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Tab Content: Overview -->
        <div x-show="activeTab === 'overview'" x-cloak>
            <div class="space-y-6">
                <!-- Statistics -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                        <div class="text-xs font-medium text-[var(--ui-muted)] mb-1">Gesamt</div>
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalMinutes / 60, 2, ',', '.') }} h</div>
                    </div>
                    <div class="p-4 bg-gradient-to-br from-[var(--ui-success-5)] to-[var(--ui-success-10)] rounded-lg border border-[var(--ui-success)]/20">
                        <div class="text-xs font-medium text-[var(--ui-success)] mb-1">Abgerechnet</div>
                        <div class="text-2xl font-bold text-[var(--ui-success)]">{{ number_format($this->billedMinutes / 60, 2, ',', '.') }} h</div>
                    </div>
                    <div class="p-4 bg-gradient-to-br from-[var(--ui-warning-5)] to-[var(--ui-warning-10)] rounded-lg border border-[var(--ui-warning)]/20">
                        <div class="text-xs font-medium text-[var(--ui-warning)] mb-1">Offen</div>
                        <div class="text-2xl font-bold text-[var(--ui-warning)]">{{ number_format($this->unbilledMinutes / 60, 2, ',', '.') }} h</div>
                    </div>
                    @if($this->unbilledAmountCents)
                        <div class="p-4 bg-gradient-to-br from-[var(--ui-primary-5)] to-[var(--ui-primary-10)] rounded-lg border border-[var(--ui-primary)]/20">
                            <div class="text-xs font-medium text-[var(--ui-primary)] mb-1">Offener Wert</div>
                            <div class="text-2xl font-bold text-[var(--ui-primary)]">{{ number_format($this->unbilledAmountCents / 100, 2, ',', '.') }} €</div>
                        </div>
                    @endif
                </div>

                <!-- Entries List -->
                <div class="rounded-lg border border-[var(--ui-border)]/60 overflow-hidden">
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @forelse($entries ?? [] as $entry)
                            <div class="flex flex-col gap-3 px-4 py-3 hover:bg-[var(--ui-muted-5)]/50 transition-colors sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex-1 flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-[var(--ui-secondary)]">{{ $entry->work_date?->format('d.m.Y') }}</span>
                                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $entry->is_billed ? 'bg-[var(--ui-success-10)] border-[var(--ui-success)]/40 text-[var(--ui-success)]' : 'bg-[var(--ui-warning-10)] border-[var(--ui-warning)]/40 text-[var(--ui-warning)]' }}">
                                            @if($entry->is_billed)
                                                @svg('heroicon-o-check-circle', 'w-3 h-3')
                                            @else
                                                @svg('heroicon-o-exclamation-circle', 'w-3 h-3')
                                            @endif
                                            {{ $entry->is_billed ? 'Abgerechnet' : 'Offen' }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-[var(--ui-muted)] flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ number_format($entry->minutes / 60, 2, ',', '.') }} h</span>
                                        @if($entry->amount_cents)
                                            <span>• {{ number_format($entry->amount_cents / 100, 2, ',', '.') }} €</span>
                                        @elseif($entry->rate_cents)
                                            <span>• {{ number_format($entry->rate_cents / 100, 2, ',', '.') }} €/h</span>
                                        @endif
                                        <span>• {{ $entry->user?->name ?? 'Unbekannt' }}</span>
                                    </div>
                                    @if($entry->note)
                                        <div class="text-xs text-[var(--ui-muted)] italic mt-1">{{ $entry->note }}</div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        wire:click="toggleBilled({{ $entry->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleBilled({{ $entry->id }})"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors {{ $entry->is_billed ? 'bg-[var(--ui-surface)] text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]' : 'bg-[var(--ui-success-5)] text-[var(--ui-success)] border-[var(--ui-success)]/40 hover:bg-[var(--ui-success-10)]' }}"
                                    >
                                        {{ $entry->is_billed ? 'Als offen markieren' : 'Abrechnen' }}
                                    </button>
                                    <button
                                        wire:click="deleteEntry({{ $entry->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteEntry({{ $entry->id }})"
                                        class="p-1.5 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] rounded-lg transition-colors"
                                        title="Eintrag löschen"
                                    >
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-8 text-center">
                                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center">
                                    @svg('heroicon-o-clock', 'w-6 h-6 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm text-[var(--ui-muted)]">Noch keine Zeiten erfasst.</p>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">Wechsle zum Tab "Neue Zeit erfassen" um zu beginnen.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-between items-center">
            <div class="text-xs text-[var(--ui-muted)]" x-show="activeTab === 'overview'">
                @if($entries && $entries->count() > 0)
                    {{ $entries->count() }} Einträge
                @endif
            </div>
            <div class="flex justify-end gap-3 ml-auto">
                <x-ui-button variant="secondary" wire:click="close">
                    Schließen
                </x-ui-button>
                <x-ui-button 
                    variant="primary" 
                    wire:click="save" 
                    wire:loading.attr="disabled"
                    x-show="activeTab === 'entry'"
                >
                    <span wire:loading.remove wire:target="save">Zeit erfassen</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                        Speichern…
                    </span>
                </x-ui-button>
            </div>
        </div>
    </x-slot>
</x-ui-modal>
