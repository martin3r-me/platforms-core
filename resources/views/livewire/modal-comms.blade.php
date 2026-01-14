<div>
    <x-ui-modal size="wide" hideFooter="1" wire:model="open" :closeButton="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                        @svg('heroicon-o-paper-airplane', 'w-6 h-6 text-[var(--ui-primary)]')
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Kommunikation</h3>
                    <p class="text-sm text-[var(--ui-muted)]">UI-Shell (ohne Daten) – nur Aufbau.</p>
                </div>
            </div>
        </x-slot>

        <div class="-m-6 w-full h-full min-h-0 min-w-0 overflow-hidden" style="width:100%;">
            <div class="p-6 h-full min-h-0">
                {{-- Chat-Fenster (nur Andeutung, keine Logik) --}}
                <div class="h-full min-h-0 flex flex-col rounded-xl border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] shadow-sm overflow-hidden">
                    {{-- Header --}}
                    <div class="px-4 py-3 border-b border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">Chat</div>
                                    <span class="text-[11px] text-[var(--ui-muted)]">(Demo)</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    {{-- Thread/Contact Badges (hart codiert) --}}
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--ui-muted-5)] px-2.5 py-1 text-xs text-[var(--ui-secondary)] border border-[var(--ui-border)]/60">
                                        @svg('heroicon-o-envelope', 'w-4 h-4 text-[var(--ui-muted)]')
                                        <span class="truncate max-w-[14rem]">m.erren@bhgdigital.de</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--ui-muted-5)] px-2.5 py-1 text-xs text-[var(--ui-secondary)] border border-[var(--ui-border)]/60">
                                        @svg('heroicon-o-phone', 'w-4 h-4 text-[var(--ui-muted)]')
                                        <span class="truncate max-w-[14rem]">+ 0172 123 12 14</span>
                                    </span>
                                </div>
                            </div>

                            {{-- Plus (Thread anlegen) – nur UI --}}
                            <button
                                type="button"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition"
                                title="Neuer Thread"
                            >
                                @svg('heroicon-o-plus', 'w-5 h-5')
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="flex-1 min-h-0 overflow-y-auto bg-[var(--ui-muted-5)] p-4">
                        <div class="max-w-3xl mx-auto space-y-3">
                            <div class="flex justify-start">
                                <div class="max-w-[85%] rounded-2xl bg-white border border-[var(--ui-border)]/60 px-4 py-3">
                                    <div class="text-xs text-[var(--ui-muted)]">Gegenseite</div>
                                    <div class="mt-1 text-sm text-[var(--ui-secondary)]">
                                        Platzhalter-Nachricht (Inbound) …
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <div class="max-w-[85%] rounded-2xl bg-[var(--ui-surface)] border border-[var(--ui-border)]/60 px-4 py-3">
                                    <div class="text-xs text-[var(--ui-muted)] text-right">Du</div>
                                    <div class="mt-1 text-sm text-[var(--ui-secondary)]">
                                        Platzhalter-Antwort (Outbound) …
                                    </div>
                                </div>
                            </div>
                            <div class="text-center text-xs text-[var(--ui-muted)] pt-2">
                                (Hier später Threads, Verlauf, Attachments, etc.)
                            </div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="px-4 py-3 border-t border-[var(--ui-border)]/60 bg-[var(--ui-bg)]">
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                <textarea
                                    rows="2"
                                    class="w-full rounded-md border border-[var(--ui-border)]/60 bg-white px-3 py-2 text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/20"
                                    placeholder="Nachricht… (UI only)"
                                    disabled
                                ></textarea>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-md bg-[var(--ui-primary)] px-3 py-2 text-sm font-semibold text-[var(--ui-on-primary)] opacity-60 cursor-not-allowed"
                                disabled
                            >
                                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                                Senden
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-modal>
</div>

