<div x-data="{ open: $wire.entangle('open') }" class="h-full d-flex">
    <div x-show="open" x-cloak class="h-full d-flex">
        <x-ui-right-sidebar>
        <div class="sticky top-0 z-10 px-3 py-2 border-bottom-1 d-flex items-center gap-2 bg-white">
            <div class="font-medium">Cursor</div>
            <div class="ml-auto text-xs text-gray-500">Tokens: {{ $totalTokensIn }} / {{ $totalTokensOut }}</div>
            <x-ui-button size="sm" variant="secondary-outline" @click="$wire.toggle()">Schließen</x-ui-button>
        </div>
        <div class="flex-1 overflow-auto p-3 space-y-2">
            @foreach($feed as $b)
                @if(($b['role'] ?? '') === 'user')
                    <div class="text-right">
                        <div class="inline-block bg-primary text-white px-3 py-2 rounded">{{ $b['text'] ?? '' }}</div>
                    </div>
                @elseif(($b['type'] ?? '') === 'plan')
                    <div class="text-left text-xs bg-muted-10 px-3 py-2 rounded">
                        <div class="font-medium">Plan</div>
                        <div>Intent: {{ $b['data']['intent'] ?? '–' }}</div>
                        @if(!empty($b['data']['impact']))
                            <div>Impact: {{ $b['data']['impact'] }}</div>
                        @endif
                    </div>
                @elseif(($b['type'] ?? '') === 'confirm')
                    <div class="text-left text-sm bg-warning-50 px-3 py-2 rounded">
                        <div class="font-medium mb-1">Bestätigung erforderlich</div>
                        <div>Intent: {{ $b['data']['intent'] ?? '' }}</div>
                        <div class="text-xs">Confidence: {{ (string)($b['data']['confidence'] ?? '') }}</div>
                        <div class="mt-2 d-flex items-center gap-2">
                            <x-ui-button size="sm" variant="primary" wire:click="confirmAndRun('{{ $b['data']['intent'] ?? '' }}', {{ json_encode($b['data']['slots'] ?? []) }})">Bestätigen</x-ui-button>
                        </div>
                    </div>
                @elseif(($b['type'] ?? '') === 'result')
                    <div class="text-left text-sm bg-success-50 px-3 py-2 rounded">
                        <div class="font-medium">Ergebnis</div>
                        <div>{{ $b['data']['message'] ?? (($b['data']['ok'] ?? false) ? 'OK' : 'Fehler') }}</div>
                        @if(!empty($b['data']['data']['tasks']))
                            <ul class="mt-2 text-xs list-disc pl-4 space-y-1">
                                @foreach($b['data']['data']['tasks'] as $t)
                                    <li>
                                        <span class="{{ !empty($t['is_done']) ? 'line-through text-gray-400' : '' }}">{{ $t['title'] ?? '–' }}</span>
                                        @if(!empty($t['due_date']))
                                            <span class="ml-1 text-gray-500">(fällig: {{ $t['due_date'] }})</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($b['data']['data']['projects']))
                            <ul class="mt-2 text-xs list-disc pl-4 space-y-1">
                                @foreach($b['data']['data']['projects'] as $p)
                                    <li>{{ $p['name'] ?? 'Unbenannt' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @elseif(($b['type'] ?? '') === 'message')
                    <div class="text-left text-sm bg-muted-5 px-3 py-2 rounded">
                        <div>{{ $b['data']['text'] ?? '' }}</div>
                    </div>
                @elseif(($b['type'] ?? '') === 'choices')
                    <div class="text-left text-sm bg-muted-5 px-3 py-2 rounded">
                        <div class="font-medium mb-1">{{ $b['data']['title'] ?? 'Bitte wählen' }}</div>
                        <ul class="space-y-1">
                            @foreach(($b['data']['items'] ?? []) as $it)
                                <li>
                                    <x-ui-button size="sm" variant="secondary-outline" wire:click="openProjectById({{ (int)($it['id'] ?? 0) }})">
                                        {{ $it['name'] ?? 'Unbenannt' }}
                                    </x-ui-button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="px-3 py-2 border-top-1 d-flex items-center gap-2">
            <input type="text" class="flex-grow border rounded px-2 py-1" placeholder="Nachricht…" wire:model.defer="input" wire:keydown.enter="send">
            <label class="d-flex items-center gap-2 text-xs">
                <input type="checkbox" wire:model.live="forceExecute" class="border rounded"> ohne Rückfrage
            </label>
            <x-ui-button size="sm" variant="primary" wire:click="send">Senden</x-ui-button>
        </div>
        </x-ui-right-sidebar>
    </div>
</div>


