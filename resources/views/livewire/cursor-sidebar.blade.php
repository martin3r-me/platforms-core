<div x-data="{ open: $wire.entangle('open') }" class="h-full d-flex">
    <div x-show="open" x-cloak class="h-full d-flex">
        <x-ui-right-sidebar>
        <div class="sticky top-0 z-10 px-2 py-2 border-bottom-1 d-flex items-center gap-2 bg-white overflow-x-hidden">
            <x-heroicon-o-bolt class="w-5 h-5 text-primary" />
            <div class="text-xs text-gray-500 truncate">Aktive Chats: {{ $activeChatsCount }}</div>
            <div class="d-flex items-center gap-1 ml-2">
                @foreach($recentChats as $c)
                    <button class="text-xs px-2 py-0.5 rounded bg-muted-10 hover:bg-muted-20 truncate max-w-32" title="{{ $c['title'] }}" wire:click="switchChat({{ (int)$c['id'] }})">{{ $c['title'] }}</button>
                @endforeach
                <x-ui-button size="xs" variant="secondary-outline" wire:click="newChat">+</x-ui-button>
            </div>
            @php
                // Einfacher Workflow-Pill: zeige Fortschritt des letzten Plans
                $plans = array_values(array_filter($feed, fn($b) => (($b['type'] ?? '') === 'plan')));
                $lastPlan = !empty($plans) ? end($plans) : null;
                $items = is_array($lastPlan['data'] ?? null) ? ($lastPlan['data'] ?? []) : [];
                $total = count($items);
                $done = collect($items)->where('done', true)->count();
                $pct = $total > 0 ? (int) floor(($done / $total) * 100) : 0;
            @endphp
            @if($total > 0)
                <div class="ml-2 d-flex items-center gap-2 px-2 py-0.5 rounded bg-muted-10">
                    <x-heroicon-o-clipboard-document-check class="w-4 h-4 text-primary" />
                    <div class="text-xs">Workflow: {{ $done }}/{{ $total }} ({{ $pct }}%)</div>
                    <div class="w-16 h-1 rounded bg-muted-20 overflow-hidden">
                        <div class="h-1 bg-primary" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endif
            <div class="ml-auto text-xs text-gray-500 truncate">
                <span class="px-2 py-0.5 rounded bg-muted-10">{{ $totalTokensIn }}</span>
                <span class="px-2 py-0.5 rounded bg-muted-10">{{ $totalTokensOut }}</span>
            </div>
            <x-ui-button size="sm" variant="secondary-outline" @click="$wire.toggle()">Schließen</x-ui-button>
        </div>
        <div class="flex-1 overflow-auto p-2 space-y-2">
            @foreach($feed as $b)
                @if(($b['role'] ?? '') === 'user')
                    <div class="text-right">
                        <div class="inline-block bg-primary text-white px-2 py-1 rounded max-w-64 truncate">{{ $b['text'] ?? '' }}</div>
                    </div>
                @elseif(($b['type'] ?? '') === 'plan')
                    <div class="text-left text-xs bg-muted-10 px-2 py-2 rounded">
                        @php 
                            $items = is_array($b['data']) ? $b['data'] : []; 
                            $total = count($items);
                            $done = collect($items)->where('done', true)->count();
                            $pct = $total > 0 ? (int) floor(($done / $total) * 100) : 0;
                        @endphp
                        <div class="d-flex items-center justify-between mb-1">
                            <div class="font-medium">ToDo-Liste</div>
                            <div class="d-flex items-center gap-2">
                                <span class="text-muted-70">{{ $done }}/{{ $total }}</span>
                                <span class="px-2 py-0.5 rounded bg-muted-20">{{ $pct }}%</span>
                            </div>
                        </div>
                        <div class="w-full h-1 rounded bg-muted-20 overflow-hidden mb-2">
                            <div class="h-1 bg-primary" style="width: {{ $pct }}%"></div>
                        </div>
                        <ul class="space-y-1">
                            @foreach($items as $it)
                                <li class="d-flex items-center gap-2">
                                    @if(!empty($it['done']))
                                        <x-heroicon-o-check class="w-4 h-4 text-success-600" />
                                    @else
                                        <x-heroicon-o-clock class="w-4 h-4 text-muted-50" />
                                    @endif
                                    <span class="{{ !empty($it['done']) ? 'line-through text-gray-400' : '' }}">{{ $it['step'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @elseif(($b['type'] ?? '') === 'plan_status')
                    <div class="text-left text-2xs bg-muted-5 px-2 py-1 rounded">
                        <span class="font-medium">Plan-Status:</span>
                        <span>{{ $b['data']['text'] ?? '' }}</span>
                    </div>
                @elseif(($b['type'] ?? '') === 'confirm')
                    <div class="text-left text-sm bg-warning-50 px-2 py-1 rounded">
                        <div class="font-medium mb-1">Bestätigung erforderlich</div>
                        <div class="truncate">Intent: {{ $b['data']['intent'] ?? '' }}</div>
                        <div class="text-xs">Confidence: {{ (string)($b['data']['confidence'] ?? '') }}</div>
                        <div class="mt-2 d-flex items-center gap-2">
                            <x-ui-button size="sm" variant="primary" wire:click="confirmAndRun('{{ $b['data']['intent'] ?? '' }}', {{ json_encode($b['data']['slots'] ?? []) }})">Bestätigen</x-ui-button>
                        </div>
                    </div>
                @elseif(($b['type'] ?? '') === 'result')
                    <div class="text-left text-sm bg-success-50 px-2 py-1 rounded">
                        <div class="font-medium">Ergebnis</div>
                        <div class="truncate">{{ $b['data']['message'] ?? (($b['data']['ok'] ?? false) ? 'OK' : 'Fehler') }}</div>
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
                    <div class="text-left text-sm bg-muted-5 px-2 py-1 rounded">
                        <div class="truncate">{{ $b['data']['text'] ?? '' }}</div>
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


