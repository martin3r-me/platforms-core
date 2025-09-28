<div>
    <div x-data="rightSidebarState()" x-init="init()" class="d-flex">
        <aside 
            x-cloak
            :class="collapsed ? 'w-16 is-collapsed' : 'w-96 is-expanded'"
            class="relative flex-shrink-0 h-screen bg-white border-left-1 border-left-solid border-muted transition-all duration-300 d-flex flex-col overflow-hidden"
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
            
            {{-- DEBUG: Context Info --}}
            @if(!empty($currentContext))
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
            @endif
            {{-- Zugefahrener Zustand: Nur Icons --}}
            <template x-if="collapsed">
                <div class="d-flex flex-col gap-2">
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
            </template>

            {{-- Ausgeklappter Zustand: Chat-Funktionalität --}}
            <template x-if="!collapsed">
                <div class="d-flex flex-col h-full">
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
                                     @elseif(($b['type'] ?? '') === 'activity')
                                         <div class="text-left text-xs bg-blue-50 px-2 py-1 rounded border-l-2 border-blue-300">
                                             <div class="flex items-center gap-2">
                                                 <span class="animate-pulse">🔄</span>
                                                 <span class="text-blue-700">{{ $b['data']['text'] ?? '' }}</span>
                                             </div>
                                         </div>
                                     @endif
                                 @endforeach
                                 
                                {{-- Live Agent Activities Stream --}}
                                @if($showActivityStream && !empty($agentActivities))
                                    <div class="space-y-1 max-h-32 overflow-y-auto">
                                        @foreach($agentActivities as $index => $activity)
                                            <div class="text-xs bg-blue-50 px-2 py-1 rounded border-l-2 border-blue-300 animate-fade-in">
                                                <div class="flex items-center gap-2">
                                                    <span class="{{ $activity['status'] === 'running' ? 'animate-pulse' : '' }}">
                                                        {{ $activity['icon'] ?? '🔄' }}
                                                    </span>
                                                    <span class="text-blue-700 font-medium">{{ $activity['step'] ?? $activity['message'] }}</span>
                                                    @if(isset($activity['duration']) && $activity['duration'] > 0)
                                                        <span class="text-gray-500">({{ number_format($activity['duration'], 2) }}s)</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                
                                {{-- Current Step Display --}}
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
                                     <div class="space-y-1 max-h-32 overflow-y-auto">
                                         @foreach($agentActivities as $index => $activity)
                                             <div class="text-xs bg-blue-50 px-2 py-1 rounded border-l-2 border-blue-300 animate-fade-in">
                                                 <div class="flex items-center gap-2">
                                                     <span class="{{ $activity['status'] === 'running' ? 'animate-pulse' : '' }}">
                                                         {{ $activity['icon'] ?? '🔄' }}
                                                     </span>
                                                     <span class="text-blue-700 font-medium">{{ $activity['step'] ?? $activity['message'] }}</span>
                                                     @if(isset($activity['duration']) && $activity['duration'] > 0)
                                                         <span class="text-blue-500 text-xs">({{ number_format($activity['duration'], 0) }}ms)</span>
                                                     @endif
                                                     @if(isset($activity['timestamp']))
                                                         <span class="text-blue-400 text-xs ml-auto">{{ $activity['timestamp'] }}</span>
                                                     @endif
                                                 </div>
                                             </div>
                                         @endforeach
                                     </div>
                                 @endif
                                 
                                 {{-- Progress Bar --}}
                                 @if($isWorking && $totalSteps > 0)
                                     <div class="bg-gray-100 rounded p-2">
                                         <div class="flex justify-between text-xs text-gray-600 mb-1">
                                             <span>Agent arbeitet...</span>
                                             <span>{{ $currentStep }} / {{ $totalSteps }}</span>
                                         </div>
                                         <div class="w-full bg-gray-200 rounded-full h-2">
                                             <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" 
                                                  style="width: {{ $totalSteps > 0 ? ($currentStep / $totalSteps) * 100 : 0 }}%"></div>
                                         </div>
                                     </div>
                                 @endif
                             </div>

                {{-- Chat Input --}}
                <div class="px-3 py-2 border-top-1 d-flex items-center gap-2">
                    <input type="text" class="flex-grow border rounded px-2 py-1" placeholder="Nachricht…" wire:model.defer="input" wire:keydown.enter="send">
                    <x-ui-button size="sm" variant="primary" wire:click="send">Senden</x-ui-button>
                </div>
                </div>
            </template>
        </div>
        </aside>
    </div>
    
    <style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeIn 0.3s ease-in-out;
    }
    </style>

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
    
    <!-- Context Panel -->
    @if(!$collapsed)
    <div class="border-t border-gray-200 bg-gray-50">
        <!-- Context Panel Toggle -->
        <div class="flex items-center justify-between p-2">
            <button 
                wire:click="toggleContextPanel" 
                class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-800"
            >
                <span>📋</span>
                <span>Context</span>
                <span class="text-xs">{{ $contextPanelOpen ? '▼' : '▶' }}</span>
            </button>
            
            @if(!empty($currentContext))
            <div class="flex space-x-1">
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
            @endif
        </div>
        
        <!-- Context Panel Content -->
        @if($contextPanelOpen && !empty($currentContext))
        <div class="p-3 bg-white border-t border-gray-200 text-xs">
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
                    <span class="text-gray-600 text-xs break-all">{{ $currentUrl }}</span>
                </div>
                @endif
                
                @if(isset($currentContext['meta']))
                <div>
                    <span class="font-medium text-gray-700">Meta:</span>
                    <div class="mt-1 space-y-1">
                        @foreach($currentContext['meta'] as $key => $value)
                        @if($value)
                        <div class="text-gray-600">
                            <span class="capitalize">{{ $key }}:</span> {{ $value }}
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
    @endif
</div>