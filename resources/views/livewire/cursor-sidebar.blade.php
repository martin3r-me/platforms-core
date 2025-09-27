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
                @elseif(($b['type'] ?? '') === 'message')
                    <div class="text-left text-sm bg-muted-5 px-2 py-1 rounded">
                        <div class="truncate">{{ $b['data']['text'] ?? '' }}</div>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="px-3 py-2 border-top-1 d-flex items-center gap-2">
            <input type="text" class="flex-grow border rounded px-2 py-1" placeholder="Nachricht…" wire:model.defer="input" wire:keydown.enter="send">
            <x-ui-button size="sm" variant="primary" wire:click="send">Senden</x-ui-button>
        </div>
        </x-ui-right-sidebar>
    </div>
</div>