{{--
    Extra Fields Form Component

    Renders extra field inputs for inline editing.
    Works with the WithExtraFields Livewire trait.

    Usage:
    <x-core-extra-fields-form :definitions="$this->extraFieldDefinitions" />

    The wire:model binds to extraFieldValues.{id} from the trait.
--}}
@props([
    'definitions' => [],
    'columns' => 2,
])

@if(count($definitions) > 0)
    <div {{ $attributes->merge(['class' => 'grid gap-4 ' . match($columns) {
        1 => 'grid-cols-1',
        3 => 'grid-cols-1 md:grid-cols-3',
        4 => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
        default => 'grid-cols-1 md:grid-cols-2',
    }]) }}>
        @foreach($definitions as $field)
            <div>
                @switch($field['type'])
                    @case('textarea')
                        <x-ui-input-textarea
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_required']"
                            :hint="$field['is_encrypted'] ? 'Verschlüsselt' : null"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            rows="3"
                            placeholder="Wert eingeben..."
                        />
                        @break

                    @case('number')
                        <x-ui-input-number
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_required']"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            step="any"
                            placeholder="Wert eingeben..."
                        />
                        @if($field['is_encrypted'])
                            <span class="text-xs text-[var(--ui-muted)] flex items-center gap-1 mt-1">
                                @svg('heroicon-o-lock-closed', 'w-3 h-3')
                                Verschlüsselt
                            </span>
                        @endif
                        @break

                    @default
                        <x-ui-input-text
                            :name="'extraFieldValues.' . $field['id']"
                            :label="$field['label']"
                            :required="$field['is_required']"
                            :hint="$field['is_encrypted'] ? 'Verschlüsselt' : null"
                            wire:model.live.debounce.500ms="extraFieldValues.{{ $field['id'] }}"
                            placeholder="Wert eingeben..."
                        />
                @endswitch
            </div>
        @endforeach
    </div>
@endif
