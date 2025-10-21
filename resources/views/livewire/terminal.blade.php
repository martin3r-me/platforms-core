<div
    x-data="{
        get open(){ return Alpine?.store('page')?.terminalOpen ?? false },
        toggle(){ Alpine?.store('page') && (Alpine.store('page').terminalOpen = !Alpine.store('page').terminalOpen) },
        es: null,
        streamText: '',
        startStream(url){
            if(this.es){ this.es.close(); this.es = null; }
            try {
                console.log('[Terminal SSE] startStream →', url);
                this.streamText = '';
                this.es = new EventSource(url);
                this.es.onopen = () => { console.log('[Terminal SSE] connection open'); };
                this.es.onmessage = (e) => {
                    if(!e.data) return;
                    console.log('[Terminal SSE] onmessage raw:', e.data);
                    if(e.data === '[DONE]') { console.log('[Terminal SSE] DONE'); this.es.close(); this.es = null; window.dispatchEvent(new CustomEvent('ai-stream-complete')); return; }
                    try {
                        const data = JSON.parse(e.data);
                        if(data.delta){
                            console.log('[Terminal SSE] delta:', data.delta);
                            window.dispatchEvent(new CustomEvent('ai-stream-delta', { detail: { delta: data.delta } }));
                        }
                    } catch(parseErr) {
                        console.warn('[Terminal SSE] parse error:', parseErr);
                    }
                };
                this.es.onerror = (err) => { console.error('[Terminal SSE] error:', err); this.es && this.es.close(); this.es = null; window.dispatchEvent(new CustomEvent('ai-stream-error')); };
            } catch(_) {}
        }
    }"
    x-on:toggle-terminal.window="toggle()"
    x-on:ai-stream-start.window="console.log('[Terminal SSE] ai-stream-start', $event.detail?.url); startStream($event.detail.url)"
    x-on:ai-stream-delta.window="console.log('[Terminal SSE] ai-stream-delta event'); streamText += $event.detail.delta; $nextTick(() => { const c = $el.querySelector('[data-terminal-body]'); if(c){ c.scrollTop = c.scrollHeight } })"
    x-on:ai-stream-complete.window="console.log('[Terminal SSE] ai-stream-complete'); $wire.set('isProcessing', false); $wire.set('isStreaming', false); $wire.set('canCancel', false); $wire.set('progressText', ''); $wire.call('loadMessages')"
    x-on:ai-stream-error.window="console.log('[Terminal SSE] ai-stream-error'); $wire.set('isProcessing', false); $wire.set('isStreaming', false); $wire.set('canCancel', false); $wire.set('progressText', ''); $wire.call('loadMessages')"
    x-on:ai-stream-error.window="$wire.set('isProcessing', false); $wire.set('isStreaming', false); $wire.set('canCancel', false); $wire.set('progressText', ''); $wire.call('loadMessages')"
    class="w-full"
