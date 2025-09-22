<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        Cursor
    </x-slot>

    <div class="space-y-3">
        <div class="d-flex items-center gap-2">
            <input type="text" class="flex-grow px-3 py-2 border rounded" placeholder="Befehl eingeben…" wire:model.defer="input" wire:keydown.enter="planAndMaybeRun">
            <label class="d-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="forceExecute" class="border rounded">
                ohne Rückfrage ausführen
            </label>
            <x-ui-button variant="primary" wire:click="planAndMaybeRun">Ausführen</x-ui-button>
        </div>

        @if(!empty($feed))
            <div class="p-3 rounded border bg-muted-5 space-y-2 max-h-96 overflow-auto">
                @foreach($feed as $b)
                    @if(($b['role'] ?? '') === 'user')
                        <div class="text-right">
                            <div class="inline-block bg-primary text-white px-3 py-2 rounded">{{ $b['text'] ?? '' }}</div>
                        </div>
                    @elseif(($b['type'] ?? '') === 'plan')
                        <div class="text-left text-sm bg-muted-10 px-3 py-2 rounded">
                            <div class="font-medium">Plan</div>
                            <div>Intent: {{ $b['data']['intent'] ?? '–' }}</div>
                            @if(!empty($b['data']['slots']))
                                <div class="text-xs">Slots: {{ json_encode($b['data']['slots']) }}</div>
                            @endif
                            @if(!empty($b['data']['impact']))
                                <div class="text-xs">Impact: {{ $b['data']['impact'] }}</div>
                            @endif
                        </div>
                    @elseif(($b['type'] ?? '') === 'confirm')
                        <div class="text-left text-sm bg-warning-50 px-3 py-2 rounded">
                            <div class="font-medium mb-1">Bestätigung erforderlich</div>
                            <div>Intent: {{ $b['data']['intent'] ?? '' }}</div>
                            @if(!empty($b['data']['slots']))
                                <div class="text-xs mt-1">Parameter: {{ json_encode($b['data']['slots']) }}</div>
                            @endif
                            <div class="mt-2 d-flex items-center gap-2">
                                <x-ui-button size="sm" variant="primary" wire:click="confirmAndRun('{{ $b['data']['intent'] ?? '' }}', {{ json_encode($b['data']['slots'] ?? []) }})">Bestätigen</x-ui-button>
                            </div>
                        </div>
                    @elseif(($b['type'] ?? '') === 'result')
                        <div class="text-left text-sm bg-success-50 px-3 py-2 rounded">
                            <div class="font-medium">Ergebnis</div>
                            <div>{{ $b['data']['message'] ?? (($b['data']['ok'] ?? false) ? 'OK' : 'Fehler') }}</div>
                        </div>
                    @elseif(($b['type'] ?? '') === 'error')
                        <div class="text-left text-sm bg-danger-50 px-3 py-2 rounded">
                            <div class="font-medium">Fehler</div>
                            <div>{{ is_array($b['data']) ? json_encode($b['data']) : ($b['data'] ?? '') }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <x-slot name="footer">
        <div class="d-flex items-center gap-2 ml-auto">
            <x-ui-button variant="secondary-outline" wire:click="close">Schließen</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>


