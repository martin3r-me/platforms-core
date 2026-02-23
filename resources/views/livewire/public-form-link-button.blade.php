<div x-data="{ copied: false }" class="inline-flex">
    @if(!$hasLink)
        {{-- No link yet: show create button --}}
        <button
            type="button"
            wire:click="createLink"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg
                   text-[var(--ui-muted)] hover:text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/5
                   border border-[var(--ui-border)]/40 hover:border-[var(--ui-primary)]/30
                   transition-all duration-150"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
            <span wire:loading.remove wire:target="createLink">Public Link</span>
            <span wire:loading wire:target="createLink">...</span>
        </button>
    @else
        {{-- Link exists: show URL + copy + toggle --}}
        <div class="inline-flex items-center gap-1">
            {{-- Copy button --}}
            <button
                type="button"
                x-on:click="
                    navigator.clipboard.writeText('{{ $linkUrl }}');
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                "
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-150
                       {{ $isActive
                           ? 'text-emerald-600 bg-emerald-50 border border-emerald-200/60 hover:bg-emerald-100'
                           : 'text-gray-400 bg-gray-50 border border-gray-200/60 hover:bg-gray-100'
                       }}"
                title="{{ $linkUrl }}"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                <span x-show="!copied">Link kopieren</span>
                <span x-show="copied" x-cloak>Kopiert!</span>
            </button>

            {{-- Toggle active/inactive --}}
            <button
                type="button"
                wire:click="toggleActive"
                wire:loading.attr="disabled"
                class="inline-flex items-center px-2 py-1.5 text-xs rounded-lg transition-all duration-150
                       {{ $isActive
                           ? 'text-emerald-500 hover:text-red-500 hover:bg-red-50'
                           : 'text-gray-400 hover:text-emerald-500 hover:bg-emerald-50'
                       }}"
                title="{{ $isActive ? 'Link deaktivieren' : 'Link aktivieren' }}"
            >
                @if($isActive)
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @else
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                @endif
            </button>
        </div>
    @endif
</div>
