<x-ui.right-sidebar>
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

            <!-- Live Agent Activities Stream -->
            @if($showActivityStream && !empty($agentActivities))
                <div class="space-y-1 max-h-32 overflow-y-auto">
                    @foreach($agentActivities as $index => $activity)
                        <div class="text-xs bg-blue-50 px-2 py-1 rounded border-l-2 border-blue-300">
                            <div class="flex items-center gap-2">
                                <span class="{{ $activity['status'] === 'running' ? 'animate-pulse' : '' }}">
                                    {{ $activity['icon'] ?? '🔄' }}
                                </span>
                                <span class="text-blue-700 font-medium">{{ $activity['step'] ?? $activity['message'] }}</span>
                                @if(isset($activity['duration']) && $activity['duration'] > 0)
                                    <span class="text-blue-500 text-xs">({{ number_format($activity['duration'], 0) }}ms)</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            
            <!-- Current Step Display -->
            @if($isWorking && $currentStep)
                <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded text-xs">
                    <div class="flex items-center gap-2">
                        <span class="animate-pulse">🔄</span>
                        <span class="text-green-700 font-medium">{{ $currentStep }}</span>
                        @if($stepNumber && $totalSteps)
                            <span class="text-gray-500">({{ $stepNumber }}/{{ $totalSteps }})</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Progress Bar -->
        @if($isWorking && $totalSteps > 0)
            <div class="bg-gray-100 rounded p-2 mx-4 mb-2">
                <div class="flex justify-between text-xs text-gray-600 mb-1">
                    <span>Agent arbeitet...</span>
                    <span>{{ $currentStep }} / {{ $totalSteps }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                         style="width: {{ $totalSteps > 0 ? ($currentStep / $totalSteps) * 100 : 0 }}%"></div>
                </div>
            </div>
        @endif

        <!-- Context Panel -->
        @if(!empty($currentContext))
        <div class="mx-4 mb-2">
            <div class="bg-yellow-50 border border-yellow-200 rounded p-2 text-xs">
                <div class="font-bold text-yellow-800 mb-1">🔍 DEBUG CONTEXT:</div>
                <div class="text-yellow-700">
                    <div><strong>Model:</strong> {{ $currentModel ?? 'null' }}</div>
                    <div><strong>Model ID:</strong> {{ $currentModelId ?? 'null' }}</div>
                    <div><strong>Subject:</strong> {{ $currentSubject ?? 'null' }}</div>
                    <div><strong>URL:</strong> {{ $currentUrl ?? 'null' }}</div>
                    <div><strong>Source:</strong> {{ $currentContext['source'] ?? 'null' }}</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Context Panel Toggle -->
        @if(!empty($currentContext))
        <div class="mx-4 mb-2">
            <div class="bg-gray-50 border border-gray-200 rounded p-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-600">Context Panel</span>
                    <button 
                        wire:click="toggleContextPanel" 
                        class="text-xs px-2 py-1 rounded {{ $contextPanelOpen ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}"
                    >
                        {{ $contextPanelOpen ? 'Ausblenden' : 'Anzeigen' }}
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- Context Panel Content -->
        @if($contextPanelOpen && !empty($currentContext))
        <div class="mx-4 mb-2 p-3 bg-white border border-gray-200 rounded text-xs">
            <div class="space-y-2">
                @if($currentModel)
                <div>
                    <span class="font-medium text-gray-700">Model:</span>
                    <span class="text-gray-600">{{ $currentModel }}</span>
                </div>
                @endif
                
                @if($currentModelId)
                <div>
                    <span class="font-medium text-gray-700">ID:</span>
                    <span class="text-gray-600">{{ $currentModelId }}</span>
                </div>
                @endif
                
                @if($currentSubject)
                <div>
                    <span class="font-medium text-gray-700">Betreff:</span>
                    <span class="text-gray-600">{{ $currentSubject }}</span>
                </div>
                @endif
                
                @if($currentUrl)
                <div>
                    <span class="font-medium text-gray-700">URL:</span>
                    <span class="text-gray-600">{{ $currentUrl }}</span>
                </div>
                @endif
                
                @if(isset($currentContext['meta']))
                <div>
                    <span class="font-medium text-gray-700">Meta:</span>
                    <span class="text-gray-600">{{ json_encode($currentContext['meta']) }}</span>
                </div>
                @endif
            </div>
            
            <div class="mt-3 flex gap-2">
                <button 
                    wire:click="toggleContext" 
                    class="text-xs px-2 py-1 rounded {{ $includeContext ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}"
                >
                    {{ $includeContext ? 'Aktiv' : 'Aus' }}
                </button>
                <button 
                    wire:click="clearContext" 
                    class="text-xs px-2 py-1 rounded bg-red-100 text-red-800 hover:bg-red-200"
                >
                    ✕
                </button>
            </div>
        </div>
        @endif

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
</x-ui.right-sidebar>