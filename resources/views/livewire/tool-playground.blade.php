<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="🔧 Tool Playground (MCP Testing)" icon="heroicon-o-wrench-screwdriver" />
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
                🔧 Tool Playground (MCP Testing)
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mb-8">
                Vollständiger Playground zum Testen der Tool-Orchestrierung mit vollem Debug.
            </p>

                <div x-data="toolPlayground" class="space-y-6">
                    <!-- Tool Selection -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4">Tool auswählen</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Tool Name</label>
                                <input 
                                    type="text" 
                                    x-model="toolName"
                                    placeholder="z.B. planner.projects.create"
                                    class="w-full px-3 py-2 border rounded-lg dark:bg-gray-600 dark:border-gray-500"
                                    list="tools-list"
                                >
                                <datalist id="tools-list">
                                    <template x-for="tool in availableTools">
                                        <option :value="tool.name" x-text="tool.name"></option>
                                    </template>
                                </datalist>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Argumente (JSON)</label>
                                <textarea 
                                    x-model="argumentsJson"
                                    placeholder='{"name": "Test Projekt"}'
                                    class="w-full px-3 py-2 border rounded-lg font-mono text-sm dark:bg-gray-600 dark:border-gray-500"
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
                            class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                        >
                            🔄 Tools laden
                        </button>
                        <button 
                            @click="testTool()"
                            :disabled="loading"
                            class="mt-4 ml-2 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50"
                        >
                            <span x-show="!loading">▶️ Tool testen</span>
                            <span x-show="loading">⏳ Lädt...</span>
                        </button>
                    </div>

                    <!-- Results -->
                    <div x-show="result" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4">Ergebnis</h2>
                        <div class="space-y-4">
                            <div>
                                <span class="font-semibold">Status:</span>
                                <span 
                                    x-text="result.success ? '✅ Erfolg' : '❌ Fehler'"
                                    :class="result.success ? 'text-green-600' : 'text-red-600'"
                                ></span>
                            </div>
                            <div x-show="result.data">
                                <span class="font-semibold">Daten:</span>
                                <pre class="mt-2 p-3 bg-gray-800 text-green-400 rounded overflow-auto text-xs" x-text="JSON.stringify(result.data, null, 2)"></pre>
                            </div>
                            <div x-show="result.error">
                                <span class="font-semibold text-red-600">Fehler:</span>
                                <pre class="mt-2 p-3 bg-gray-800 text-red-400 rounded overflow-auto text-xs" x-text="JSON.stringify(result.error, null, 2)"></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Debug Info -->
                    <div x-show="debug" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4">🔍 Debug-Informationen</h2>
                        <details class="space-y-2">
                            <summary class="cursor-pointer font-medium">Debug-Details anzeigen</summary>
                            <pre class="mt-2 p-3 bg-gray-800 text-yellow-400 rounded overflow-auto text-xs" x-text="JSON.stringify(debug, null, 2)"></pre>
                        </details>
                    </div>

                    <!-- Available Tools -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4">Verfügbare Tools (<span x-text="availableTools.length"></span>)</h2>
                        <div class="max-h-96 overflow-y-auto">
                            <div class="space-y-2">
                                <template x-for="tool in availableTools">
                                    <div class="p-2 bg-white dark:bg-gray-600 rounded border">
                                        <div class="font-mono text-sm font-semibold" x-text="tool.name"></div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1" x-text="tool.description"></div>
                                        <div class="mt-2 flex gap-2">
                                            <span 
                                                x-show="tool.has_dependencies"
                                                class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs"
                                            >
                                                Dependencies
                                            </span>
                                            <span 
                                                x-show="tool.has_metadata"
                                                class="px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded text-xs"
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

                init() {
                    // Auto-load tools on page load
                    this.loadTools();
                }
            }));
        });
    </script>
</x-ui-page>

