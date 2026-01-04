<div class="flex flex-col h-screen">
    <!-- Header -->
    <div class="border-b border-[var(--ui-border)] p-4">
        <h1 class="text-xl font-semibold text-[var(--ui-secondary)]">Simple Tool Playground</h1>
        <p class="text-sm text-[var(--ui-muted)] mt-1">Minimaler Chat mit Tools & SSE</p>
    </div>

    <!-- Chat Messages -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="messages" x-data="{ scrollToBottom() { this.$nextTick(() => { const el = this.$refs.messages; if(el) el.scrollTop = el.scrollHeight; }); } }">
        @foreach($messages as $msg)
            <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-3xl rounded-lg p-3 {{ $msg['role'] === 'user' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-surface)] border border-[var(--ui-border)]' }}">
                    <div class="text-sm font-semibold mb-1">{{ $msg['role'] === 'user' ? 'Du' : 'Assistant' }}</div>
                    <div class="whitespace-pre-wrap">{{ $msg['content'] }}</div>
                </div>
            </div>
        @endforeach

        <!-- Streaming Content -->
        @if($isStreaming || !empty($streamingContent))
            <div class="flex justify-start">
                <div class="max-w-3xl rounded-lg p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]">
                    <div class="text-sm font-semibold mb-1">Assistant</div>
                    <div class="whitespace-pre-wrap" wire:live>{{ $streamingContent }}</div>
                    @if($currentTool)
                        <div class="mt-2 text-xs text-[var(--ui-muted)]">🔧 {{ $currentTool }}</div>
                    @endif
                    @if($isStreaming)
                        <div class="mt-2 text-xs text-[var(--ui-muted)] animate-pulse">●</div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Input -->
    <div class="border-t border-[var(--ui-border)] p-4">
        <form wire:submit.prevent="sendMessage" class="flex gap-2">
            <input 
                type="text" 
                wire:model.live="message"
                @disabled($isStreaming)
                placeholder="{{ $isStreaming ? 'Verarbeite...' : 'Nachricht eingeben...' }}"
                class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)] disabled:opacity-50"
            />
            <button 
                type="submit"
                @disabled($isStreaming || empty(trim($message)))
                class="px-6 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50"
            >
                Senden
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('livewire:init', () => {
    let streamReader = null;
    let isStreaming = false;

    // SSE Stream Handler
    Livewire.on('start-stream', (data) => {
        if (isStreaming) {
            if (streamReader) {
                streamReader.cancel();
            }
        }

        isStreaming = true;
        const userMessage = data.message;
        const url = '{{ route("core.tools.simple.stream") }}';
        
        // Bereite Chat-Historie vor (ohne letzte User-Message)
        const chatHistory = @js($messages);
        const historyForRequest = chatHistory.slice(0, -1).map(msg => ({
            role: msg.role,
            content: msg.content,
        }));

        // POST Request mit Fetch (SSE)
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ 
                message: userMessage,
                chat_history: historyForRequest,
            }),
        }).then(response => {
            if (!response.ok) {
                throw new Error('Stream failed');
            }

            const reader = response.body.getReader();
            streamReader = reader;
            const decoder = new TextDecoder();
            let buffer = '';
            let currentEvent = null;

            const readChunk = () => {
                reader.read().then(({ done, value }) => {
                    if (done) {
                        isStreaming = false;
                        return;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (line.trim() === '') continue;
                        
                        if (line.startsWith('event: ')) {
                            currentEvent = line.substring(7).trim();
                            continue;
                        }

                        if (line.startsWith('data: ')) {
                            try {
                                const data = JSON.parse(line.substring(6));
                                handleSSEEvent(currentEvent || 'message', data);
                            } catch (e) {
                                console.warn('Failed to parse SSE data:', line, e);
                            }
                        }
                    }

                    readChunk();
                }).catch(err => {
                    console.error('Stream error:', err);
                    isStreaming = false;
                    @this.call('handleStreamError', err.message);
                });
            };

            readChunk();
        }).catch(err => {
            console.error('Fetch error:', err);
            isStreaming = false;
            @this.call('handleStreamError', err.message);
        });
    });

    function handleSSEEvent(eventType, data) {
        // Content Updates (Streaming)
        if (eventType === 'llm.delta' && data.delta) {
            @this.call('handleStreamDelta', data.delta);
        }

        if (eventType === 'llm.content' && data.content) {
            // Setze kompletten Content
            @this.set('streamingContent', data.content);
        }

        // Tool Events
        if (eventType === 'tool.start' && data.tool) {
            @this.call('handleToolStart', data.tool);
        }

        if (eventType === 'tool.complete' && data.tool) {
            @this.call('handleToolComplete', data.tool);
        }

        if (eventType === 'tool.error' && data.tool) {
            @this.set('streamingContent', @this.get('streamingContent') + `\n❌ Fehler: ${data.error || 'Unbekannter Fehler'}\n`);
        }

        // Completion
        if (eventType === 'complete') {
            const content = data.message || @this.get('streamingContent');
            @this.call('handleStreamComplete', content, data.chat_history);
            isStreaming = false;
        }

        // Errors
        if (eventType === 'error') {
            const errorMsg = data.error || 'Unbekannter Fehler';
            @this.call('handleStreamError', errorMsg);
            isStreaming = false;
        }
    }
});
</script>
