<div x-data="{ tab: 'team' }" x-init="
    window.addEventListener('open-modal-team', (e) => { tab = e?.detail?.tab || 'team'; });
">
<x-ui-modal size="xl" wire:model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0">Team verwalten</h2>
                <span class="text-xs text-[var(--ui-muted)] bg-[var(--ui-muted-5)] px-2 py-1 rounded-full">TEAM</span>
            </div>
        </div>
        <div class="flex gap-1 mt-4 border-b border-gray-200">
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'team', 'text-gray-500 hover:text-gray-700' : tab !== 'team' }" @click="tab = 'team'">Team</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'ai-user', 'text-gray-500 hover:text-gray-700' : tab !== 'ai-user' }" @click="tab = 'ai-user'">AI User</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'create', 'text-gray-500 hover:text-gray-700' : tab !== 'create' }" @click="tab = 'create'">Anlegen</button>
            <button type="button" class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors" :class="{ 'text-blue-600 border-b-2 border-blue-600 bg-blue-50' : tab === 'billing', 'text-gray-500 hover:text-gray-700' : tab !== 'billing' }" @click="tab = 'billing'">Abrechnung</button>
        </div>
    </x-slot>

    {{-- Team Tab --}}
    <div class="mt-6" x-show="tab === 'team'" x-cloak>
        <div class="space-y-6">
            <p>Team Tab - Inhalt entfernt zum Debuggen</p>
        </div>
    </div>

    {{-- AI User Tab --}}
    <div class="mt-6" x-show="tab === 'ai-user'" x-cloak>
        <p>AI User Tab - Inhalt entfernt zum Debuggen</p>
    </div>

    {{-- Create Tab --}}
    <div class="mt-6" x-show="tab === 'create'" x-cloak>
        <p>Create Tab - Inhalt entfernt zum Debuggen</p>
    </div>

    {{-- Billing Tab --}}
    <div class="mt-6" x-show="tab === 'billing'" x-cloak>
        <p>Billing Tab - Inhalt entfernt zum Debuggen</p>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">
                Schließen
            </x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>
