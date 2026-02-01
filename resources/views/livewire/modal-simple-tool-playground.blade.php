<div>
    {{-- Use a dedicated in-between size: big enough to work, but not full-screen. --}}
    <x-ui-modal size="wide" hideFooter="1" wire:model="open" :closeButton="true">
        <x-slot name="header">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                        @svg('heroicon-o-sparkles', 'w-6 h-6 text-[var(--ui-primary)]')
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Simple Playground</h3>
                    <p class="text-sm text-[var(--ui-muted)]">Streaming, Reasoning/Thinking, Tools (Debug) – im Modal.</p>
                </div>
                {{-- Tabs in the modal header (requested) --}}
                <div class="flex items-center gap-2">
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('simple-playground:set-tab', { detail: { tab: 'chat' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Chat
                    </button>
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('simple-playground:set-tab', { detail: { tab: 'models' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Model settings
                    </button>
                    <button type="button"
                        x-data
                        @click="window.dispatchEvent(new CustomEvent('simple-playground:set-tab', { detail: { tab: 'settings' } }))"
                        class="px-3 py-1.5 rounded-md text-sm border transition bg-[var(--ui-bg)] text-[var(--ui-muted)] border-[var(--ui-border)] hover:text-[var(--ui-secondary)]"
                    >
                        Settings
                    </button>
                </div>
            </div>
        </x-slot>

        @php
            $simpleStreamUrl = route('core.tools.simple.stream');
            $simpleModelsUrl = route('core.tools.simple.models');
        @endphp

        <script>
            // URLs + Context für das Modal-Playground (keine Blade-Parsing-Probleme im @verbatim Block unten).
            window.__simpleStreamUrl = @json($simpleStreamUrl);
            window.__simpleModelsUrl = @json($simpleModelsUrl);
            window.__simplePlaygroundContext = @json($context);

            // Playground-Context via Livewire-Event aktualisieren (z.B. wenn Task-Seite den Context setzt)
            // Livewire 3: dispatch() feuert Livewire-Events, die über Livewire.on() gehört werden
            document.addEventListener('livewire:init', () => {
                Livewire.on('playground-context-updated', (data) => {
                    window.__simplePlaygroundContext = data.context || data[0]?.context || data;
                    // Trigger browser event für inner modal listener
                    window.dispatchEvent(new CustomEvent('playground-context-updated', { detail: { context: window.__simplePlaygroundContext } }));
                });
            });
        </script>

        {{-- x-ui-modal (non-full) has a padded, scrollable body already.
           We want the playground to use the full available height/width, so we cancel padding here. --}}
        {{-- Keep this wrapper as a block-level full-width container.
             (Some browsers/layouts can shrink flex children unexpectedly; the inner grid must stretch.) --}}
        <div class="-m-6 w-full h-full min-h-0 min-w-0 overflow-hidden" style="width:100%;">
            @include('platform::livewire.simple-tool-playground-modal-inner', ['coreAiModels' => $coreAiModels ?? collect()])
        </div>
    </x-ui-modal>
</div>


