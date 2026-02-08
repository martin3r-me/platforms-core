@props([
    'definitions' => [],
    'title' => 'Extra-Felder',
    'columns' => 2,
])

@if(count($definitions) > 0)
    <div {{ $attributes->merge(['class' => 'bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 p-6']) }}>
        <div class="flex items-center gap-2 mb-6">
            @svg('heroicon-o-squares-plus', 'w-5 h-5 text-[var(--ui-primary)]')
            <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $title }}</h2>
        </div>
        <x-core-extra-fields-form :definitions="$definitions" :columns="$columns" />
    </div>
@endif
