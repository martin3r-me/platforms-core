<div x-data="{ open: @entangle('open'), collapsed: @entangle('collapsed'), input: @entangle('input'), textareaHeight: 'auto' }" 
     x-init="$watch('input', () => { 
         $nextTick(() => { 
             let textarea = $refs.textarea;
             textarea.style.height = 'auto';
             textarea.style.height = textarea.scrollHeight + 'px';
             textareaHeight = textarea.scrollHeight + 'px';
         });
     });"
     class="h-screen flex flex-col bg-white shadow-lg z-50"
     :class="open ? 'w-96' : 'w-16'">

    {{-- Collapsed State (Icons Only) --}}
    <template x-if="collapsed">
        <div class="flex flex-col items-center justify-between h-full py-4">
            <div class="space-y-4">
                <button @click="toggle()" class="p-2 rounded-full hover:bg-gray-100">
                    <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 text-gray-600" />
                </button>
                {{-- Add other icons here --}}
            </div>
        </div>
    </template>

    {{-- Expanded State (Chat Interface) --}}
    <template x-if="!collapsed">
        <div class="flex flex-col h-full">
            {{-- Header --}}
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Intelligent Agent</h2>
                <button @click="toggle()" class="p-2 rounded-full hover:bg-gray-100">
                    <x-heroicon-o-x-mark class="w-6 h-6 text-gray-600" />
                </button>
            </div>

            {{-- Chat Messages --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="chatMessages">
                @foreach($feed as $message)
                    <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-xs px-4 py-2 rounded-lg {{ $message['role'] === 'user' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800' }}">
                            <div class="text-sm">{{ $message['text'] ?? $message['content'] ?? '' }}</div>
                            <div class="text-xs mt-1 opacity-70">
                                {{ isset($message['created_at']) ? \Carbon\Carbon::parse($message['created_at'])->format('H:i') : '' }}
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Live Agent Activities Stream --}}
                @if($showActivityStream && $currentStep)
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

            {{-- Context Panel --}}
            @if(!empty($currentContext))
                <div class="p-3 bg-gray-50 border-t border-gray-200">
                    <div class="flex items-center justify-between cursor-pointer" wire:click="toggleContextPanel">
                        <h3 class="text-sm font-medium text-gray-700">
                            Context
                            @if($includeContext)
                                <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">Aktiv</span>
                            @else
                                <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-800 text-xs rounded-full">Aus</span>
                            @endif
                        </h3>
                        <button class="text-gray-500 hover:text-gray-700">
                            <x-heroicon-o-chevron-down x-show="!contextPanelOpen" class="w-4 h-4" />
                            <x-heroicon-o-chevron-up x-show="contextPanelOpen" class="w-4 h-4" />
                        </button>
                    </div>

                    <div x-show="contextPanelOpen" x-collapse class="mt-2 text-xs text-gray-600 space-y-1">
                        @if($currentModel) <div><strong>Model:</strong> {{ $currentModel }}</div> @endif
                        @if($currentModelId) <div><strong>ID:</strong> {{ $currentModelId }}</div> @endif
                        @if($currentSubject) <div><strong>Betreff:</strong> {{ $currentSubject }}</div> @endif
                        @if($currentUrl) <div><strong>URL:</strong> <a href="{{ $currentUrl }}" target="_blank" class="text-blue-500 hover:underline">{{ $currentUrl }}</a></div> @endif
                        @if(isset($currentContext['source'])) <div><strong>Source:</strong> {{ $currentContext['source'] }}</div> @endif
                        @if(isset($currentContext['meta']) && is_array($currentContext['meta']))
                            @foreach($currentContext['meta'] as $key => $value)
                                @if($value) <div><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</div> @endif
                            @endforeach
                        @endif
                        <div class="flex gap-2 mt-2">
                            <button wire:click="toggleContext" class="text-xs px-2 py-1 rounded {{ $includeContext ? 'bg-red-100 text-red-800 hover:bg-red-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}">
                                {{ $includeContext ? 'Deaktivieren' : 'Aktivieren' }}
                            </button>
                            <button wire:click="clearContext" class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-800 hover:bg-gray-200">
                                Context löschen
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Chat Input --}}
            <div class="p-4 border-t border-gray-200 bg-white sticky bottom-0">
                <div class="flex items-end gap-2">
                    <textarea 
                        x-ref="textarea"
                        class="flex-grow border rounded-lg px-3 py-2 resize-none overflow-hidden focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        placeholder="Nachricht…" 
                        wire:model.live="input" 
                        wire:keydown.enter.prevent="send"
                        :style="{ height: textareaHeight }"
                        rows="1"
                    ></textarea>
                    <x-ui-button size="sm" variant="primary" wire:click="send">Senden</x-ui-button>
                </div>
            </div>
        </div>
    </template>
</div>