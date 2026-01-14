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
                    <p class="text-sm text-[var(--ui-muted)]">Platzhalter (UI-Shell) – Inhalt folgt.</p>
                </div>
            </div>
        </x-slot>

        <div class="-m-6 w-full h-full min-h-0 min-w-0 overflow-hidden" style="width:100%;">
            <div class="p-6 h-full flex items-center justify-center">
                <div class="text-center max-w-md">
                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">Comms v2</div>
                    <div class="mt-1 text-sm text-[var(--ui-muted)]">
                        Hier kommt die neue Kommunikations-UI rein (ohne Daten, nur Hülle).
                    </div>
                </div>
            </div>
        </div>
    </x-ui-modal>
</div>

