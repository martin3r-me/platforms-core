<div class="flex flex-col h-screen" x-data="simpleToolPlayground()" x-init="init()">
    <!-- Header -->
    <div class="border-b border-[var(--ui-border)] p-4">
        <h1 class="text-xl font-semibold text-[var(--ui-secondary)]">Simple Tool Playground</h1>
        <p class="text-sm text-[var(--ui-muted)] mt-1">Minimaler Chat mit Tools & SSE</p>
    </div>

    <!-- Chat Messages -->
    <div class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="messages">
        <template x-for="(msg, index) in messages" :key="index">
            <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                <div class="max-w-3xl rounded-lg p-3" 
                     :class="msg.role === 'user' 
                        ? 'bg-[var(--ui-primary)] text-white' 
                        : 'bg-[var(--ui-surface)] border border-[var(--ui-border)]'">
                    <div class="text-sm font-semibold mb-1" x-text="msg.role === 'user' ? 'Du' : 'Assistant'"></div>
                    <div class="whitespace-pre-wrap" x-html="msg.content"></div>
                </div>
            </div>
        </template>

        <!-- Streaming Content -->
        <div x-show="streamingContent" class="flex justify-start">
            <div class="max-w-3xl rounded-lg p-3 bg-[var(--ui-surface)] border border-[var(--ui-border)]">
                <div class="text-sm font-semibold mb-1">Assistant</div>
                <div class="whitespace-pre-wrap" x-html="streamingContent"></div>
                <div class="mt-2 text-xs text-[var(--ui-muted)] animate-pulse">●</div>
            </div>
        </div>
    </div>

    <!-- Input -->
    <div class="border-t border-[var(--ui-border)] p-4">
        <form @submit.prevent="sendMessage()" class="flex gap-2">
            <input 
                type="text" 
                x-model="inputMessage"
                :disabled="isStreaming"
                :placeholder="isStreaming ? 'Verarbeite...' : 'Nachricht eingeben...'"
                class="flex-1 px-4 py-2 border border-[var(--ui-border)] rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]"
            />
            <button 
                type="submit"
                :disabled="isStreaming || !inputMessage.trim()"
                class="px-6 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:bg-opacity-90 disabled:opacity-50"
            >
                Senden
            </button>
        </form>
    </div>
</div>

<script>
function simpleToolPlayground() {
    return {
        messages: @js($messages),
        inputMessage: '',
        streamingContent: '',
        isStreaming: false,
        eventSource: null,

        init() {
            // Livewire Events
            Livewire.on('start-stream', (data) => {
                this.startStream(data.message);
            });
        },

        sendMessage() {
            if (!this.inputMessage.trim() || this.isStreaming) {
                return;
            }

            const userMessage = this.inputMessage.trim();
            this.inputMessage = '';
            
            // Füge User-Message hinzu
            this.messages.push({
                role: 'user',
                content: userMessage,
            });

            this.isStreaming = true;
            this.startStream(userMessage);
        },

        startStream(userMessage) {
            // Alte Verbindung schließen
            if (this.eventSource) {
                this.eventSource.close();
            }

            this.streamingContent = '';
            const url = '{{ route("core.tools.simple.stream") }}';

            // POST Request mit Fetch (SSE)
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ message: userMessage }),
            }).then(response => {
                if (!response.ok) {
                    throw new Error('Stream failed');
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let currentEvent = null;

                const readChunk = () => {
                    reader.read().then(({ done, value }) => {
                        if (done) {
                            this.finishStream();
                            return;
                        }

                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop() || '';

                        for (const line of lines) {
                            if (line.startsWith('event: ')) {
                                currentEvent = line.substring(7).trim();
                                continue;
                            }

                            if (line.startsWith('data: ')) {
                                try {
                                    const data = JSON.parse(line.substring(6));
                                    this.handleEvent(currentEvent, data);
                                } catch (e) {
                                    console.warn('Failed to parse SSE data:', line);
                                }
                            }
                        }

                        readChunk();
                    }).catch(err => {
                        console.error('Stream error:', err);
                        this.finishStream();
                    });
                };

                readChunk();
            }).catch(err => {
                console.error('Fetch error:', err);
                this.finishStream();
            });
        },

        handleEvent(eventType, data) {
            // Content Updates
            if (eventType === 'llm.content' && data.content) {
                this.streamingContent = data.content;
                this.scrollToBottom();
            }

            if (eventType === 'llm.delta' && data.delta) {
                this.streamingContent = (this.streamingContent || '') + data.delta;
                this.scrollToBottom();
            }

            // Tool Events
            if (eventType === 'tool.start' && data.tool) {
                console.log(`[Tool] Start: ${data.tool}`);
            }

            if (eventType === 'tool.complete' && data.tool) {
                console.log(`[Tool] Complete: ${data.tool}`);
            }

            // Completion
            if (eventType === 'complete') {
                if (data.message) {
                    this.messages.push({
                        role: 'assistant',
                        content: data.message,
                    });
                }
                this.finishStream();
            }

            // Errors
            if (eventType === 'error') {
                this.messages.push({
                    role: 'assistant',
                    content: `❌ Fehler: ${data.error || 'Unbekannter Fehler'}`,
                });
                this.finishStream();
            }
        },

        finishStream() {
            this.streamingContent = '';
            this.eventSource = null;
            this.isStreaming = false;
            @this.set('isStreaming', false);
            this.scrollToBottom();
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const messagesEl = this.$refs.messages;
                if (messagesEl) {
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }
            });
        },
    };
}
</script>
