<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        Module wechseln
    </x-slot>

    <div class="grid grid-cols-2 gap-4">
        @foreach($modules as $module)
            @php
                // Route-Name aus der Modul-Navigation holen
                $routeName = $module['navigation']['route'] ?? null;

                // Falls Subdomain, die URL automatisch durch route() aufbauen
                $finalUrl = $routeName
                    ? route($routeName)   // Laravel kümmert sich um Domain/Subdomain
                    : ($module['url'] ?? '#');
            @endphp

            <a href="{{ $finalUrl }}"
               class="d-flex items-center gap-2 p-4 rounded-md border border-solid border-1 transition hover:border-primary hover:bg-primary-10">

                {{-- Debug-Ausgabe --}}
                <div class="text-xs text-gray-500">
                    <strong>Route Name:</strong> {{ $routeName ?? 'NULL' }}<br>
                    <strong>Final URL:</strong> {{ $finalUrl }}
                </div>

                @if(!empty($module['icon']))
                    <x-dynamic-component :component="$module['icon']" class="w-5 h-5 text-primary" />
                @else
                    <x-heroicon-o-cube class="w-5 h-5 text-primary" />
                @endif
                <span class="font-medium text-secondary">{{ $module['title'] ?? $module['label'] ?? 'Modul' }}</span>
            </a>
        @endforeach
    </div>

    <x-slot name="footer"></x-slot>
</x-ui-modal>