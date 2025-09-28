<div>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>

    <aside class="h-screen bg-white border-l border-gray-200 flex flex-col" x-data="rightSidebarState()">
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium text-gray-900">AI Assistant</h3>
                <button @click="toggle()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Chat Content -->
        <div x-show="!collapsed" class="flex-1 flex flex-col">
            <!-- Messages Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @foreach($feed as $message)
                    <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg {{ $message['role'] === 'user' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-900' }}">
                            <div class="text-sm">{{ $message['text'] ?? $message['content'] ?? '' }}</div>
                            <div class="text-xs mt-1 opacity-70">
                                {{ isset($message['created_at']) ? \Carbon\Carbon::parse($message['created_at'])->format('H:i') : '' }}
                            </div>
                        </div>
                    </div>
                @endforeach

                <!-- Current Step Display -->
                @if($isWorking && $currentStep)
                    <div class="flex justify-start">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg bg-green-100 text-green-800">
                            <div class="flex items-center gap-2">
                                <span class="animate-pulse">🔄</span>
                                <span class="text-sm font-medium">{{ $currentStep }}</span>
                                @if($stepNumber && $totalSteps)
                                    <span class="text-xs opacity-70">({{ $stepNumber }}/{{ $totalSteps }})</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Input Area - Fixed at Bottom -->
            <div class="border-t border-gray-200 bg-white p-4">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <textarea 
                            wire:model.live="input" 
                            wire:keydown.enter.prevent="send"
                            placeholder="Nachricht eingeben..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            rows="1"
                            x-data="{
                                resize() {
                                    this.style.height = 'auto';
                                    this.style.height = this.scrollHeight + 'px';
                                }
                            }"
                            x-init="resize()"
                            @input="resize()"
                        ></textarea>
                    </div>
                    <button 
                        wire:click="send" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="$wire.isWorking"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Collapsed State - Icons Only -->
        <div x-show="collapsed" class="flex-1 flex flex-col items-center justify-center space-y-4 p-4">
            <button @click="toggle()" class="p-3 rounded-lg bg-gray-100 hover:bg-gray-200">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </button>
        </div>
    </aside>

    <script>
        function rightSidebarState() {
            return {
                collapsed: true,
                toggle() {
                    this.collapsed = !this.collapsed;
                }
            }
        }
    </script>
</div>