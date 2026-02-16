{{--
    Condition Builder Component

    Visual builder for field visibility conditions.
    Used in the Extra Fields modal to configure when fields should be visible.

    Props:
    - $visibility: Array with enabled, logic, groups
    - $availableFields: Array of fields that can be used as conditions
    - $allOperators: All available operators from ExtraFieldConditionEvaluator
    - $description: Human-readable description of current conditions
--}}
@props([
    'visibility' => ['enabled' => false, 'logic' => 'AND', 'groups' => []],
    'availableFields' => [],
    'allOperators' => [],
    'description' => 'Immer sichtbar',
])

<div class="space-y-4">
    {{-- Enable/Disable Toggle --}}
    <div class="flex items-center justify-between p-3 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
        <div class="flex items-center gap-3">
            <label class="relative inline-flex items-center cursor-pointer">
                <input
                    type="checkbox"
                    wire:click="toggleVisibilityEnabled"
                    @checked($visibility['enabled'] ?? false)
                    class="sr-only peer"
                />
                <div class="w-11 h-6 bg-[var(--ui-muted-20)] peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-[var(--ui-primary)] peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:h-5 after:w-5 after:transition-all peer-checked:bg-[var(--ui-primary)]"></div>
            </label>
            <span class="text-sm font-medium text-[var(--ui-secondary)]">
                Bedingte Sichtbarkeit
            </span>
        </div>

        @if($visibility['enabled'] ?? false)
            <span class="text-xs text-[var(--ui-primary)]">
                @svg('heroicon-o-eye', 'w-4 h-4 inline mr-1')
                Aktiv
            </span>
        @endif
    </div>

    @if($visibility['enabled'] ?? false)
        {{-- Live Preview --}}
        <div class="p-3 bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/20">
            <div class="flex items-start gap-2">
                @svg('heroicon-o-eye', 'w-4 h-4 text-[var(--ui-primary)] mt-0.5 flex-shrink-0')
                <div>
                    <span class="text-xs font-medium text-[var(--ui-primary)]">Sichtbar wenn:</span>
                    <p class="text-sm text-[var(--ui-secondary)] mt-1">{{ $description }}</p>
                </div>
            </div>
        </div>

        {{-- Main Logic Selector (between groups) --}}
        @if(count($visibility['groups'] ?? []) > 1)
            <div class="flex items-center gap-2 px-3 py-2 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                <span class="text-xs font-medium text-[var(--ui-muted)]">Gruppen verknüpfen mit:</span>
                <div class="flex gap-1">
                    <button
                        type="button"
                        wire:click="setVisibilityLogic('AND')"
                        class="px-3 py-1 text-xs font-medium transition-colors {{ ($visibility['logic'] ?? 'AND') === 'AND' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-surface)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]' }}"
                    >
                        UND
                    </button>
                    <button
                        type="button"
                        wire:click="setVisibilityLogic('OR')"
                        class="px-3 py-1 text-xs font-medium transition-colors {{ ($visibility['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-surface)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]' }}"
                    >
                        ODER
                    </button>
                </div>
            </div>
        @endif

        {{-- Condition Groups --}}
        <div class="space-y-4">
            @foreach($visibility['groups'] ?? [] as $groupIndex => $group)
                <div class="border border-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
                    {{-- Group Header --}}
                    <div class="flex items-center justify-between px-3 py-2 bg-[var(--ui-muted-5)] border-b border-[var(--ui-border)]/40">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-[var(--ui-secondary)]">
                                Gruppe {{ $groupIndex + 1 }}
                            </span>

                            @if(count($group['conditions'] ?? []) > 1)
                                <div class="flex items-center gap-1">
                                    <span class="text-xs text-[var(--ui-muted)]">Bedingungen:</span>
                                    <button
                                        type="button"
                                        wire:click="setGroupLogic({{ $groupIndex }}, 'AND')"
                                        class="px-2 py-0.5 text-xs transition-colors {{ ($group['logic'] ?? 'AND') === 'AND' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-surface)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]' }}"
                                    >
                                        UND
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="setGroupLogic({{ $groupIndex }}, 'OR')"
                                        class="px-2 py-0.5 text-xs transition-colors {{ ($group['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-secondary)] text-white' : 'bg-[var(--ui-surface)] text-[var(--ui-muted)] border border-[var(--ui-border)]/40 hover:bg-[var(--ui-muted-5)]' }}"
                                    >
                                        ODER
                                    </button>
                                </div>
                            @endif
                        </div>

                        <button
                            type="button"
                            wire:click="removeConditionGroup({{ $groupIndex }})"
                            wire:confirm="Gruppe wirklich löschen?"
                            class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                            title="Gruppe löschen"
                        >
                            @svg('heroicon-o-trash', 'w-4 h-4')
                        </button>
                    </div>

                    {{-- Conditions --}}
                    <div class="p-3 space-y-3">
                        @forelse($group['conditions'] ?? [] as $conditionIndex => $condition)
                            @php
                                $selectedFieldName = $condition['field'] ?? '';
                                $selectedField = collect($availableFields)->firstWhere('name', $selectedFieldName);
                                $selectedFieldType = $selectedField['type'] ?? 'text';
                                $operatorsForType = \Platform\Core\Services\ExtraFieldConditionEvaluator::getOperatorsForType($selectedFieldType);
                                $selectedOperator = $condition['operator'] ?? 'equals';
                                $operatorMeta = $allOperators[$selectedOperator] ?? null;
                                $requiresValue = $operatorMeta['requiresValue'] ?? true;
                            @endphp

                            <div class="flex items-start gap-2 p-2 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/20">
                                {{-- Field Selector --}}
                                <div class="flex-1 min-w-0">
                                    <label class="block text-xs text-[var(--ui-muted)] mb-1">Feld</label>
                                    <select
                                        wire:change="updateConditionField({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                        class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                    >
                                        <option value="">Feld wählen...</option>
                                        @foreach($availableFields as $field)
                                            <option value="{{ $field['name'] }}" @selected($field['name'] === $selectedFieldName)>
                                                {{ $field['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Operator Selector --}}
                                <div class="w-32 flex-shrink-0">
                                    <label class="block text-xs text-[var(--ui-muted)] mb-1">Operator</label>
                                    <select
                                        wire:change="updateConditionOperator({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                        class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                        @disabled(!$selectedFieldName)
                                    >
                                        @foreach($operatorsForType as $opKey => $opMeta)
                                            <option value="{{ $opKey }}" @selected($opKey === $selectedOperator)>
                                                {{ $opMeta['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Value Input (conditionally shown) --}}
                                @if($requiresValue && $selectedFieldName)
                                    <div class="flex-1 min-w-0">
                                        <label class="block text-xs text-[var(--ui-muted)] mb-1">Wert</label>
                                        @if($selectedFieldType === 'boolean')
                                            <select
                                                wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                                class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                            >
                                                <option value="1" @selected(($condition['value'] ?? null) == '1')>Ja</option>
                                                <option value="0" @selected(($condition['value'] ?? null) == '0')>Nein</option>
                                            </select>
                                        @elseif($selectedFieldType === 'select')
                                            @php
                                                $choices = $selectedField['options']['choices'] ?? [];
                                            @endphp
                                            @if(in_array($selectedOperator, ['in', 'not_in']))
                                                {{-- Multi-select for in/not_in operators --}}
                                                <select
                                                    wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, Array.from($event.target.selectedOptions).map(o => o.value))"
                                                    class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                    multiple
                                                    size="3"
                                                >
                                                    @foreach($choices as $choice)
                                                        <option
                                                            value="{{ $choice }}"
                                                            @selected(is_array($condition['value'] ?? null) && in_array($choice, $condition['value']))
                                                        >
                                                            {{ $choice }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <select
                                                    wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                                    class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                >
                                                    <option value="">Wert wählen...</option>
                                                    @foreach($choices as $choice)
                                                        <option value="{{ $choice }}" @selected(($condition['value'] ?? null) === $choice)>
                                                            {{ $choice }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        @elseif($selectedFieldType === 'lookup')
                                            @php
                                                $lookupId = $selectedField['options']['lookup_id'] ?? null;
                                                $lookup = $lookupId ? \Platform\Core\Models\CoreLookup::with('activeValues')->find($lookupId) : null;
                                                $lookupValues = $lookup ? $lookup->activeValues : collect();
                                            @endphp
                                            @if(in_array($selectedOperator, ['in', 'not_in']))
                                                <select
                                                    wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, Array.from($event.target.selectedOptions).map(o => o.value))"
                                                    class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                    multiple
                                                    size="3"
                                                >
                                                    @foreach($lookupValues as $lv)
                                                        <option
                                                            value="{{ $lv->value }}"
                                                            @selected(is_array($condition['value'] ?? null) && in_array($lv->value, $condition['value']))
                                                        >
                                                            {{ $lv->label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <select
                                                    wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                                    class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                >
                                                    <option value="">Wert wählen...</option>
                                                    @foreach($lookupValues as $lv)
                                                        <option value="{{ $lv->value }}" @selected(($condition['value'] ?? null) === $lv->value)>
                                                            {{ $lv->label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        @elseif($selectedFieldType === 'number')
                                            <input
                                                type="number"
                                                wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                                value="{{ $condition['value'] ?? '' }}"
                                                step="any"
                                                class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                placeholder="Zahl eingeben..."
                                            />
                                        @else
                                            <input
                                                type="text"
                                                wire:change="updateConditionValue({{ $groupIndex }}, {{ $conditionIndex }}, $event.target.value)"
                                                value="{{ $condition['value'] ?? '' }}"
                                                class="w-full px-2 py-1.5 text-sm border border-[var(--ui-border)]/40 bg-[var(--ui-surface)] text-[var(--ui-secondary)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]"
                                                placeholder="Wert eingeben..."
                                            />
                                        @endif
                                    </div>
                                @endif

                                {{-- Remove Condition Button --}}
                                <div class="pt-5">
                                    <button
                                        type="button"
                                        wire:click="removeCondition({{ $groupIndex }}, {{ $conditionIndex }})"
                                        class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-danger)] transition-colors"
                                        title="Bedingung löschen"
                                    >
                                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                                    </button>
                                </div>
                            </div>

                            {{-- AND/OR connector between conditions --}}
                            @if(!$loop->last && count($group['conditions']) > 1)
                                <div class="flex items-center justify-center">
                                    <span class="px-2 py-0.5 text-xs font-medium {{ ($group['logic'] ?? 'AND') === 'OR' ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }}">
                                        {{ ($group['logic'] ?? 'AND') === 'OR' ? 'ODER' : 'UND' }}
                                    </span>
                                </div>
                            @endif
                        @empty
                            <p class="text-sm text-[var(--ui-muted)] text-center py-2">
                                Keine Bedingungen definiert.
                            </p>
                        @endforelse

                        {{-- Add Condition Button --}}
                        <button
                            type="button"
                            wire:click="addCondition({{ $groupIndex }})"
                            class="w-full px-3 py-2 text-sm text-[var(--ui-primary)] border border-dashed border-[var(--ui-primary)]/40 hover:bg-[var(--ui-primary-5)] transition-colors flex items-center justify-center gap-2"
                        >
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Bedingung hinzufügen
                        </button>
                    </div>
                </div>

                {{-- AND/OR connector between groups --}}
                @if(!$loop->last && count($visibility['groups']) > 1)
                    <div class="flex items-center justify-center">
                        <span class="px-3 py-1 text-xs font-semibold {{ ($visibility['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-primary-10)] text-[var(--ui-primary)]' : 'bg-[var(--ui-muted-10)] text-[var(--ui-secondary)]' }}">
                            {{ ($visibility['logic'] ?? 'AND') === 'OR' ? 'ODER' : 'UND' }}
                        </span>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Add Group Button --}}
        <button
            type="button"
            wire:click="addConditionGroup"
            class="w-full px-4 py-3 text-sm text-[var(--ui-secondary)] border border-dashed border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)] transition-colors flex items-center justify-center gap-2"
        >
            @svg('heroicon-o-plus-circle', 'w-5 h-5')
            Neue Bedingungsgruppe hinzufügen
        </button>
        {{-- Circular Dependency Error --}}
        @error('editField.visibility')
            <div class="p-3 bg-[var(--ui-danger-5)] border border-[var(--ui-danger)]/20">
                <div class="flex items-start gap-2">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-danger)] mt-0.5 flex-shrink-0')
                    <p class="text-sm text-[var(--ui-danger)]">{{ $message }}</p>
                </div>
            </div>
        @enderror
    @else
        {{-- Disabled State Info --}}
        <div class="p-4 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-center">
            <p class="text-sm text-[var(--ui-muted)]">
                Aktivieren Sie die bedingte Sichtbarkeit, um festzulegen, wann dieses Feld angezeigt werden soll.
            </p>
        </div>
    @endif
</div>
