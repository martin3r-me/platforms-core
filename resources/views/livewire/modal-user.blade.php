<x-ui-modal persistent="true" wire:model="modalShow">
    <x-slot name="header">
        User
    </x-slot>

    {{-- Slot-Inhalt = Body --}}
    <div class="grid grid-cols-2 gap-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui-button variant="danger">
                Logout
            </x-ui-button>
        </form>
    </div>

    <x-slot name="footer">
        
    </x-slot>
</x-ui-modal>