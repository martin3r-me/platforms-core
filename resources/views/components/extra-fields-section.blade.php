@props([
    'definitions' => [],
    'title' => 'Extra-Felder',
    'columns' => 2,
    'ownContextType' => null,
    'ownContextId' => null,
    'showManageButton' => false,
])

@php
    $hasDefinitions = count($definitions) > 0;
    $hasInheritedDefinitions = collect($definitions)->contains('is_inherited', true);
    $hasOwnDefinitions = collect($definitions)->contains('is_inherited', false);
    $canManageOwn = $ownContextType && $ownContextId;
@endphp

@if($hasDefinitions || $canManageOwn)
    <div {{ $attributes->merge(['class' => 'bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6']) }}>
        <div class="flex items-center justify-between gap-2 mb-6">
            <div class="flex items-center gap-2">
                @svg('heroicon-o-squares-plus', 'w-5 h-5 text-[var(--ui-primary)]')
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $title }}</h2>
            </div>
            @if($canManageOwn)
                <button
                    type="button"
                    x-data
                    @click="
                        $dispatch('extrafields', { context_type: '{{ addslashes($ownContextType) }}', context_id: {{ $ownContextId }} });
                        $dispatch('extrafields:open');
                    "
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-[var(--ui-primary)] hover:text-[var(--ui-primary)]/80 hover:bg-[var(--ui-primary-5)] rounded-lg transition-colors"
                >
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Eigene Felder</span>
                </button>
            @endif
        </div>

        @if($hasDefinitions)
            {{-- Geerbte Felder --}}
            @php
                $inheritedDefinitions = collect($definitions)->where('is_inherited', true)->values()->toArray();
            @endphp
            @if(count($inheritedDefinitions) > 0)
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider">Geerbte Felder</span>
                        <span class="text-xs text-[var(--ui-muted)]">(vom übergeordneten Objekt)</span>
                    </div>
                    <x-core-extra-fields-form :definitions="$inheritedDefinitions" :columns="$columns" />
                </div>
            @endif

            {{-- Eigene Felder --}}
            @php
                $ownDefinitions = collect($definitions)->where('is_inherited', false)->values()->toArray();
            @endphp
            @if(count($ownDefinitions) > 0)
                <div>
                    @if(count($inheritedDefinitions) > 0)
                        <div class="flex items-center gap-2 mb-3 pt-4 border-t border-[var(--ui-border)]/40">
                            <span class="text-xs font-medium text-[var(--ui-muted)] uppercase tracking-wider">Eigene Felder</span>
                        </div>
                    @endif
                    <x-core-extra-fields-form :definitions="$ownDefinitions" :columns="$columns" />
                </div>
            @endif

            {{-- Fallback wenn keine Kategorisierung vorhanden (is_inherited nicht gesetzt) --}}
            @if(count($inheritedDefinitions) === 0 && count($ownDefinitions) === 0)
                <x-core-extra-fields-form :definitions="$definitions" :columns="$columns" />
            @endif
        @else
            <p class="text-sm text-[var(--ui-muted)]">Noch keine Extra-Felder definiert. Klicken Sie auf "Eigene Felder" um welche hinzuzufügen.</p>
        @endif
    </div>
@endif
