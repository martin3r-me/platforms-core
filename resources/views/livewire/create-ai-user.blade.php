<div>
    @if (!$showForm)
        <div class="space-y-4">
            <p class="text-sm text-[var(--ui-muted)]">
                Erstelle einen neuen AI-User für dieses Team. AI-User können nur diesem Team und dessen Unter-Teams zugewiesen werden.
            </p>
            <x-ui-button wire:click="$set('showForm', true)" variant="primary">
                Neuen AI-User erstellen
            </x-ui-button>
        </div>
    @else
        <form wire:submit.prevent="createAiUser" class="space-y-4">
            <!-- Name -->
            <x-ui-input-text
                name="form.name"
                label="Name"
                wire:model.live="form.name"
                placeholder="Name des AI-Users"
                required
                :errorKey="'form.name'"
            />

            <!-- AI Model -->
            @if ($aiModels->isNotEmpty())
                <x-ui-input-select
                    name="form.core_ai_model_id"
                    label="AI-Model (optional)"
                    :options="$aiModels"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    wire:model.live="form.core_ai_model_id"
                    :errorKey="'form.core_ai_model_id'"
                />
            @endif

            <!-- Instruction -->
            <div>
                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                    Anweisung / Beschreibung (optional)
                </label>
                <textarea
                    name="form.instruction"
                    class="w-full px-3 py-2 border border-[var(--ui-border)] rounded-lg focus:ring-2 focus:ring-[var(--ui-primary)] focus:border-[var(--ui-primary)] bg-[var(--ui-surface)] text-[var(--ui-secondary)]"
                    rows="4"
                    wire:model.live="form.instruction"
                    placeholder="Beschreibe, wer dieser AI-User ist und welche Rolle er hat..."
                ></textarea>
                @error('form.instruction')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2">
                <x-ui-button type="submit" variant="primary" wire:loading.attr="disabled">
                    AI-User erstellen
                </x-ui-button>
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('showForm', false)" wire:loading.attr="disabled">
                    Abbrechen
                </x-ui-button>
            </div>
        </form>
    @endif

    @if (session()->has('message'))
        <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            {{ session('message') }}
        </div>
    @endif
</div>
