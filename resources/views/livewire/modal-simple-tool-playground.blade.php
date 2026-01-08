<div>
    <x-ui-modal size="2xl" :hideFooter="true" wire:model="open" :closeButton="true">
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
        </script>

        <div class="h-[calc(90vh-5rem)] overflow-hidden">
            @include('platform::livewire.simple-tool-playground-modal-inner')
        </div>
    </x-ui-modal>
</div>


