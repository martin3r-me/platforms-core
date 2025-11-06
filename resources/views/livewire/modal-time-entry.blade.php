@auth
<x-ui-modal size="md" wire:model="open" :closeButton="true" x-on:close-modal-time-entry.window="$wire.close()">
    <x-slot name="title">
        Zeit erfassen
    </x-slot>

    <div class="space-y-4">
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
                <x-ui-input-select
                    label="Dauer"
                    wire:model.live="minutes"
                    :options="collect($this->minuteOptions)->map(fn($value) => ['value' => $value, 'label' => number_format($value / 60, 2, ',', '.') . ' h'])"
                    optionValue="value"
                    optionLabel="label"
                    :errorKey="'minutes'"
                />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui-input-text
                label="Stundensatz (optional)"
                wire:model.live="rate"
                placeholder="z. B. 95,00"
                :errorKey="'rate'"
            />

            <x-ui-input-textarea
                label="Notiz"
                wire:model.live="note"
                rows="2"
                placeholder="Optionaler Kommentar"
                :errorKey="'note'"
            />
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end gap-3">
            <x-ui-button variant="secondary" wire:click="close">
                Abbrechen
            </x-ui-button>
            <x-ui-button variant="primary" wire:click="save" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Speichern</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                    Speichern…
                </span>
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
@endauth

