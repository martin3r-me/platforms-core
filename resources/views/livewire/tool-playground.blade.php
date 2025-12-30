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
                <!-- Tabs: Tool Test vs MCP Simulation -->
                <div class="border-b border-[var(--ui-border)] mb-6">
                    <div class="flex gap-4">
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
                            🎯 MCP Simulation (Vollständiger Flow)
                        </button>
                    </div>
                </div>

                <!-- MCP Simulation Tab -->
                <div x-show="activeTab === 'simulate'" class="space-y-6">
                    <div class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4 text-[var(--ui-secondary)]">🎯 MCP-Simulation</h2>
                        <p class="text-[var(--ui-muted)] mb-4">
                            Simuliere den kompletten Request-Flow: User-Input → Tool-Discovery → Chain-Planning → Execution → Response
                        </p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2 text-[var(--ui-secondary)]">User-Nachricht (natürliche Sprache)</label>
                                <textarea 
                                    x-model="simulationMessage"
                                    placeholder="z.B. 'Erstelle ein Projekt namens Test Projekt'"
                                    class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                                    rows="3"
                                ></textarea>
                                <div class="mt-2 text-xs text-[var(--ui-muted)]">
                                    💡 Beispiele: "Erstelle ein Projekt", "Zeige mir alle Teams", "Lösche Projekt X"
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button 
                                    @click="runSimulation()"
                                    :disabled="simulationLoading"
                                    class="px-4 py-2 bg-[var(--ui-primary)] text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                                >
                                    <span x-show="!simulationLoading">🚀 Vollständige Simulation starten</span>
                                    <span x-show="simulationLoading">⏳ Simuliere...</span>
                                </button>
                                <button 
                                    @click="simulationMessage = 'Erstelle ein Projekt namens Test Projekt'"
                                    class="px-3 py-2 bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] rounded-lg hover:opacity-90 text-sm"
                                >
                                    📝 Beispiel
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Simulation Results -->
                    <div x-show="simulationResult" class="space-y-4">
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
                            <div class="space-y-2">
                                <template x-for="(exec, index) in (simulationResult?.execution_flow || [])">
                                    <div class="p-3 bg-[var(--ui-surface)] rounded border border-[var(--ui-border)]">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="font-mono text-sm font-semibold text-[var(--ui-secondary)]" x-text="exec.tool"></span>
                                            <span :class="exec.result.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]'" x-text="exec.result.success ? '✅' : '❌'"></span>
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)]" x-text="'Argumente: ' + JSON.stringify(exec.arguments)"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Final Response -->
                        <div x-show="simulationResult?.final_response" class="bg-[var(--ui-muted-5)] rounded-lg p-4">
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

                // MCP Simulation
                simulationMessage: 'Erstelle ein Projekt namens Test Projekt',
                simulationLoading: false,
                simulationResult: null,
                debugCopied: false,

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

                async runSimulation() {
                    this.simulationLoading = true;
                    this.simulationResult = null;
                    this.debugCopied = false;

                    try {
                        const response = await fetch('{{ route("core.tools.playground.simulate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                message: this.simulationMessage,
                                options: {}
                            })
                        });

                        // Prüfe Response-Status
                        if (!response.ok) {
                            // Versuche JSON zu parsen, auch bei Fehlern
                            let errorData;
                            try {
                                errorData = await response.json();
                            } catch (jsonError) {
                                // Wenn JSON-Parsing fehlschlägt, verwende Text
                                const errorText = await response.text();
                                errorData = {
                                    success: false,
                                    error: `HTTP ${response.status}: ${errorText}`,
                                    error_details: {
                                        status: response.status,
                                        statusText: response.statusText,
                                        raw_response: errorText.substring(0, 1000)
                                    }
                                };
                            }
                            
                            // Zeige Fehler-Details im Playground
                            this.simulationResult = {
                                timestamp: new Date().toISOString(),
                                user_message: this.simulationMessage,
                                steps: [],
                                tools_discovered: [],
                                execution_flow: [],
                                final_response: {
                                    type: 'error',
                                    message: errorData.error || 'Unbekannter Fehler',
                                    error_details: errorData.error_details || errorData,
                                },
                                error: errorData.error_details || errorData,
                            };
                            
                            // Zeige auch Alert mit Details
                            const errorMsg = errorData.error || 'Unbekannter Fehler';
                            const errorDetails = errorData.error_details ? 
                                `\n\nDetails:\nDatei: ${errorData.error_details.file || 'N/A'}\nZeile: ${errorData.error_details.line || 'N/A'}\nKlasse: ${errorData.error_details.class || 'N/A'}` : 
                                '';
                            alert('❌ Simulation fehlgeschlagen!\n\n' + errorMsg + errorDetails);
                            
                            return;
                        }
                        
                        const data = await response.json();
                        if (data.success) {
                            this.simulationResult = data.simulation;
                        } else {
                            // Zeige Fehler-Details im Playground
                            this.simulationResult = {
                                timestamp: new Date().toISOString(),
                                user_message: this.simulationMessage,
                                steps: data.simulation?.steps || [],
                                tools_discovered: data.simulation?.tools_discovered || [],
                                execution_flow: data.simulation?.execution_flow || [],
                                final_response: {
                                    type: 'error',
                                    message: data.error || 'Unbekannter Fehler',
                                    error_details: data.error_details || data,
                                },
                                error: data.error_details || data,
                            };
                            
                            // Zeige Alert mit Details
                            const errorMsg = data.error || 'Unbekannter Fehler';
                            const errorDetails = data.error_details ? 
                                `\n\nDetails:\nDatei: ${data.error_details.file || 'N/A'}\nZeile: ${data.error_details.line || 'N/A'}\nKlasse: ${data.error_details.class || 'N/A'}` : 
                                '';
                            alert('❌ Simulation fehlgeschlagen!\n\n' + errorMsg + errorDetails);
                        }
                    } catch (e) {
                        // Netzwerk- oder Parsing-Fehler
                        console.error('Simulation Error:', e);
                        this.simulationResult = {
                            timestamp: new Date().toISOString(),
                            user_message: this.simulationMessage,
                            steps: [],
                            tools_discovered: [],
                            execution_flow: [],
                            final_response: {
                                type: 'error',
                                message: 'Netzwerk- oder Parsing-Fehler: ' + e.message,
                            },
                            error: {
                                message: e.message,
                                stack: e.stack,
                            },
                        };
                        alert('❌ Fehler beim Senden der Anfrage:\n\n' + e.message);
                    } finally {
                        this.simulationLoading = false;
                    }
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

                init() {
                    // Auto-load tools on page load
                    this.$nextTick(() => {
                        this.loadTools();
                    });
                }

            }));
        });
    </script>
</x-ui-page>

