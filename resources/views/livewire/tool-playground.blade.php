<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="🔧 Tool Playground (MCP Testing)" icon="heroicon-o-wrench-screwdriver" />
    </x-slot>

    <x-ui-page-container>
        <div>
            <h1 class="text-2xl font-bold text-[var(--ui-secondary)] mb-2">
                🔧 Tool Playground (MCP Testing)
            </h1>
            <p class="text-[var(--ui-muted)] mb-6">
                Vollständiger Playground zum Testen der Tool-Orchestrierung mit vollem Debug.
            </p>

            <div x-data="toolPlayground" class="space-y-6">
                <!-- Tabs: Tool Test, MCP Simulation, Tool Discovery, Tool Requests -->
                <div class="border-b border-[var(--ui-border)] mb-6">
                    <div class="flex gap-4 flex-wrap">
                        <button 
                            @click="activeTab = 'test'"
                            :class="activeTab === 'test' ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]'"
                            class="pb-2 px-4 font-medium"
                        >
                            🔧 Tool Test
                        </button>
                        <button 
                            @click="activeTab = 'simulate'"
                            :class="activeTab === 'simulate' ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]'"
                            class="pb-2 px-4 font-medium"
                        >
                            🎯 MCP Simulation
                        </button>
                        <button 
                            @click="activeTab = 'discovery'"
                            :class="activeTab === 'discovery' ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]'"
                            class="pb-2 px-4 font-medium"
                        >
                            🔍 Tool Discovery (tools.list)
                        </button>
                        <button 
                            @click="activeTab = 'requests'"
                            :class="activeTab === 'requests' ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]'"
                            class="pb-2 px-4 font-medium"
                        >
                            📝 Tool Requests (tools.request)
                        </button>
                    </div>
                </div>

                <!-- MCP Simulation Tab -->
                <div x-show="activeTab === 'simulate'" class="space-y-6">
                    <!-- Streaming Toggle -->
                    <div class="bg-[var(--ui-muted-5)] rounded-lg p-3 border border-[var(--ui-border)]">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-semibold text-[var(--ui-secondary)]">📡 Streaming-Modus:</span>
                                <span class="text-xs text-[var(--ui-muted)] ml-2">Echtzeit-Updates während der Simulation</span>
                            </div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" x-model="useStreaming" class="mr-2">
                                <span class="text-sm" :class="useStreaming ? 'text-[var(--ui-success)]' : 'text-[var(--ui-muted)]'">
                                    <span x-show="useStreaming">✅ Aktiv</span>
                                    <span x-show="!useStreaming">❌ Inaktiv</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Chat Interface -->
                    <div class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-[var(--ui-secondary)]">💬 Chat (MCP-Simulation)</h2>
                            <button 
                                @click="clearChat()"
                                class="px-3 py-1 bg-[var(--ui-danger)] text-white rounded text-sm hover:opacity-90"
                            >
                                🗑️ Chat leeren
                            </button>
                        </div>
                        <p class="text-[var(--ui-muted)] mb-4">
                            Chat mit der LLM - sie kann Tools aufrufen und komplexe Aufgaben lösen.
                            <span x-show="useStreaming" class="text-[var(--ui-info)]">📡 Streaming aktiv - Events werden in Echtzeit angezeigt.</span>
                        </p>
                        
                        <!-- Chat Messages -->
                        <div class="bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)] mb-4" style="max-height: 500px; overflow-y: auto;" x-ref="chatContainer">
                            <div class="p-4 space-y-2">
                                <template x-if="!chatMessages || chatMessages.length === 0">
                                    <div class="text-center text-[var(--ui-muted)] py-8">
                                        <p>💬 Starte die Konversation mit einer Nachricht</p>
                                        <p class="text-xs mt-2">Beispiele: "Erstelle ein Projekt", "Zeige mir alle Teams", "Lösche Projekt X"</p>
                                    </div>
                                </template>
                                
                                <!-- Chat-Verlauf: Nur Messages (keine Events im Chat) -->
                                <template x-for="(item, index) in chatMessages" :key="'msg-' + index">
                                    <template x-if="item && item.type === 'message'">
                                        <div class="flex" :class="item.role === 'user' ? 'justify-end' : 'justify-start'">
                                            <div 
                                                class="max-w-[80%] rounded-lg p-3"
                                                :class="item.role === 'user' 
                                                    ? 'bg-[var(--ui-primary)] text-white' 
                                                    : 'bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]'"
                                            >
                                                <div class="text-sm font-semibold mb-1" x-text="item.role === 'user' ? 'Du' : 'LLM'"></div>
                                                <div class="text-sm whitespace-pre-wrap" x-text="item.content || '...'"></div>
                                                <div x-show="item.tool_calls && item.tool_calls.length > 0" class="mt-2 pt-2 border-t border-[var(--ui-border)]">
                                                    <div class="text-xs font-semibold mb-1">🔧 Tools aufgerufen:</div>
                                                    <template x-for="toolCall in item.tool_calls">
                                                        <div class="text-xs font-mono mb-1" x-text="toolCall.function?.name || toolCall.name"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </template>
                                
                                <div x-show="simulationLoading" class="flex justify-start">
                                    <div class="bg-[var(--ui-muted-5)] rounded-lg p-3 border border-[var(--ui-border)]">
                                        <div class="flex items-center gap-2 text-[var(--ui-muted)]">
                                            <span class="text-xs">...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chat Input -->
                        <div class="flex gap-2">
                            <textarea 
                                x-model="simulationMessage"
                                @keydown.enter.prevent="runSimulation()"
                                @keydown.shift.enter.prevent="simulationMessage += '\n'"
                                placeholder="Nachricht eingeben... (Enter zum Senden, Shift+Enter für neue Zeile)"
                                class="flex-1 px-3 py-2 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                                rows="2"
                            ></textarea>
                            <button 
                                @click="runSimulation()"
                                :disabled="simulationLoading || !simulationMessage.trim()"
                                class="px-4 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:opacity-90 disabled:opacity-50 self-end"
                            >
                                <span x-show="!simulationLoading">📤 Senden</span>
                                <span x-show="simulationLoading">⏳</span>
                            </button>
                        </div>
                    </div>

                    <!-- Simulation Results -->
                    <div x-show="simulationResult" class="space-y-4">
                        <!-- Semantische Analyse -->
                        <div x-show="simulationResult?.semantic_analysis" class="bg-[var(--ui-info-5)] rounded-lg p-4 border-2 border-[var(--ui-info)]">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-info)]">🧠 Semantische Intent-Analyse</h3>
                            <div class="space-y-3">
                                <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="font-semibold text-[var(--ui-secondary)]">Intent-Typ:</span>
                                        <span class="px-2 py-1 rounded text-sm" 
                                              :class="simulationResult?.semantic_analysis?.intent_type === 'task' ? 'bg-[var(--ui-warning-5)] text-[var(--ui-warning)]' : 
                                                      simulationResult?.semantic_analysis?.intent_type === 'question' ? 'bg-[var(--ui-info-5)] text-[var(--ui-info)]' : 
                                                      'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]'"
                                              x-text="simulationResult?.semantic_analysis?.intent_type || 'unclear'"></span>
                                    </div>
                                    <div class="text-sm mb-2">
                                        <span class="font-semibold text-[var(--ui-secondary)]">Kann selbstständig auflösen:</span>
                                        <span x-show="simulationResult?.semantic_analysis?.can_solve_independently === null" 
                                              class="text-[var(--ui-info)]">🤔 LLM entscheidet selbst</span>
                                        <span x-show="simulationResult?.semantic_analysis?.can_solve_independently === true" 
                                              class="text-[var(--ui-success)]">✅ Ja</span>
                                        <span x-show="simulationResult?.semantic_analysis?.can_solve_independently === false" 
                                              class="text-[var(--ui-danger)]">❌ Nein</span>
                                    </div>
                                    <div class="text-sm mb-2">
                                        <span class="font-semibold text-[var(--ui-secondary)]">Grund:</span>
                                        <span class="text-[var(--ui-muted)]" x-text="simulationResult?.semantic_analysis?.reason || 'N/A'"></span>
                                    </div>
                                    <div class="text-sm mb-2">
                                        <span class="font-semibold text-[var(--ui-secondary)]">Benötigt Tools:</span>
                                        <span x-show="simulationResult?.semantic_analysis?.needs_tools === null" 
                                              class="text-[var(--ui-info)]">🤔 LLM entscheidet selbst</span>
                                        <span x-show="simulationResult?.semantic_analysis?.needs_tools === true" 
                                              class="text-[var(--ui-warning)]">✅ Ja</span>
                                        <span x-show="simulationResult?.semantic_analysis?.needs_tools === false" 
                                              class="text-[var(--ui-success)]">❌ Nein</span>
                                    </div>
                                    <div x-show="simulationResult?.semantic_analysis?.can_help_with_tools" class="text-sm mb-2">
                                        <span class="font-semibold text-[var(--ui-success)]">✅ Kann mit Tools helfen:</span>
                                        <span class="text-[var(--ui-success)]" x-text="simulationResult?.semantic_analysis?.relevant_tools_count + ' Tools verfügbar'"></span>
                                    </div>
                                    <div x-show="simulationResult?.semantic_analysis?.can_help_user" class="text-sm mb-2">
                                        <span class="font-semibold text-[var(--ui-info)]">💡 Kann User helfen:</span>
                                        <span class="text-[var(--ui-info)]" x-text="'Helper-Tools: ' + (simulationResult?.semantic_analysis?.helper_tools?.join(', ') || 'N/A')"></span>
                                    </div>
                                    <div x-show="simulationResult?.semantic_analysis?.needs_tool_request" class="text-sm mb-2">
                                        <span class="font-semibold text-[var(--ui-warning)]">⚠️ Tool-Request nötig:</span>
                                        <span class="text-[var(--ui-warning)]">Keine passenden Tools verfügbar</span>
                                    </div>
                                    <div class="mt-3 p-2 bg-[var(--ui-muted)] rounded">
                                        <span class="font-semibold text-[var(--ui-secondary)]">Empfohlene Aktion:</span>
                                        <div class="text-sm text-[var(--ui-secondary)] mt-1" x-text="simulationResult?.semantic_analysis?.recommended_action || 'N/A'"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Flow Visualization -->
                        <div class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">📊 Request-Flow</h3>
                            <div class="space-y-3">
                                <template x-for="(step, index) in (simulationResult?.steps || [])" :key="index">
                                    <div class="flex items-start gap-4 p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[var(--ui-primary)] text-white flex items-center justify-center font-bold" x-text="step.step"></div>
                                        <div class="flex-1">
                                            <div class="font-semibold text-[var(--ui-secondary)]" x-text="step.name"></div>
                                            <div class="text-sm text-[var(--ui-muted)] mt-1" x-text="step.description"></div>
                                            <div x-show="step.result" class="mt-2 text-sm text-[var(--ui-success)]" x-text="step.result"></div>
                                            <div x-show="step.tools" class="mt-2">
                                                <span class="text-xs text-[var(--ui-muted)]">Tools: </span>
                                                <template x-for="tool in step.tools">
                                                    <span class="text-xs px-2 py-1 bg-[var(--ui-primary-5)] text-[var(--ui-primary)] rounded mr-1" x-text="tool"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Tools Discovered -->
                        <div x-show="simulationResult?.tools_discovered?.length > 0" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">🔍 Tools entdeckt (<span x-text="simulationResult?.tools_discovered?.length || 0"></span>)</h3>
                            <div class="space-y-2">
                                <template x-for="tool in (simulationResult?.tools_discovered || [])">
                                    <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                        <div class="font-mono text-sm font-semibold text-[var(--ui-secondary)]" x-text="tool.name"></div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-1" x-text="tool.description"></div>
                                        <div x-show="tool.has_dependencies" class="mt-2">
                                            <span class="text-xs px-2 py-1 bg-[var(--ui-warning-5)] text-[var(--ui-warning)] rounded">Hat Dependencies</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Chain Plan -->
                        <div x-show="simulationResult?.chain_plan" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">🔗 Chain-Plan</h3>
                            <div class="space-y-2">
                                <div class="text-sm text-[var(--ui-muted)] mb-2">Ausführungsreihenfolge:</div>
                                <template x-for="(tool, index) in (simulationResult?.chain_plan?.execution_order || [])">
                                    <div class="flex items-center gap-2 p-2 bg-[var(--ui-surface)] rounded">
                                        <span class="text-xs font-bold text-[var(--ui-primary)]" x-text="(index + 1) + '.'"></span>
                                        <span class="font-mono text-sm text-[var(--ui-secondary)]" x-text="tool"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Execution Flow -->
                        <div x-show="simulationResult?.execution_flow?.length > 0" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">⚙️ Execution-Flow</h3>
                            <div class="space-y-3">
                                <template x-for="(exec, index) in (simulationResult?.execution_flow || [])">
                                    <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]" :class="exec.is_dependency ? 'border-l-4 border-l-[var(--ui-info)]' : ''">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span x-show="exec.is_dependency" class="text-xs text-[var(--ui-info)] font-semibold">🔗 Dependency:</span>
                                            <span class="font-mono text-sm font-semibold text-[var(--ui-secondary)]" x-text="exec.tool"></span>
                                            <span :class="exec.result.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]'" x-text="exec.result.success ? '✅' : '❌'"></span>
                                        </div>
                                        
                                        <!-- Performance Info -->
                                        <div x-show="exec.execution_time_ms" class="text-xs text-[var(--ui-muted)] mb-2">
                                            ⏱️ <strong>Ausführungszeit:</strong> <span x-text="exec.execution_time_ms"></span>ms
                                        </div>

                                        <!-- Events -->
                                        <div x-show="exec.events" class="mb-3 p-2 bg-[var(--ui-muted)] rounded">
                                            <div class="text-xs font-semibold mb-1 text-[var(--ui-secondary)]">📡 Events:</div>
                                            <div x-show="exec.events && exec.events.tool_executed" class="text-xs text-[var(--ui-success)] mb-1">
                                                ✅ ToolExecuted: <span x-text="exec.events?.tool_executed ? Math.round(exec.events.tool_executed.duration * 1000) : 0"></span>ms, 
                                                Memory: <span x-text="exec.events?.tool_executed ? Math.round(exec.events.tool_executed.memory_usage / 1024 / 1024 * 100) / 100 : 0"></span>MB,
                                                Trace: <span x-text="exec.events?.tool_executed?.trace_id || ''"></span>
                                            </div>
                                            <div x-show="exec.events && exec.events.tool_failed" class="text-xs text-[var(--ui-danger)] mb-1">
                                                ❌ ToolFailed: <span x-text="exec.events?.tool_failed?.error_code || ''"></span> - 
                                                <span x-text="exec.events?.tool_failed?.error_message || ''"></span>
                                            </div>
                                        </div>

                                        <!-- Features Info -->
                                        <div x-show="exec.features" class="mb-3 space-y-2">
                                            <div class="text-xs font-semibold mb-1 text-[var(--ui-secondary)]">🔧 Features:</div>
                                            
                                            <!-- Cache -->
                                            <div x-show="exec.features && exec.features.cache" class="text-xs p-2 bg-[var(--ui-muted)] rounded">
                                                <strong>💾 Cache:</strong>
                                                <span x-show="exec.features?.cache?.enabled" class="text-[var(--ui-success)]">Aktiviert</span>
                                                <span x-show="exec.features?.cache && !exec.features.cache.enabled" class="text-[var(--ui-muted)]">Deaktiviert</span>
                                                <span x-show="exec.features?.cache?.cached" class="text-[var(--ui-success)] ml-2">✅ Aus Cache</span>
                                                <span x-show="exec.features?.cache && !exec.features.cache.cached && exec.features.cache.enabled" class="text-[var(--ui-muted)] ml-2">❌ Nicht gecacht</span>
                                            </div>

                                            <!-- Timeout -->
                                            <div x-show="exec.features && exec.features.timeout" class="text-xs p-2 bg-[var(--ui-muted)] rounded">
                                                <strong>⏱️ Timeout:</strong>
                                                <span x-show="exec.features?.timeout?.enabled" class="text-[var(--ui-success)]">Aktiviert</span>
                                                <span x-show="exec.features?.timeout && !exec.features.timeout.enabled" class="text-[var(--ui-muted)]">Deaktiviert</span>
                                                <span x-show="exec.features?.timeout?.timeout_seconds" class="ml-2">
                                                    Max: <span x-text="exec.features?.timeout?.timeout_seconds || ''"></span>s
                                                </span>
                                            </div>

                                            <!-- Validation -->
                                            <div x-show="exec.features && exec.features.validation" class="text-xs p-2 bg-[var(--ui-muted)] rounded">
                                                <strong>✅ Validation:</strong>
                                                <span x-show="exec.features?.validation?.valid" class="text-[var(--ui-success)]">Valide</span>
                                                <span x-show="exec.features?.validation && !exec.features.validation.valid" class="text-[var(--ui-danger)]">Fehler</span>
                                                <div x-show="exec.features?.validation?.errors && exec.features.validation.errors.length > 0" class="mt-1 text-[var(--ui-danger)]">
                                                    <template x-for="(error, i) in (exec.features?.validation?.errors || [])" :key="i">
                                                        <div x-text="error"></div>
                                                    </template>
                                                </div>
                                            </div>

                                            <!-- Circuit Breaker -->
                                            <div x-show="exec.features && exec.features.circuit_breaker" class="text-xs p-2 bg-[var(--ui-muted)] rounded">
                                                <strong>🔌 Circuit Breaker:</strong>
                                                <span x-show="exec.features?.circuit_breaker?.enabled" class="text-[var(--ui-success)]">Aktiviert</span>
                                                <span x-show="exec.features?.circuit_breaker && !exec.features.circuit_breaker.enabled" class="text-[var(--ui-muted)]">Deaktiviert</span>
                                                <span x-show="exec.features?.circuit_breaker?.openai_status" class="ml-2">
                                                    OpenAI: <span 
                                                        x-text="exec.features?.circuit_breaker?.openai_status || ''"
                                                        :class="exec.features?.circuit_breaker?.openai_status === 'open' ? 'text-[var(--ui-danger)]' : 'text-[var(--ui-success)]'"
                                                    ></span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-xs text-[var(--ui-muted)] mb-2">
                                            <strong>Argumente:</strong>
                                            <pre class="mt-1 p-2 bg-[var(--ui-muted)] rounded overflow-auto" x-text="JSON.stringify(exec.arguments, null, 2)"></pre>
                                        </div>
                                        <div x-show="exec.result.data" class="text-xs mt-2">
                                            <strong class="text-[var(--ui-success)]">Ergebnis:</strong>
                                            <pre class="mt-1 p-2 bg-[var(--ui-muted)] text-[var(--ui-success)] rounded overflow-auto" x-text="JSON.stringify(exec.result.data, null, 2)"></pre>
                                        </div>
                                        <div x-show="exec.result.has_error" class="text-xs mt-2">
                                            <strong class="text-[var(--ui-danger)]">Fehler:</strong>
                                            <pre class="mt-1 p-2 bg-[var(--ui-muted)] text-[var(--ui-danger)] rounded overflow-auto" x-text="JSON.stringify(exec.result.error, null, 2)"></pre>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Action Summary (Execution Summary) -->
                        <div x-show="simulationResult?.action_summary" class="bg-[var(--ui-success-5)] rounded-lg p-4 border-2 border-[var(--ui-success)]">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-success)]">📋 Execution Summary</h3>
                            <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                <div class="text-sm font-semibold mb-3 text-[var(--ui-secondary)]" x-text="simulationResult?.action_summary?.summary || 'Keine Zusammenfassung verfügbar'"></div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <div class="text-center p-2 bg-[var(--ui-muted-5)] rounded">
                                        <div class="text-2xl font-bold text-[var(--ui-primary)]" x-text="simulationResult?.action_summary?.tools_executed || 0"></div>
                                        <div class="text-xs text-[var(--ui-muted)]">Tools ausgeführt</div>
                                    </div>
                                    <div class="text-center p-2 bg-[var(--ui-muted-5)] rounded">
                                        <div class="text-2xl font-bold text-[var(--ui-success)]" x-text="simulationResult?.action_summary?.models_created || 0"></div>
                                        <div class="text-xs text-[var(--ui-muted)]">Erstellt</div>
                                    </div>
                                    <div class="text-center p-2 bg-[var(--ui-muted-5)] rounded">
                                        <div class="text-2xl font-bold text-[var(--ui-info)]" x-text="simulationResult?.action_summary?.models_updated || 0"></div>
                                        <div class="text-xs text-[var(--ui-muted)]">Aktualisiert</div>
                                    </div>
                                    <div class="text-center p-2 bg-[var(--ui-muted-5)] rounded">
                                        <div class="text-2xl font-bold text-[var(--ui-danger)]" x-text="simulationResult?.action_summary?.models_deleted || 0"></div>
                                        <div class="text-xs text-[var(--ui-muted)]">Gelöscht</div>
                                    </div>
                                </div>
                                
                                <!-- Detaillierte Model-Änderungen -->
                                <div x-show="simulationResult?.action_summary?.created_models?.length > 0 || simulationResult?.action_summary?.updated_models?.length > 0 || simulationResult?.action_summary?.deleted_models?.length > 0" class="mt-4 space-y-3">
                                    <div x-show="simulationResult?.action_summary?.created_models?.length > 0">
                                        <div class="text-xs font-semibold mb-2 text-[var(--ui-success)]">✅ Erstellt:</div>
                                        <template x-for="model in (simulationResult?.action_summary?.created_models || [])">
                                            <div class="text-xs p-2 bg-[var(--ui-success-5)] rounded mb-1">
                                                <span class="font-mono" x-text="model.model_type"></span> (ID: <span x-text="model.model_id"></span>)
                                                <span x-show="model.reason" class="text-[var(--ui-muted)] ml-2" x-text="' - ' + model.reason"></span>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <div x-show="simulationResult?.action_summary?.updated_models?.length > 0">
                                        <div class="text-xs font-semibold mb-2 text-[var(--ui-info)]">🔄 Aktualisiert:</div>
                                        <template x-for="model in (simulationResult?.action_summary?.updated_models || [])">
                                            <div class="text-xs p-2 bg-[var(--ui-info-5)] rounded mb-1">
                                                <span class="font-mono" x-text="model.model_type"></span> (ID: <span x-text="model.model_id"></span>)
                                                <span x-show="model.reason" class="text-[var(--ui-muted)] ml-2" x-text="' - ' + model.reason"></span>
                                            </div>
                                        </template>
                                    </div>
                                    
                                    <div x-show="simulationResult?.action_summary?.deleted_models?.length > 0">
                                        <div class="text-xs font-semibold mb-2 text-[var(--ui-danger)]">🗑️ Gelöscht:</div>
                                        <template x-for="model in (simulationResult?.action_summary?.deleted_models || [])">
                                            <div class="text-xs p-2 bg-[var(--ui-danger-5)] rounded mb-1">
                                                <span class="font-mono" x-text="model.model_type"></span> (ID: <span x-text="model.model_id"></span>)
                                                <span x-show="model.reason" class="text-[var(--ui-muted)] ml-2" x-text="' - ' + model.reason"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Audit Trail (Step-by-Step) -->
                        <div x-show="simulationResult?.audit_trail" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">📜 Audit Trail (Step-by-Step)</h3>
                            <div class="text-xs text-[var(--ui-muted)] mb-3">
                                Trace-ID: <span class="font-mono" x-text="simulationResult?.audit_trail?.trace_id"></span> | 
                                <span x-text="simulationResult?.audit_trail?.total_steps || 0"></span> Schritte
                            </div>
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                <template x-for="(step, index) in (simulationResult?.audit_trail?.steps || [])" :key="index">
                                    <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]" :class="step.type === 'model_change' ? 'border-l-4 border-l-[var(--ui-warning)]' : ''">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-xs font-bold text-[var(--ui-primary)]" x-text="'Schritt ' + step.step"></span>
                                            <span class="text-xs px-2 py-1 rounded" 
                                                  :class="step.type === 'tool_execution' ? 'bg-[var(--ui-info-5)] text-[var(--ui-info)]' : 'bg-[var(--ui-warning-5)] text-[var(--ui-warning)]'"
                                                  x-text="step.type === 'tool_execution' ? '🔧 Tool' : '📝 Model-Änderung'"></span>
                                            <span class="text-xs text-[var(--ui-muted)]" x-text="step.timestamp"></span>
                                        </div>
                                        
                                        <div x-show="step.type === 'tool_execution'">
                                            <div class="font-mono text-sm font-semibold text-[var(--ui-secondary)]" x-text="step.tool_name"></div>
                                            <div class="text-xs text-[var(--ui-muted)] mt-1" x-text="step.result_message || 'Keine Nachricht'"></div>
                                            <div class="flex items-center gap-4 mt-2 text-xs">
                                                <span :class="step.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]'" 
                                                      x-text="step.success ? '✅ Erfolgreich' : '❌ Fehlgeschlagen'"></span>
                                                <span x-show="step.duration_ms" class="text-[var(--ui-muted)]" x-text="'⏱️ ' + step.duration_ms + 'ms'"></span>
                                            </div>
                                        </div>
                                        
                                        <div x-show="step.type === 'model_change'">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-semibold" 
                                                      :class="step.operation === 'created' ? 'text-[var(--ui-success)]' : 
                                                              step.operation === 'updated' ? 'text-[var(--ui-info)]' : 
                                                              'text-[var(--ui-danger)]'"
                                                      x-text="step.operation === 'created' ? '✅ Erstellt' : 
                                                              step.operation === 'updated' ? '🔄 Aktualisiert' : 
                                                              '🗑️ Gelöscht'"></span>
                                                <span class="font-mono text-sm text-[var(--ui-secondary)]" x-text="step.model_type"></span>
                                                <span class="text-xs text-[var(--ui-muted)]">(ID: <span x-text="step.model_id"></span>)</span>
                                            </div>
                                            <div x-show="step.reason" class="text-xs text-[var(--ui-muted)] mt-1" x-text="step.reason"></div>
                                            <div x-show="step.changed_fields?.length > 0" class="text-xs text-[var(--ui-muted)] mt-1">
                                                Geänderte Felder: <span x-text="step.changed_fields.join(', ')"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- Summary -->
                            <div x-show="simulationResult?.audit_trail?.summary" class="mt-4 p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                <div class="text-xs font-semibold mb-2 text-[var(--ui-secondary)]">📊 Zusammenfassung:</div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                                    <div>Tools ausgeführt: <span class="font-bold" x-text="simulationResult?.audit_trail?.summary?.tools_executed || 0"></span></div>
                                    <div>Tools erfolgreich: <span class="font-bold text-[var(--ui-success)]" x-text="simulationResult?.audit_trail?.summary?.tools_successful || 0"></span></div>
                                    <div>Tools fehlgeschlagen: <span class="font-bold text-[var(--ui-danger)]" x-text="simulationResult?.audit_trail?.summary?.tools_failed || 0"></span></div>
                                    <div>Models erstellt: <span class="font-bold text-[var(--ui-success)]" x-text="simulationResult?.audit_trail?.summary?.models_created || 0"></span></div>
                                    <div>Models aktualisiert: <span class="font-bold text-[var(--ui-info)]" x-text="simulationResult?.audit_trail?.summary?.models_updated || 0"></span></div>
                                    <div>Models gelöscht: <span class="font-bold text-[var(--ui-danger)]" x-text="simulationResult?.audit_trail?.summary?.models_deleted || 0"></span></div>
                                </div>
                            </div>
                        </div>

                        <!-- User Input Required (Multi-Step) -->
                        <div x-show="simulationResult?.requires_user_input || simulationResult?.final_response?.type === 'user_input_required'" class="bg-[var(--ui-warning-5)] rounded-lg p-4 border-2 border-[var(--ui-warning)]">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-warning)]">👤 User-Input erforderlich</h3>
                            <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                <div class="text-[var(--ui-warning)] font-semibold mb-2" x-text="simulationResult?.user_input_prompt || simulationResult?.final_response?.message || 'Bitte wähle aus der Liste aus.'"></div>
                                
                                <!-- Zeige verfügbare Optionen (z.B. Teams) -->
                                <div x-show="simulationResult?.user_input_data || simulationResult?.final_response?.data" class="mt-4">
                                    <div class="text-sm font-semibold mb-2 text-[var(--ui-secondary)]">Verfügbare Optionen:</div>
                                    <div class="space-y-2 max-h-64 overflow-y-auto">
                                        <!-- Teams-Liste -->
                                        <template x-if="simulationResult?.user_input_data?.teams || simulationResult?.final_response?.data?.teams">
                                            <template x-for="team in (simulationResult?.user_input_data?.teams || simulationResult?.final_response?.data?.teams || [])">
                                                <div 
                                                    @click="continueWithUserInput(team.id)"
                                                    class="p-3 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)] hover:bg-[var(--ui-primary-5)] cursor-pointer transition-colors"
                                                >
                                                    <div class="font-semibold text-[var(--ui-secondary)]" x-text="team.name"></div>
                                                    <div class="text-xs text-[var(--ui-muted)] mt-1">
                                                        ID: <span x-text="team.id"></span>
                                                        <span x-show="team.is_current" class="ml-2 text-[var(--ui-success)]">(Aktuelles Team)</span>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                        
                                        <!-- Generische Liste -->
                                        <template x-if="!simulationResult?.user_input_data?.teams && !simulationResult?.final_response?.data?.teams">
                                            <div class="text-xs text-[var(--ui-muted)] p-2 bg-[var(--ui-muted)] rounded">
                                                <pre x-text="JSON.stringify(simulationResult?.user_input_data || simulationResult?.final_response?.data || {}, null, 2)"></pre>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                
                                <!-- User-Input-Feld für manuelle Eingabe -->
                                <div class="mt-4">
                                    <label class="block text-sm font-medium mb-2 text-[var(--ui-secondary)]">Oder manuell eingeben:</label>
                                    <div class="flex gap-2">
                                        <input 
                                            type="text" 
                                            x-model="userInputValue"
                                            placeholder="z.B. Team-ID: 5 oder JSON: {'team_id': 5}"
                                            class="flex-1 px-3 py-2 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                                        >
                                        <button 
                                            @click="continueWithUserInput(userInputValue)"
                                            class="px-4 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:opacity-90"
                                        >
                                            ➡️ Weiter
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex items-center gap-2 flex-wrap">
                                    <button 
                                        @click="continueWithUserInput(simulationResult?.user_input_data?.current_team_id || simulationResult?.final_response?.data?.current_team_id)"
                                        x-show="simulationResult?.user_input_data?.current_team_id || simulationResult?.final_response?.data?.current_team_id"
                                        class="px-4 py-2 bg-[var(--ui-success)] text-white rounded-lg hover:opacity-90 text-sm"
                                    >
                                        ✅ Aktuelles Team verwenden (ID: <span x-text="simulationResult?.user_input_data?.current_team_id || simulationResult?.final_response?.data?.current_team_id"></span>)
                                    </button>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        💡 Klicke auf ein Team oben, um die Simulation fortzusetzen, oder verwende das aktuelle Team.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Final Response -->
                        <div x-show="simulationResult?.final_response && simulationResult?.final_response?.type !== 'user_input_required'" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-secondary)]">💬 Finale Antwort</h3>
                            <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                <div :class="simulationResult?.final_response?.type === 'success' ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]'" class="font-semibold mb-2" x-text="simulationResult?.final_response?.message || 'Keine Nachricht'"></div>
                                <pre x-show="simulationResult?.final_response?.data" class="text-xs bg-[var(--ui-muted)] p-2 rounded overflow-auto" x-text="JSON.stringify(simulationResult?.final_response?.data || {}, null, 2)"></pre>
                            </div>
                        </div>

                        <!-- Error Details (wenn vorhanden) -->
                        <div x-show="simulationResult?.error" class="bg-[var(--ui-danger-5)] rounded-lg p-4 border-2 border-[var(--ui-danger)]">
                            <h3 class="text-lg font-semibold mb-4 text-[var(--ui-danger)]">❌ Fehler-Details</h3>
                            <div class="space-y-3">
                                <div x-show="simulationResult?.error?.message">
                                    <span class="font-semibold text-[var(--ui-secondary)]">Nachricht:</span>
                                    <div class="mt-1 p-2 bg-[var(--ui-surface)] rounded text-[var(--ui-danger)] font-mono text-sm" x-text="simulationResult?.error?.message"></div>
                                </div>
                                <div x-show="simulationResult?.error?.file" class="text-sm">
                                    <span class="font-semibold text-[var(--ui-secondary)]">Datei:</span>
                                    <span class="text-[var(--ui-muted)] ml-2" x-text="simulationResult?.error?.file"></span>
                                </div>
                                <div x-show="simulationResult?.error?.line" class="text-sm">
                                    <span class="font-semibold text-[var(--ui-secondary)]">Zeile:</span>
                                    <span class="text-[var(--ui-muted)] ml-2" x-text="simulationResult?.error?.line"></span>
                                </div>
                                <div x-show="simulationResult?.error?.class" class="text-sm">
                                    <span class="font-semibold text-[var(--ui-secondary)]">Klasse:</span>
                                    <span class="text-[var(--ui-muted)] ml-2" x-text="simulationResult?.error?.class"></span>
                                </div>
                                <div x-show="simulationResult?.error?.trace" class="mt-3">
                                    <span class="font-semibold text-[var(--ui-secondary)]">Stack Trace:</span>
                                    <pre class="mt-2 p-3 bg-[var(--ui-muted)] text-[var(--ui-danger)] rounded overflow-auto text-xs max-h-64" x-text="Array.isArray(simulationResult?.error?.trace) ? simulationResult?.error?.trace.join('\\n') : JSON.stringify(simulationResult?.error?.trace, null, 2)"></pre>
                                </div>
                                <div x-show="simulationResult?.error && !simulationResult?.error?.message">
                                    <pre class="p-3 bg-[var(--ui-muted)] text-[var(--ui-danger)] rounded overflow-auto text-xs" x-text="JSON.stringify(simulationResult?.error, null, 2)"></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Debug Export (Kopierbar) -->
                        <div class="bg-[var(--ui-muted-5)] rounded-lg p-4 border-2 border-[var(--ui-primary)]">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">📋 Debug-Export (Kopierbar)</h3>
                                <div class="flex gap-2">
                                    <button 
                                        @click="copyDebugExport()"
                                        class="px-3 py-1 bg-[var(--ui-primary)] text-white rounded text-sm hover:opacity-90"
                                    >
                                        📋 Kopieren
                                    </button>
                                    <button 
                                        @click="downloadDebugExport()"
                                        class="px-3 py-1 bg-[var(--ui-info)] text-white rounded text-sm hover:opacity-90"
                                    >
                                        💾 Download
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <pre 
                                    id="debug-export-content"
                                    class="text-xs bg-[var(--ui-muted)] p-4 rounded overflow-auto max-h-96 font-mono text-[var(--ui-secondary)] whitespace-pre-wrap cursor-text select-all"
                                    x-text="getDebugExport()"
                                ></pre>
                                <div 
                                    x-show="debugCopied"
                                    class="absolute top-2 right-2 px-3 py-1 bg-[var(--ui-success)] text-white rounded text-sm"
                                    x-transition
                                >
                                    ✅ Kopiert!
                                </div>
                            </div>
                            <p class="text-xs text-[var(--ui-muted)] mt-2">
                                💡 Kopiere diesen Debug-Export und teile ihn für Support/Entwicklung. Enthält alle Details der Simulation.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tool Test Tab -->
                <div x-show="activeTab === 'test'" class="space-y-6">
                    <!-- Tool Selection -->
                    <div class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4 text-[var(--ui-secondary)]">Tool auswählen</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2 text-[var(--ui-secondary)]">Tool Name</label>
                                <input 
                                    type="text" 
                                    x-model="toolName"
                                    placeholder="z.B. planner.projects.create"
                                    class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                                    list="tools-list"
                                >
                                <datalist id="tools-list">
                                    <template x-for="tool in availableTools">
                                        <option :value="tool.name" x-text="tool.name"></option>
                                    </template>
                                </datalist>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2 text-[var(--ui-secondary)]">Argumente (JSON)</label>
                                <textarea 
                                    x-model="argumentsJson"
                                    placeholder='{"name": "Test Projekt"}'
                                    class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg font-mono text-sm bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                                    rows="3"
                                ></textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-4 items-center">
                            <label class="flex items-center">
                                <input type="checkbox" x-model="options.plan" class="mr-2">
                                <span>Chain-Plan anzeigen</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="options.discover" class="mr-2">
                                <span>Discovery testen</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="options.use_orchestrator" class="mr-2" checked>
                                <span>Orchestrator nutzen</span>
                            </label>
                        </div>
                        <button 
                            @click="loadTools()"
                            class="mt-4 px-4 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:opacity-90"
                        >
                            🔄 Tools laden
                        </button>
                        <button 
                            @click="testTool()"
                            :disabled="loading"
                            class="mt-4 ml-2 px-4 py-2 bg-[var(--ui-success)] text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                        >
                            <span x-show="!loading">▶️ Tool testen</span>
                            <span x-show="loading">⏳ Lädt...</span>
                        </button>
                    </div>

                <!-- Results -->
                <div x-show="result" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                    <h2 class="text-xl font-semibold mb-4 text-[var(--ui-secondary)]">Ergebnis</h2>
                        <div class="space-y-4">
                    <div>
                        <span class="font-semibold text-[var(--ui-secondary)]">Status:</span>
                        <span 
                            x-text="result?.success ? '✅ Erfolg' : '❌ Fehler'"
                            :class="result?.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]'"
                        ></span>
                    </div>
                    <div x-show="result?.data">
                        <span class="font-semibold text-[var(--ui-secondary)]">Daten:</span>
                        <pre class="mt-2 p-3 bg-[var(--ui-muted)] text-[var(--ui-success)] rounded overflow-auto text-xs" x-text="JSON.stringify(result?.data || {}, null, 2)"></pre>
                    </div>
                    <div x-show="result?.error">
                        <span class="font-semibold text-[var(--ui-danger)]">Fehler:</span>
                        <pre class="mt-2 p-3 bg-[var(--ui-muted)] text-[var(--ui-danger)] rounded overflow-auto text-xs" x-text="JSON.stringify(result?.error || {}, null, 2)"></pre>
                    </div>
                        </div>
                    </div>

                    <!-- Debug Info -->
                    <div x-show="debug" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4 text-[var(--ui-secondary)]">🔍 Debug-Informationen</h2>
                        <details class="space-y-2">
                            <summary class="cursor-pointer font-medium text-[var(--ui-secondary)]">Debug-Details anzeigen</summary>
                            <pre class="mt-2 p-3 bg-[var(--ui-muted)] text-[var(--ui-warning)] rounded overflow-auto text-xs" x-text="JSON.stringify(debug, null, 2)"></pre>
                        </details>
                    </div>

                    <!-- Available Tools -->
                    <div class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4 text-[var(--ui-secondary)]">Verfügbare Tools (<span x-text="availableTools.length"></span>)</h2>
                        <div class="max-h-96 overflow-y-auto">
                            <div class="space-y-2">
                                <template x-for="tool in availableTools">
                                    <div class="p-2 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                        <div class="font-mono text-sm font-semibold text-[var(--ui-secondary)]" x-text="tool.name"></div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-1" x-text="tool.description"></div>
                                        <div class="mt-2 flex gap-2">
                                            <span 
                                                x-show="tool.has_dependencies"
                                                class="px-2 py-1 bg-[var(--ui-primary-5)] text-[var(--ui-primary)] rounded text-xs"
                                            >
                                                Dependencies
                                            </span>
                                            <span 
                                                x-show="tool.has_metadata"
                                                class="px-2 py-1 bg-[var(--ui-info-5)] text-[var(--ui-info)] rounded text-xs"
                                            >
                                                Metadata
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('toolPlayground', () => ({
                activeTab: 'simulate', // Start mit Simulation
                
                // Tool Test
                toolName: 'planner.projects.create',
                argumentsJson: '{"name": "Test Projekt"}',
                options: {
                    plan: true,
                    discover: false,
                    use_orchestrator: true
                },
                loading: false,
                result: null,
                debug: null,
                availableTools: [],

                // MCP Simulation - Chat
                simulationMessage: '',
                simulationLoading: false,
                simulationResult: null,
                debugCopied: false,
                userInputValue: '', // Für Multi-Step User-Input
                chatMessages: [], // Chat-Historie (WICHTIG: Muss Array sein für Alpine.js)
                chatHistory: [], // Vollständige Chat-Historie für Backend
                sessionId: null, // Session-ID für Chat-Historie
                useStreaming: true, // Streaming-Modus aktivieren
                streamingEvents: [], // Events während Streaming
                eventSource: null, // EventSource-Instanz
                
                // Helper: Force Alpine.js Update
                forceUpdate() {
                    // Trigger Alpine.js Reaktivität durch temporäre Änderung
                    this.chatMessages = [...this.chatMessages];
                },

                // Tool Discovery
                discoveryFilters: {
                    search: '',
                    module: '',
                    category: '',
                    tag: '',
                    read_only: false
                },
                discoveryLoading: false,
                discoveryResult: null,

                // Tool Requests
                toolRequest: {
                    description: '',
                    use_case: '',
                    suggested_name: '',
                    category: '',
                    module: ''
                },
                toolRequestLoading: false,
                toolRequestResult: null,
                toolRequests: [],
                toolRequestsLoading: false,

                async loadTools() {
                    try {
                        const response = await fetch('{{ route("core.tools.playground.tools") }}');
                        const data = await response.json();
                        if (data.success) {
                            this.availableTools = data.tools;
                        }
                    } catch (e) {
                        console.error('Fehler beim Laden der Tools:', e);
                    }
                },

                async testTool() {
                    this.loading = true;
                    this.result = null;
                    this.debug = null;

                    try {
                        let arguments = {};
                        try {
                            arguments = JSON.parse(this.argumentsJson || '{}');
                        } catch (e) {
                            alert('Ungültiges JSON in Argumenten!');
                            this.loading = false;
                            return;
                        }

                        const response = await fetch('{{ route("core.tools.playground.test") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                tool: this.toolName,
                                arguments: arguments,
                                options: this.options
                            })
                        });

                        const data = await response.json();
                        this.result = data;
                        this.debug = data.debug || null;
                    } catch (e) {
                        this.result = {
                            success: false,
                            error: e.message
                        };
                    } finally {
                        this.loading = false;
                    }
                },

                async runSimulation(step = 0, previousResult = null, userInput = null) {
                    if (!this.simulationMessage.trim() && step === 0) {
                        return; // Keine leeren Nachrichten
                    }
                    
                    // Verwende Streaming wenn aktiviert
                    if (this.useStreaming) {
                        return await this.runSimulationStream(step, previousResult, userInput);
                    }
                    
                    this.simulationLoading = true;
                    
                    // Füge User-Message zu Chat-Anzeige hinzu (nur UI)
                    // WICHTIG: chatHistory wird NICHT hier hinzugefügt - das Backend macht das!
                    if (step === 0) {
                        const userMsg = this.simulationMessage.trim();
                        if (userMsg) {
                            this.chatMessages.push({
                                role: 'user',
                                content: userMsg,
                                timestamp: new Date().toISOString()
                            });
                            // chatHistory wird vom Backend aktualisiert und zurückgegeben
                        }
                    }
                    
                    if (step === 0) {
                        this.simulationResult = null; // Nur beim ersten Schritt zurücksetzen
                        this.debugCopied = false;
                    }

                    try {
                        const payload = {
                            message: this.simulationMessage,
                            options: {},
                            chat_history: this.chatHistory, // Sende Chat-Historie
                            session_id: this.sessionId || this.generateSessionId(), // Session-ID
                        };
                        
                        // Multi-Step: Füge Schritt-Info hinzu
                        if (step > 0) {
                            payload.step = step;
                            payload.previous_result = previousResult;
                            payload.user_input = userInput;
                        }
                        
                        const response = await fetch('{{ route("core.tools.playground.simulate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });

                        // WICHTIG: Body nur einmal lesen
                        let responseData;
                        const contentType = response.headers.get('content-type');
                        const isJson = contentType && contentType.includes('application/json');
                        
                        // Lese Body als Text (kann dann als JSON geparst werden)
                        const responseText = await response.text();
                        
                        try {
                            // Versuche Text als JSON zu parsen
                            if (isJson || responseText.trim().startsWith('{') || responseText.trim().startsWith('[')) {
                                responseData = JSON.parse(responseText);
                            } else {
                                // Wenn nicht JSON (z.B. HTML-Fehlerseite), erstelle Fehler-Objekt
                                // Prüfe ob es HTML ist
                                const isHtml = responseText.trim().toLowerCase().startsWith('<!doctype') || 
                                             responseText.trim().toLowerCase().startsWith('<html');
                                
                                if (isHtml) {
                                    // Extrahiere Fehler-Info aus HTML falls möglich
                                    const titleMatch = responseText.match(/<title>(.*?)<\/title>/i);
                                    const title = titleMatch ? titleMatch[1] : 'HTML-Fehlerseite';
                                    
                                    responseData = {
                                        success: false,
                                        error: `HTTP ${response.status}: Server-Fehler (HTML-Response statt JSON)`,
                                        error_details: {
                                            status: response.status,
                                            statusText: response.statusText,
                                            content_type: contentType || 'unknown',
                                            is_html: true,
                                            html_title: title,
                                            raw_response_preview: responseText.substring(0, 500),
                                            message: 'Der Server hat eine HTML-Fehlerseite zurückgegeben. Dies deutet auf einen fatalen PHP-Fehler hin, der vor der JSON-Response auftritt.'
                                        }
                                    };
                                } else {
                                    // Anderes Format
                                    responseData = {
                                        success: false,
                                        error: `HTTP ${response.status}: ${response.statusText}`,
                                        error_details: {
                                            status: response.status,
                                            statusText: response.statusText,
                                            content_type: contentType || 'unknown',
                                            raw_response: responseText.substring(0, 1000)
                                        }
                                    };
                                }
                            }
                        } catch (parseError) {
                            // Wenn JSON-Parsing fehlschlägt, erstelle Fehler-Objekt
                            const isHtml = responseText.trim().toLowerCase().startsWith('<!doctype') || 
                                         responseText.trim().toLowerCase().startsWith('<html');
                            
                            responseData = {
                                success: false,
                                error: `HTTP ${response.status}: Fehler beim Parsen der Antwort`,
                                error_details: {
                                    status: response.status,
                                    statusText: response.statusText,
                                    content_type: contentType || 'unknown',
                                    is_html: isHtml,
                                    parse_error: parseError.message,
                                    raw_response_preview: responseText.substring(0, 500)
                                }
                            };
                        }

                        // Prüfe Response-Status oder success-Flag
                        if (!response.ok || !responseData.success) {
                            // Zeige Fehler-Details im Playground
                            this.simulationResult = {
                                timestamp: new Date().toISOString(),
                                user_message: this.simulationMessage,
                                steps: responseData.simulation?.steps || [],
                                tools_discovered: responseData.simulation?.tools_discovered || [],
                                execution_flow: responseData.simulation?.execution_flow || [],
                                final_response: {
                                    type: 'error',
                                    message: responseData.error || 'Unbekannter Fehler',
                                    error_details: responseData.error_details || responseData,
                                },
                                error: responseData.error_details || {
                                    message: responseData.error || 'Unbekannter Fehler',
                                    status: response.status,
                                    statusText: response.statusText,
                                },
                            };
                            
                            // Zeige auch Alert mit Details
                            const errorMsg = responseData.error || 'Unbekannter Fehler';
                            const errorDetails = responseData.error_details ? 
                                `\n\nDetails:\nDatei: ${responseData.error_details.file || 'N/A'}\nZeile: ${responseData.error_details.line || 'N/A'}\nKlasse: ${responseData.error_details.class || 'N/A'}` : 
                                '';
                            alert('❌ Simulation fehlgeschlagen!\n\n' + errorMsg + errorDetails);
                            
                            return;
                        }
                        
                        // Erfolgreiche Antwort
                        if (responseData.success) {
                            // Update Chat-Historie
                            if (responseData.chat_history) {
                                this.chatHistory = responseData.chat_history;
                            }
                            if (responseData.session_id) {
                                this.sessionId = responseData.session_id;
                            }
                            
                            // Füge Assistant-Response zu Chat hinzu
                            const finalResponse = responseData.simulation?.final_response;
                            if (finalResponse && finalResponse.content) {
                                // Entferne letzte Assistant-Message (falls vorhanden)
                                if (this.chatMessages.length > 0 && this.chatMessages[this.chatMessages.length - 1].role === 'assistant') {
                                    this.chatMessages.pop();
                                }
                                
                                // Füge neue Assistant-Message hinzu
                                this.chatMessages.push({
                                    role: 'assistant',
                                    content: finalResponse.content,
                                    timestamp: new Date().toISOString(),
                                    tool_calls: responseData.simulation?.execution_flow?.map(e => ({
                                        name: e.tool,
                                        function: { name: e.tool }
                                    })) || []
                                });
                                
                                // Chat-Historie wird bereits vom Backend aktualisiert und zurückgegeben
                            // Keine manuelle Hinzufügung nötig - responseData.chat_history ist bereits vollständig
                            }
                            
                            // Merge mit vorherigem Ergebnis (für Multi-Step)
                            if (step > 0 && this.simulationResult) {
                                // Füge neuen Schritt zu bestehendem Ergebnis hinzu
                                this.simulationResult.steps = [...(this.simulationResult.steps || []), ...(responseData.simulation?.steps || [])];
                                this.simulationResult.execution_flow = [...(this.simulationResult.execution_flow || []), ...(responseData.simulation?.execution_flow || [])];
                                this.simulationResult.tools_used = [...(this.simulationResult.tools_used || []), ...(responseData.simulation?.tools_used || [])];
                                this.simulationResult.final_response = responseData.simulation?.final_response || this.simulationResult.final_response;
                                this.simulationResult.requires_user_input = responseData.simulation?.requires_user_input || false;
                                this.simulationResult.user_input_prompt = responseData.simulation?.user_input_prompt || null;
                                this.simulationResult.user_input_data = responseData.simulation?.user_input_data || null;
                                this.simulationResult.next_tool = responseData.simulation?.next_tool || null;
                                this.simulationResult.next_tool_args = responseData.simulation?.next_tool_args || null;
                                // Merge Action Summary und Audit Trail
                                if (responseData.simulation?.action_summary) {
                                    this.simulationResult.action_summary = responseData.simulation.action_summary;
                                }
                                if (responseData.simulation?.audit_trail) {
                                    this.simulationResult.audit_trail = responseData.simulation.audit_trail;
                                }
                            } else {
                                this.simulationResult = responseData.simulation;
                            }
                            
                            // Leere Input-Feld
                            if (step === 0) {
                                this.simulationMessage = '';
                            }
                            
                            // Scroll zu neuem Message
                            this.$nextTick(() => {
                                if (this.$refs.chatContainer) {
                                    this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
                                }
                            });
                        } else {
                            // Zeige Fehler-Details im Playground
                            this.simulationResult = {
                                timestamp: new Date().toISOString(),
                                user_message: this.simulationMessage,
                                steps: responseData.simulation?.steps || [],
                                tools_discovered: responseData.simulation?.tools_discovered || [],
                                execution_flow: responseData.simulation?.execution_flow || [],
                                final_response: {
                                    type: 'error',
                                    message: responseData.error || 'Unbekannter Fehler',
                                    error_details: responseData.error_details || responseData,
                                },
                                error: responseData.error_details || responseData,
                            };
                            
                            // Zeige Alert mit Details
                            const errorMsg = responseData.error || 'Unbekannter Fehler';
                            const errorDetails = responseData.error_details ? 
                                `\n\nDetails:\nDatei: ${responseData.error_details.file || 'N/A'}\nZeile: ${responseData.error_details.line || 'N/A'}\nKlasse: ${responseData.error_details.class || 'N/A'}` : 
                                '';
                            alert('❌ Simulation fehlgeschlagen!\n\n' + errorMsg + errorDetails);
                        }
                    } catch (e) {
                        // Netzwerk- oder andere Fehler
                        console.error('Simulation Error:', e);
                        this.simulationResult = {
                            timestamp: new Date().toISOString(),
                            user_message: this.simulationMessage,
                            steps: [],
                            tools_discovered: [],
                            execution_flow: [],
                            final_response: {
                                type: 'error',
                                message: 'Netzwerk- oder Verbindungsfehler: ' + e.message,
                            },
                            error: {
                                message: e.message,
                                stack: e.stack,
                                name: e.name,
                            },
                        };
                        alert('❌ Fehler beim Senden der Anfrage:\n\n' + e.message);
                    } finally {
                        this.simulationLoading = false;
                    }
                },

                async runSimulationStream(step = 0, previousResult = null, userInput = null) {
                    if (!this.simulationMessage.trim() && step === 0) {
                        return;
                    }
                    
                    this.simulationLoading = true;
                    this.streamingEvents = [];
                    
                    // Füge User-Message zu Chat-Anzeige hinzu
                    if (step === 0) {
                        const userMsg = this.simulationMessage.trim();
                        if (userMsg) {
                            this.chatMessages = [...this.chatMessages, {
                                type: 'message',
                                role: 'user',
                                content: userMsg,
                                timestamp: new Date().toISOString()
                            }];
                            
                            // Test: Füge sofort ein Test-Event hinzu
                            this.chatMessages = [...this.chatMessages, {
                                type: 'event',
                                message: '🚀 Stream gestartet...',
                                timestamp: new Date().toISOString(),
                                eventType: 'test',
                                eventData: {}
                            }];
                        }
                    }
                    
                    if (step === 0) {
                        this.simulationResult = null;
                        this.debugCopied = false;
                    }
                    
                    // Erstelle Payload
                    const payload = {
                        message: this.simulationMessage,
                        options: {},
                        chat_history: this.chatHistory,
                        session_id: this.sessionId || this.generateSessionId(),
                    };
                    
                    if (step > 0) {
                        payload.step = step;
                        payload.previous_result = previousResult;
                        payload.user_input = userInput;
                    }
                    
                    // Verwende Fetch mit ReadableStream für SSE (POST mit JSON)
                    const url = '{{ route("core.tools.playground.simulate.stream") }}';
                    
                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'text/event-stream',
                            },
                            body: JSON.stringify(payload)
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();
                        let buffer = '';
                        let currentSimulation = null;
                        
                        // Parse SSE-Stream in Echtzeit
                        let currentEventType = 'message';
                        let currentEventData = null;
                        let currentDataLine = '';
                        
                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) {
                                // Verarbeite letztes Event falls vorhanden
                                if (currentDataLine.trim()) {
                                    try {
                                        currentEventData = JSON.parse(currentDataLine.trim());
                                    } catch (e) {
                                        currentEventData = currentDataLine.trim();
                                    }
                                }
                                if (currentEventData !== null) {
                                    this.handleStreamEvent(currentEventType, currentEventData);
                                    if (currentEventType === 'simulation.complete') {
                                        currentSimulation = currentEventData;
                                    }
                                }
                                break;
                            }
                            
                        const chunk = decoder.decode(value, { stream: true });
                        buffer += chunk;
                        
                        // DEBUG: Log raw chunk
                        if (chunk.length > 0) {
                            console.log('[SSE Chunk]', chunk.substring(0, 100));
                        }
                        
                        // Verarbeite Buffer Zeile für Zeile
                        let newlineIndex;
                        while ((newlineIndex = buffer.indexOf('\n')) !== -1) {
                            const line = buffer.substring(0, newlineIndex);
                            buffer = buffer.substring(newlineIndex + 1);
                            
                            const trimmed = line.trim();
                            
                            if (trimmed.startsWith('event:')) {
                                // Neues Event beginnt - verarbeite vorheriges Event SOFORT
                                if (currentDataLine.trim()) {
                                    try {
                                        currentEventData = JSON.parse(currentDataLine.trim());
                                    } catch (e) {
                                        console.warn('[SSE] Failed to parse data before new event:', currentDataLine.trim(), e);
                                        currentEventData = currentDataLine.trim();
                                    }
                                }
                                if (currentEventData !== null) {
                                    // SOFORT verarbeiten - nicht warten
                                    this.handleStreamEvent(currentEventType, currentEventData);
                                    if (currentEventType === 'simulation.complete') {
                                        currentSimulation = currentEventData;
                                    }
                                }
                                currentEventType = trimmed.substring(6).trim();
                                currentEventData = null;
                                currentDataLine = '';
                                console.log('[SSE] New event type:', currentEventType);
                            } else if (trimmed.startsWith('data:')) {
                                // Data-Zeile - sammle Daten (kann mehrzeilig sein)
                                const dataStr = trimmed.substring(5);
                                currentDataLine += dataStr;
                            } else if (trimmed === '') {
                                // Leere Zeile = Event-Ende - SOFORT verarbeiten
                                if (currentDataLine.trim()) {
                                    try {
                                        currentEventData = JSON.parse(currentDataLine.trim());
                                        console.log('[SSE] Parsed event data:', currentEventType, currentEventData);
                                    } catch (e) {
                                        console.warn('[SSE] Failed to parse event data:', currentDataLine.trim(), e);
                                        currentEventData = currentDataLine.trim();
                                    }
                                }
                                if (currentEventData !== null) {
                                    // SOFORT verarbeiten - nicht warten
                                    this.handleStreamEvent(currentEventType, currentEventData);
                                    if (currentEventType === 'simulation.complete') {
                                        currentSimulation = currentEventData;
                                    }
                                }
                                currentEventType = 'message';
                                currentEventData = null;
                                currentDataLine = '';
                            }
                        }
                        }
                        
                        // Finale Simulation-Daten setzen
                        if (currentSimulation) {
                            this.simulationResult = currentSimulation;
                            
                            // Update Chat-Historie
                            if (currentSimulation.chat_history) {
                                this.chatHistory = currentSimulation.chat_history;
                            }
                        }
                        
                        // Leere Input-Feld
                        if (step === 0) {
                            this.simulationMessage = '';
                        }
                        
                        // Scroll zu neuem Message
                        this.$nextTick(() => {
                            if (this.$refs.chatContainer) {
                                this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
                            }
                        });
                        
                    } catch (e) {
                        console.error('Streaming Error:', e);
                        this.streamingEvents.push({
                            type: 'error',
                            message: 'Fehler beim Streaming: ' + e.message,
                            timestamp: new Date().toISOString(),
                        });
                        alert('❌ Fehler beim Streaming:\n\n' + e.message);
                    } finally {
                        this.simulationLoading = false;
                    }
                },
                
                handleStreamEvent(eventType, eventData) {
                    // DEBUG: Log Event
                    console.log('[SSE Event]', eventType, eventData);
                    
                    // Events werden NICHT mehr im Chat angezeigt (nur Messages)
                    // Füge nur zu streamingEvents hinzu (für Debugging)
                    if (!this.streamingEvents) {
                        this.streamingEvents = [];
                    }
                    this.streamingEvents = [...this.streamingEvents, {
                        type: eventType,
                        data: eventData,
                        timestamp: new Date().toISOString(),
                        message: this.getEventMessage(eventType, eventData)
                    }];
                    
                    // Begrenze Events auf 50 (älteste zuerst entfernen)
                    if (this.streamingEvents.length > 50) {
                        this.streamingEvents = this.streamingEvents.slice(-50);
                    }
                    
                    // Update Simulation-Result für bestimmte Events
                    if (eventType === 'simulation.start') {
                        if (!this.simulationResult) {
                            this.simulationResult = {
                                timestamp: eventData.timestamp,
                                user_message: eventData.user_message,
                                steps: [],
                                execution_flow: [],
                            };
                        }
                    } else if (eventType.startsWith('step.')) {
                        if (!this.simulationResult) {
                            this.simulationResult = { steps: [], execution_flow: [] };
                        }
                        if (!this.simulationResult.steps) {
                            this.simulationResult.steps = [];
                        }
                        this.simulationResult.steps.push({
                            step: eventData.step || this.simulationResult.steps.length,
                            name: eventData.name || eventType,
                            description: eventData.description || '',
                            result: eventData.result,
                            timestamp: new Date().toISOString(),
                        });
                    } else if (eventType === 'tool.execution.result') {
                        if (!this.simulationResult) {
                            this.simulationResult = { execution_flow: [] };
                        }
                        if (!this.simulationResult.execution_flow) {
                            this.simulationResult.execution_flow = [];
                        }
                        this.simulationResult.execution_flow.push({
                            tool: eventData.tool,
                            result: {
                                success: eventData.success,
                                data: eventData.data,
                            },
                            execution_time_ms: eventData.execution_time_ms,
                        });
                    } else if (eventType === 'iteration.final_response') {
                        console.log('[Final Response]', eventData);
                        if (!this.simulationResult) {
                            this.simulationResult = {};
                        }
                        this.simulationResult.final_response = {
                            type: 'direct_answer',
                            content: eventData.content,
                            iterations: eventData.iterations,
                        };
                        
                        // WICHTIG: Füge finale Antwort SOFORT als Chat-Message hinzu
                        if (eventData.content) {
                            // Initialisiere chatMessages falls nicht vorhanden
                            if (!this.chatMessages) {
                                this.chatMessages = [];
                            }
                            
                            // Entferne letzte Assistant-Message falls vorhanden (um Duplikate zu vermeiden)
                            let newMessages = [...this.chatMessages];
                            const lastMsg = newMessages[newMessages.length - 1];
                            if (lastMsg && lastMsg.role === 'assistant' && lastMsg.type === 'message') {
                                newMessages.pop();
                            }
                            
                            // Füge neue Assistant-Message hinzu
                            const assistantMessage = {
                                type: 'message',
                                role: 'assistant',
                                content: eventData.content,
                                timestamp: new Date().toISOString(),
                            };
                            newMessages.push(assistantMessage);
                            
                            // Setze neues Array - Alpine.js sieht die Änderung sofort
                            this.chatMessages = newMessages;
                            console.log('[Chat Messages after final response]', this.chatMessages.length, assistantMessage);
                            
                            // Force Alpine.js Update
                            requestAnimationFrame(() => {
                                this.chatMessages = [...this.chatMessages];
                                
                                // Auto-Scroll
                                setTimeout(() => {
                                    const container = this.$refs.chatContainer;
                                    if (container) {
                                        container.scrollTop = container.scrollHeight;
                                    }
                                }, 100);
                            });
                        }
                    } else if (eventType === 'simulation.complete') {
                        console.log('[Simulation Complete]', eventData);
                        // Finale Antwort sollte bereits bei iteration.final_response hinzugefügt worden sein
                        // Falls nicht, füge sie hier hinzu (Fallback)
                        if (eventData.final_response?.content) {
                            // Prüfe ob bereits eine Assistant-Message mit diesem Content vorhanden ist
                            const hasAssistantMsg = this.chatMessages.some(msg => 
                                msg.role === 'assistant' && 
                                msg.type === 'message' && 
                                msg.content === eventData.final_response.content
                            );
                            
                            console.log('[Has Assistant Msg]', hasAssistantMsg);
                            
                            if (!hasAssistantMsg) {
                                // Entferne letzte Assistant-Message falls vorhanden (um Duplikate zu vermeiden)
                                let newMessages = [...this.chatMessages];
                                const lastMsg = newMessages[newMessages.length - 1];
                                if (lastMsg && lastMsg.role === 'assistant' && lastMsg.type === 'message') {
                                    newMessages.pop();
                                }
                                
                                // Füge finale Antwort hinzu (SOFORT)
                                newMessages.push({
                                    type: 'message',
                                    role: 'assistant',
                                    content: eventData.final_response.content,
                                    timestamp: new Date().toISOString(),
                                });
                                
                                // Setze neues Array - Alpine.js sieht die Änderung sofort
                                this.chatMessages = newMessages;
                                console.log('[Chat Messages after complete]', this.chatMessages.length);
                                
                                // Auto-Scroll nach DOM-Update
                                this.$nextTick(() => {
                                    requestAnimationFrame(() => {
                                        const container = this.$refs.chatContainer;
                                        if (container) {
                                            container.scrollTop = container.scrollHeight;
                                        }
                                    });
                                });
                            }
                        }
                    }
                },
                
                getCombinedChatHistory() {
                    // Kombiniere Chat-Messages und Events in chronologischer Reihenfolge
                    // WICHTIG: Erstelle neue Array-Referenz für Alpine.js Reaktivität
                    const combined = this.chatMessages ? [...this.chatMessages] : [];
                    
                    // Sortiere nach Timestamp
                    combined.sort((a, b) => {
                        const timeA = new Date(a.timestamp || 0).getTime();
                        const timeB = new Date(b.timestamp || 0).getTime();
                        return timeA - timeB;
                    });
                    
                    // DEBUG: Log für Debugging
                    if (combined.length > 0 && combined.length !== (this.chatMessages?.length || 0)) {
                        console.log('[getCombinedChatHistory]', combined.length, 'messages');
                    }
                    
                    return combined;
                },
                
                getEventMessage(eventType, eventData) {
                    const messages = {
                        'simulation.start': '🚀 Simulation gestartet',
                        'simulation.complete': '✅ Simulation abgeschlossen',
                        'simulation.error': '❌ Fehler: ' + (eventData.error || 'Unbekannt'),
                        'step.discovery': '🔍 Tool Discovery',
                        'step.semantic_analysis': '🧠 Semantische Analyse',
                        'step.openai.init': '🤖 OpenAI Service initialisiert',
                        'step.multistep.start': '🔄 Multi-Step gestartet',
                        'iteration.start': `🔄 Iteration ${eventData.iteration || '?'} gestartet`,
                        'iteration.tool_calls': `🔧 ${eventData.tool_calls?.length || 0} Tool-Calls`,
                        'iteration.final_response': '💬 Finale Antwort erhalten',
                        'iteration.max_reached': '⚠️ Maximale Iterationen erreicht',
                        'tool.execution.start': `🔧 Tool: ${eventData.tool || 'Unbekannt'}`,
                        'tool.execution.result': `✅ Tool: ${eventData.tool || 'Unbekannt'} (${eventData.execution_time_ms || 0}ms)`,
                        'tool.execution.error': `❌ Tool: ${eventData.tool || 'Unbekannt'} - ${eventData.error || 'Fehler'}`,
                        'tool.injection': `💉 Tools injiziert: ${eventData.tools?.length || 0}`,
                        'tool.loop_warning': `⚠️ Loop erkannt: ${eventData.tool || 'Unbekannt'} (${eventData.count || 0}x)`,
                        'verification.issues': '⚠️ Verifikation: Probleme gefunden',
                    };
                    
                    return messages[eventType] || `${eventType}: ${JSON.stringify(eventData).substring(0, 100)}`;
                },

                async continueWithUserInput(userInput) {
                    // Fortsetzen der Simulation mit User-Input
                    if (!this.simulationResult) {
                        alert('Keine Simulation vorhanden');
                        return;
                    }

                    // Prüfe ob User-Input erforderlich ist
                    if (!this.simulationResult.requires_user_input && 
                        this.simulationResult.final_response?.type !== 'user_input_required') {
                        alert('Kein User-Input erforderlich');
                        return;
                    }

                    // Erstelle previous_result aus aktueller Simulation
                    const previousResult = {
                        next_tool: this.simulationResult.next_tool || this.simulationResult.final_response?.next_tool,
                        next_tool_args: this.simulationResult.next_tool_args || this.simulationResult.final_response?.next_tool_args || {},
                        requires_user_input: true,
                        user_input_data: this.simulationResult.user_input_data || this.simulationResult.final_response?.data,
                    };

                    // Bestimme nächsten Schritt (aktueller Schritt + 1)
                    const currentStep = this.simulationResult.step || 0;
                    const nextStep = currentStep + 1;

                    // Führe Simulation mit User-Input fort
                    await this.runSimulation(nextStep, previousResult, String(userInput));
                },

                downloadDebugExport() {
                    const content = this.getDebugExport();
                    const blob = new Blob([content], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `mcp-simulation-${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                },

                getDebugExport() {
                    if (!this.simulationResult) {
                        return 'Keine Simulation-Daten verfügbar';
                    }
                    
                    const exportData = {
                        timestamp: new Date().toISOString(),
                        user_message: this.simulationMessage,
                        simulation: this.simulationResult,
                        platform_info: {
                            url: window.location.href,
                            user_agent: navigator.userAgent,
                        }
                    };
                    
                    return JSON.stringify(exportData, null, 2);
                },

                async copyDebugExport() {
                    try {
                        const content = this.getDebugExport();
                        await navigator.clipboard.writeText(content);
                        this.debugCopied = true;
                        setTimeout(() => {
                            this.debugCopied = false;
                        }, 2000);
                    } catch (e) {
                        // Fallback für ältere Browser
                        const textarea = document.createElement('textarea');
                        textarea.value = this.getDebugExport();
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        try {
                            document.execCommand('copy');
                            this.debugCopied = true;
                            setTimeout(() => {
                                this.debugCopied = false;
                            }, 2000);
                        } catch (err) {
                            alert('Kopieren fehlgeschlagen. Bitte manuell kopieren.');
                        }
                        document.body.removeChild(textarea);
                    }
                },

                async continueWithUserInput(userInput) {
                    // Fortsetzen der Simulation mit User-Input
                    if (!this.simulationResult) {
                        alert('Keine Simulation vorhanden');
                        return;
                    }

                    // Erstelle previous_result aus aktueller Simulation
                    const previousResult = {
                        next_tool: this.simulationResult.next_tool || this.simulationResult.final_response?.next_tool,
                        next_tool_args: this.simulationResult.next_tool_args || this.simulationResult.final_response?.next_tool_args || {},
                        requires_user_input: true,
                        user_input_data: this.simulationResult.user_input_data || this.simulationResult.final_response?.data,
                    };

                    // Bestimme nächsten Schritt (aktueller Schritt + 1)
                    const currentStep = this.simulationResult.step || 0;
                    const nextStep = currentStep + 1;

                    // Führe Simulation mit User-Input fort
                    await this.runSimulation(nextStep, previousResult, String(userInput));
                },

                async runDiscovery() {
                    this.discoveryLoading = true;
                    this.discoveryResult = null;

                    try {
                        const filters = {};
                        if (this.discoveryFilters.search) filters.search = this.discoveryFilters.search;
                        if (this.discoveryFilters.module) filters.module = this.discoveryFilters.module;
                        if (this.discoveryFilters.category) filters.category = this.discoveryFilters.category;
                        if (this.discoveryFilters.tag) filters.tag = this.discoveryFilters.tag;
                        if (this.discoveryFilters.read_only) filters.read_only = true;

                        const response = await fetch('{{ route("core.tools.playground.discovery") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ filters })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.discoveryResult = data.result;
                        } else {
                            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                        }
                    } catch (e) {
                        alert('Fehler: ' + e.message);
                    } finally {
                        this.discoveryLoading = false;
                    }
                },

                async submitToolRequest() {
                    if (!this.toolRequest.description) {
                        alert('Bitte gib eine Beschreibung ein!');
                        return;
                    }

                    this.toolRequestLoading = true;
                    this.toolRequestResult = null;

                    try {
                        const response = await fetch('{{ route("core.tools.playground.request") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.toolRequest)
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.toolRequestResult = data.result;
                            // Reset form
                            this.toolRequest = {
                                description: '',
                                use_case: '',
                                suggested_name: '',
                                category: '',
                                module: ''
                            };
                            // Reload requests
                            this.loadToolRequests();
                        } else {
                            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
                        }
                    } catch (e) {
                        alert('Fehler: ' + e.message);
                    } finally {
                        this.toolRequestLoading = false;
                    }
                },

                async loadToolRequests() {
                    this.toolRequestsLoading = true;

                    try {
                        const response = await fetch('{{ route("core.tools.playground.requests") }}');
                        const data = await response.json();
                        if (data.success) {
                            this.toolRequests = data.requests || [];
                        }
                    } catch (e) {
                        console.error('Fehler beim Laden der Requests:', e);
                    } finally {
                        this.toolRequestsLoading = false;
                    }
                },

                generateSessionId() {
                    if (!this.sessionId) {
                        this.sessionId = 'playground_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    }
                    return this.sessionId;
                },
                
                async clearChat() {
                    if (confirm('Möchtest du wirklich den Chat leeren? Ein neuer Thread startet dann.')) {
                        // Lösche Session-Historie im Backend
                        if (this.sessionId) {
                            try {
                                await fetch('{{ route("core.tools.playground.clear") }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        session_id: this.sessionId
                                    })
                                });
                            } catch (e) {
                                console.error('Fehler beim Löschen der Session-Historie:', e);
                            }
                        }
                        
                        // Frontend zurücksetzen
                        this.chatMessages = [];
                        this.chatHistory = [];
                        this.simulationResult = null;
                        this.simulationMessage = '';
                        this.sessionId = null; // Neue Session-ID wird beim nächsten Aufruf generiert
                        
                        // Scroll zurücksetzen
                        this.$nextTick(() => {
                            if (this.$refs.chatContainer) {
                                this.$refs.chatContainer.scrollTop = 0;
                            }
                        });
                    }
                },
                
                init() {
                    // Auto-load tools on page load
                    this.$nextTick(() => {
                        this.loadTools();
                        this.loadToolRequests();
                        this.generateSessionId();
                    });
                }

            }));
        });
    </script>
</x-ui-page>

