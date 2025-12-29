<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="🔧 Tool Playground (MCP Testing)" icon="heroicon-o-wrench-screwdriver" />
    </x-slot>

    <x-ui-page-container spacing="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-[var(--ui-secondary)] mb-2">
                🔧 Tool Playground (MCP Testing)
            </h1>
            <p class="text-[var(--ui-muted)] mb-6">
                Vollständiger Playground zum Testen der Tool-Orchestrierung mit vollem Debug.
            </p>

            <div x-data="toolPlayground" class="space-y-6">
                <!-- Tool Selection -->
                <div class="bg-[var(--ui-muted-5)] rounded-lg p-4">
                        <h2 class="text-xl font-semibold mb-4">Tool auswählen</h2>
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
                            x-text="result.success ? '✅ Erfolg' : '❌ Fehler'"
                            :class="result.success ? 'text-[var(--ui-success)]' : 'text-[var(--ui-danger)]'"
                        ></span>
                    </div>
                    <div x-show="result.data">
                        <span class="font-semibold text-[var(--ui-secondary)]">Daten:</span>
                        <pre class="mt-2 p-3 bg-[var(--ui-muted)] text-[var(--ui-success)] rounded overflow-auto text-xs" x-text="JSON.stringify(result.data, null, 2)"></pre>
                    </div>
                    <div x-show="result.error">
                        <span class="font-semibold text-[var(--ui-danger)]">Fehler:</span>
                        <pre class="mt-2 p-3 bg-[var(--ui-muted)] text-[var(--ui-danger)] rounded overflow-auto text-xs" x-text="JSON.stringify(result.error, null, 2)"></pre>
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