>

    <!-- Slide container (wie Sidebars: Größe animiert) -->
    <div
        class="w-full border-t border-[var(--ui-border)]/60 bg-[var(--ui-surface)]/95 backdrop-blur overflow-hidden transition-[max-height] duration-300 ease-out flex flex-col"
        x-bind:style="open ? 'max-height: 14rem' : 'max-height: 0px'"
        style="max-height: 0px;"
    >
        <!-- Header -->
        <div class="h-10 px-3 flex items-center justify-between text-xs border-b border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200" :class="open ? 'opacity-100' : 'opacity-0'">
            <div class="flex items-center gap-2 text-[var(--ui-muted)]">
                @svg('heroicon-o-command-line', 'w-4 h-4')
                <span>Terminal</span>
            </div>
            <div class="flex items-center gap-1">
                <button @click="toggle()" class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[var(--ui-muted)] hover:text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition">
                    @svg('heroicon-o-x-mark','w-4 h-4')
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center border-b border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] opacity-100 transition-opacity duration-200" :class="open ? 'opacity-100' : 'opacity-0'">
            <div class="flex items-center overflow-x-auto">
                @foreach($chats as $chat)
                    <div class="flex items-center border-r border-[var(--ui-border)]/60">
                        <button 
                            wire:click="setActiveChat({{ $chat['id'] }})"
                            class="flex items-center gap-2 px-3 py-2 text-xs transition-colors min-w-0"
                            :class="$wire.activeChatId == {{ $chat['id'] }} 
                                ? 'text-[var(--ui-primary)] bg-[var(--ui-surface)]' 
                                : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]'"
                        >
                            <span class="truncate max-w-20">{{ $chat['title'] ?: 'Chat ' . $chat['id'] }}</span>
                            <button 
                                wire:click="deleteChat({{ $chat['id'] }})"
                                class="inline-flex items-center justify-center w-4 h-4 rounded hover:bg-[var(--ui-danger-5)] hover:text-[var(--ui-danger)] transition-colors"
                                title="Chat löschen"
                            >
                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                            </button>
                        </button>
                    </div>
                @endforeach
                
                <!-- New Chat Button -->
                <button 
                    wire:click="createNewChat"
                    class="flex items-center gap-2 px-3 py-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                    title="Neuen Chat erstellen"
                >
                    @svg('heroicon-o-plus', 'w-4 h-4')
                </button>
            </div>
        </div>

        <!-- Body -->
        <div class="flex-1 min-h-0 overflow-y-auto px-3 py-2 text-xs font-mono text-[var(--ui-secondary)] opacity-100 transition-opacity duration-200" :class="open ? 'opacity-100' : 'opacity-0'" data-terminal-body>
            @if(empty($messages))
                <div class="text-[var(--ui-muted)]">Tippe "help" für verfügbare Befehle…</div>
                <div class="mt-2 space-y-1">
                    <div>$ help</div>
                    <div>- kpi            Zeigt Team-KPIs</div>
                    <div>- tasks --mine   Eigene Aufgaben</div>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($messages as $message)
                        <div class="flex items-start gap-2">
                            <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">
                                {{ $message['role'] === 'user' ? 'User' : 'AI' }}:
                            </span>
                            <span class="text-[var(--ui-secondary)] text-xs break-words">
                                {{ $message['content'] }}
                            </span>
                        </div>
                    @endforeach
                    
                    <!-- Progress Indicator -->
                    @if($isProcessing)
                        <div class="flex items-start gap-2">
                            <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">AI:</span>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 border-2 border-[var(--ui-primary)] border-t-transparent rounded-full animate-spin"></div>
                                <span class="text-[var(--ui-secondary)] text-xs">{{ $progressText ?: 'Verarbeite...' }}</span>
                            </div>
                            @if($currentTool)
                                <div class="text-xs text-[var(--ui-muted)]">
                                    (Tool: {{ $currentTool }})
                                </div>
                            @endif
                        </div>
                        <!-- Live Streaming Bubble -->
                        <div class="flex items-start gap-2">
                            <span class="text-[var(--ui-muted)] text-xs font-bold min-w-0 flex-shrink-0">AI:</span>
                            <span class="text-[var(--ui-secondary)] text-xs break-words" x-text="streamText"></span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Prompt -->
        <div class="h-10 px-3 flex items-center gap-2 border-t border-[var(--ui-border)]/60 opacity-100 transition-opacity duration-200 flex-shrink-0" :class="open ? 'opacity-100' : 'opacity-0'">
            <span class="text-[var(--ui-muted)] text-xs font-mono">$</span>
            <input 
                type="text" 
                wire:model="messageInput"
                wire:keydown.enter="sendMessage"
                class="flex-1 bg-transparent outline-none text-sm text-[var(--ui-secondary)] placeholder-[var(--ui-muted)]" 
                placeholder="{{ $isProcessing ? 'Verarbeite...' : 'Nachricht eingeben…' }}"
                {{ $isProcessing ? 'disabled' : '' }}
            />
            @if($canCancel)
                <button 
                    wire:click="cancelRequest"
                    class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-danger)]/60 text-[var(--ui-danger)] hover:bg-[var(--ui-danger-5)] transition"
                >
                    Abbrechen
                </button>
            @else
                <button 
                    wire:click="sendMessage"
                    class="inline-flex items-center justify-center h-8 px-3 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-muted-5)] transition"
                    {{ $isProcessing ? 'disabled' : '' }}
                >
                    Senden
                </button>
            @endif
        </div>
    </div>
    <script>
        window.addEventListener('ai-stream-delta', (e) => {
            // Optional: could push delta into a live placeholder; server already persists final message
        });
        window.addEventListener('ai-stream-complete', () => {
            // Could trigger a Livewire refresh if needed
            window.Livewire && window.Livewire.dispatch && window.Livewire.dispatch('refresh');
        });
    </script>
</div>
