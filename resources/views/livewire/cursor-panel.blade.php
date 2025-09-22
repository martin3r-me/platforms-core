<div class="fixed bottom-4 right-4 w-96 z-40" x-data>
    <div class="bg-white border rounded shadow-lg overflow-hidden" x-show="$wire.open">
        <div class="px-3 py-2 border-b d-flex items-center gap-2">
            <div class="font-medium">Cursor</div>
            <label class="ml-auto d-flex items-center gap-2 text-xs">
                <input type="checkbox" wire:model.live="forceExecute" class="border rounded">
                ohne Rückfrage ausführen
            </label>
        </div>
        <div class="p-3 max-h-80 overflow-auto space-y-2">
            @foreach($bubbles as $b)
                @if(($b['role'] ?? '') === 'user')
                    <div class="text-right">
                        <div class="inline-block bg-primary text-white px-3 py-2 rounded">{{ $b['text'] ?? '' }}</div>
                    </div>
                @elseif(($b['type'] ?? '') === 'plan')
                    <div class="text-left text-sm bg-muted-5 px-3 py-2 rounded">
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

            @if($this->recentRuns->isNotEmpty())
                <div class="mt-3 text-xs text-gray-500">
                    <div class="font-medium mb-1">Letzte Runs</div>
                    <ul class="space-y-1">
                        @foreach($this->recentRuns as $run)
                            <li>- {{ $run->command_key }} ({{ $run->result_status }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="px-3 py-2 border-t d-flex items-center gap-2">
            <input type="text" class="flex-grow border rounded px-2 py-1" placeholder="Befehl eingeben…" wire:model.defer="input" wire:keydown.enter="run">
            <x-ui-button variant="primary" size="sm" wire:click="run">Senden</x-ui-button>
        </div>
    </div>
    <button type="button" class="bg-primary text-white rounded-full w-12 h-12 shadow-lg d-flex items-center justify-center" @click="$wire.toggle()" x-show="!$wire.open">⚡</button>
</div>


