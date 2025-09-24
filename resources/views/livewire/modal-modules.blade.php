<div x-data="{ tab: 'modules' }" x-init="
    window.addEventListener('open-modal-modules', (e) => { tab = e?.detail?.tab || 'modules'; });
">
<x-ui-modal size="lg" wire:model="modalShow">
        <x-slot name="header">
            <div class="d-flex items-center justify-between w-full">
                <div class="font-medium">Zentrale Steuerung</div>
                <div class="text-xs text-gray-500">Tipp: ⌘K / M öffnet dieses Menü</div>
            </div>
            <div class="d-flex gap-3 mt-3 border-b pb-1">
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'modules' }" @click="tab = 'modules'">Module</button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'team' }" @click="tab = 'team'">Team</button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'billing' }" @click="tab = 'billing'">Abrechnung</button>
                <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer" :class="{ 'font-bold border-b-2 border-primary' : tab === 'account' }" @click="tab = 'account'">Konto</button>
                @if(auth()->user()?->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                    <button type="button" class="px-2 py-1 bg-transparent border-0 cursor-pointer ml-auto" :class="{ 'font-bold border-b-2 border-primary' : tab === 'matrix' }" @click="tab = 'matrix'">Matrix</button>
                @endif
            </div>
        </x-slot>

        

        <x-slot name="footer">
            <div class="flex justify-start">
                @if(auth()->user()->currentTeam && auth()->user()->currentTeam->user_id === auth()->id())
                    <button
                        wire:click="$toggle('showMatrix')"
                        class="px-4 py-2 rounded bg-primary text-white hover:bg-primary-700 transition"
                    >
                        @if($showMatrix)
                            Zurück zur Modulauswahl
                        @else
                            Modul-Matrix anzeigen
                        @endif
                    </button>
                @endif
            </div>
        </x-slot>
    </x-ui-modal>
</div>