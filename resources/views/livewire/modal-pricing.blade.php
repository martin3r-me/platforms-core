<x-ui-modal persistent="true" wire:model="modalShow">
    <x-slot name="header">
        Nutzungsgebühren im Überblick
    </x-slot>

    {{-- Modal Body --}}
    <div class="p-4">
        <h2 class="text-lg font-semibold mb-2">Kostenübersicht für diesen Monat</h2>
        @if($monthlyUsages && count($monthlyUsages))
            <table class="w-full text-sm border rounded">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-2 py-1 text-left">Datum</th>
                        <th class="px-2 py-1 text-left">Modul</th>
                        <th class="px-2 py-1 text-left">Typ</th>
                        <th class="px-2 py-1 text-right">Anzahl</th>
                        <th class="px-2 py-1 text-right">Einzelpreis</th>
                        <th class="px-2 py-1 text-right">Gesamt</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($monthlyUsages as $usage)
                    <tr>
                        <td class="px-2 py-1">{{ \Illuminate\Support\Carbon::parse($usage->usage_date)->format('d.m.Y') }}</td>
                        <td class="px-2 py-1">{{ $usage->label }}</td>
                        <td class="px-2 py-1">{{ $usage->billable_type }}</td>
                        <td class="px-2 py-1 text-right">{{ $usage->count }}</td>
                        <td class="px-2 py-1 text-right">{{ number_format($usage->cost_per_unit, 4, ',', '.') }} €</td>
                        <td class="px-2 py-1 text-right">{{ number_format($usage->total_cost, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="mt-4 font-bold text-right">
                Monatssumme: {{ number_format($monthlyTotal, 2, ',', '.') }} €
            </div>
        @else
            <div class="text-gray-500 text-sm py-4">
                Für diesen Monat liegen noch keine Nutzungsdaten vor.
            </div>
        @endif
    </div>

    <x-slot name="footer">
        <x-ui-button variant="info" wire:click="closeModal">Schließen</x-ui-button>
    </x-slot>
</x-ui-modal>