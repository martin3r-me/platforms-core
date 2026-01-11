<div>
    @if (Gate::check('addTeamMember', $team))
        <x-section-border />

        <!-- Create AI User -->
        <div class="mt-10 sm:mt-0">
            <x-action-section>
                <x-slot name="title">
                    {{ __('AI-User erstellen') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('Erstelle einen neuen AI-User für dieses Team. AI-User können nur diesem Team und dessen Unter-Teams zugewiesen werden.') }}
                </x-slot>

                @if (!$showForm)
                    <x-slot name="content">
                        <x-button wire:click="$set('showForm', true)">
                            {{ __('Neuen AI-User erstellen') }}
                        </x-button>
                    </x-slot>
                @else
                    <x-slot name="content">
                        <x-form-section submit="createAiUser">
                            <x-slot name="title">
                                {{ __('AI-User Details') }}
                            </x-slot>

                            <x-slot name="description">
                                {{ __('Gib die Details für den neuen AI-User ein.') }}
                            </x-slot>

                            <x-slot name="form">
                                <!-- Name -->
                                <div class="col-span-6 sm:col-span-4">
                                    <x-label for="name" value="{{ __('Name') }}" />
                                    <x-input id="name" type="text" class="mt-1 block w-full" wire:model="form.name" />
                                    <x-input-error for="form.name" class="mt-2" />
                                </div>

                                <!-- AI Model -->
                                @if ($aiModels->isNotEmpty())
                                    <div class="col-span-6 sm:col-span-4">
                                        <x-label for="core_ai_model_id" value="{{ __('AI-Model') }}" />
                                        <select id="core_ai_model_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" wire:model="form.core_ai_model_id">
                                            <option value="">{{ __('Kein Model auswählen') }}</option>
                                            @foreach ($aiModels as $model)
                                                <option value="{{ $model->id }}">{{ $model->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error for="form.core_ai_model_id" class="mt-2" />
                                    </div>
                                @endif

                                <!-- Instruction -->
                                <div class="col-span-6 sm:col-span-4">
                                    <x-label for="instruction" value="{{ __('Anweisung / Beschreibung') }}" />
                                    <textarea id="instruction" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" rows="4" wire:model="form.instruction" placeholder="{{ __('Beschreibe, wer dieser AI-User ist und welche Rolle er hat...') }}"></textarea>
                                    <x-input-error for="form.instruction" class="mt-2" />
                                </div>
                            </x-slot>

                            <x-slot name="actions">
                                <x-secondary-button wire:click="$set('showForm', false)" wire:loading.attr="disabled">
                                    {{ __('Abbrechen') }}
                                </x-secondary-button>

                                <x-button class="ms-3" wire:loading.attr="disabled">
                                    {{ __('AI-User erstellen') }}
                                </x-button>
                            </x-slot>
                        </x-form-section>
                    </x-slot>
                @endif
            </x-action-section>
        </div>

        @if (session()->has('message'))
            <x-action-message class="mt-4" on="ai-user-created">
                {{ session('message') }}
            </x-action-message>
        @endif
    @endif
</div>
