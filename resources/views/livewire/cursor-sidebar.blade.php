<div x-data="rightSidebarState()" x-init="init()" class="d-flex">
    <aside 
        x-cloak
        :class="collapsed ? 'w-16 is-collapsed' : 'w-96 is-expanded'"
        class="relative flex-shrink-0 h-full bg-white border-left-1 border-left-solid border-muted transition-all duration-300 d-flex flex-col overflow-x-hidden"
    >
        <!-- Toggle -->
        <div class="sticky top-0 z-10 bg-white border-bottom-1 border-muted">
            <button 
                @click="toggle()" 
                class="w-full p-3 d-flex items-center justify-center bg-primary-10 transition"
                title="Sidebar umschalten"
            >
                <x-heroicon-o-chevron-double-right x-show="!collapsed" class="w-6 h-6 text-primary" />
                <x-heroicon-o-chevron-double-left x-show="collapsed" class="w-6 h-6 text-primary" />
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-2 d-flex flex-col gap-2">
            {{-- Zugefahrener Zustand: Nur Icons --}}
            <div x-show="collapsed" class="d-flex flex-col gap-2">
                {{-- Chat Icon --}}
                <button 
                    class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition text-black hover:bg-primary-10 hover:text-primary hover:shadow-md justify-center"
                    title="Chat">
                    <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 flex-shrink-0"/>
                </button>

                {{-- Commands Icon --}}
                <button 
                    class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition text-black hover:bg-primary-10 hover:text-primary hover:shadow-md justify-center"
                    title="Commands">
                    <x-heroicon-o-command-line class="w-6 h-6 flex-shrink-0"/>
                </button>

                {{-- Tools Icon --}}
                <button 
                    class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition text-black hover:bg-primary-10 hover:text-primary hover:shadow-md justify-center"
                    title="Tools">
                    <x-heroicon-o-wrench-screwdriver class="w-6 h-6 flex-shrink-0"/>
                </button>
            </div>

            {{-- Ausgeklappter Zustand: Chat-Funktionalität --}}
            <div x-show="!collapsed" class="d-flex flex-col h-full">
                {{-- Chat Header --}}
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
                </div>

                {{-- Chat Messages --}}
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

                {{-- Chat Input --}}
                <div class="px-3 py-2 border-top-1 d-flex items-center gap-2">
                    <input type="text" class="flex-grow border rounded px-2 py-1" placeholder="Nachricht…" wire:model.defer="input" wire:keydown.enter="send">
                    <x-ui-button size="sm" variant="primary" wire:click="send">Senden</x-ui-button>
                </div>
            </div>
        </div>
    </aside>
</div>

<script>
function rightSidebarState() {
    return {
        collapsed: true, // Standardmäßig zugefahren
        init() {
            this.collapsed = localStorage.getItem('sidebar-cursor-collapsed') === 'true' || true;
            window.dispatchEvent(new CustomEvent('ui:right-sidebar-toggle', { detail: { collapsed: this.collapsed } }));
        },
        toggle() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebar-cursor-collapsed', this.collapsed);
            window.dispatchEvent(new CustomEvent('ui:right-sidebar-toggle', { detail: { collapsed: this.collapsed } }));
        }
    }
}
</script>