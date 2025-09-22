<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        Befehl ausführen
    </x-slot>

    <div class="space-y-3">
        <div>
            <input
                type="text"
                wire:model.defer="input"
                wire:keydown.enter="execute"
                class="w-full px-3 py-2 border rounded"
                placeholder="z. B.: lege projekt Alpha an"
                aria-label="Befehl eingeben"
                autofocus
            >
            @error('input')<div class="text-danger text-sm mt-1">{{ $message }}</div>@enderror
        </div>

        @if(!empty($result))
            <div class="p-3 rounded border bg-muted-5">
                @if(($result['ok'] ?? false) === true)
                    <div class="text-success font-medium mb-1">Erfolg</div>
                    @if(!empty($result['message']))
                        <div class="text-sm">{{ $result['message'] }}</div>
                    @endif
                    @if(!empty($result['navigate']))
                        <div class="mt-2">
                            <a href="{{ $result['navigate'] }}" class="text-primary underline" wire:navigate>Öffnen</a>
                        </div>
                    @endif
                @else
                    <div class="text-danger font-medium mb-1">Fehler</div>
                    <div class="text-sm">{{ $result['message'] ?? 'Unbekannter Fehler' }}</div>
                @endif
            </div>
        @endif
    </div>

    <x-slot name="footer">
        <div class="d-flex items-center gap-2 ml-auto">
            <x-ui-button variant="secondary-outline" wire:click="close">Schließen</x-ui-button>
            <x-ui-button variant="primary" wire:click="execute">Ausführen</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>


