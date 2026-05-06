                    <div class="space-y-7">
                        @foreach($extraFieldDefinitions as $field)
                            @php
                                $fieldId = $field['id'];
                                // Wenn Akkordeon-Layout aktiv: filtern wir pro
                                // Field ob's in den aktuellen Render-Block gehoert.
                                // Im klassischen Layout (Default) wird alles gerendert.
                                $skipThisField = false;
                                if (($useAccordionLayout ?? false) && isset($renderBlock)) {
                                    if ($renderBlock === 'open' && !in_array($fieldId, $openFieldIds, true)) {
                                        $skipThisField = true;
                                    } elseif ($renderBlock === 'accordion' && !in_array($fieldId, $accordionFieldIds, true)) {
                                        $skipThisField = true;
                                    }
                                }
                            @endphp
                            @if($skipThisField)
                                @continue
                            @endif
                            @php
                                $fieldType = $field['type'];
                                $fieldLabel = $field['label'];
                                // Pflicht-Marker: nutzt zentralen Helper damit
                                // options.required_in_phase_orders mit-greift
                                $isRequired = $this->isFieldEffectivelyRequired($field, $currentPhaseOrder);
                                $options = $field['options'] ?? [];
                            @endphp

                            <div
                                x-show="isFieldVisible({{ $fieldId }})"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 -translate-y-2"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 translate-y-0"
                                x-transition:leave-end="opacity-0 -translate-y-2"
                            >
                                {{-- Label --}}
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <label class="block text-sm font-semibold text-gray-800">
                                        {{ $fieldLabel }}
                                        @if($isRequired)
                                            <span class="text-red-400 ml-0.5">*</span>
                                        @endif
                                    </label>
                                    @php
                                        $badges = [];
                                        if ($isRequired) $badges[] = ['label' => 'Pflichtfeld', 'color' => 'red'];
                                        if (($options['multiple'] ?? false) && in_array($fieldType, ['select', 'lookup', 'file'])) $badges[] = ['label' => 'Mehrfachauswahl', 'color' => 'indigo'];
                                        if ($field['is_encrypted'] ?? false) $badges[] = ['label' => 'Verschlüsselt', 'color' => 'gray'];
                                    @endphp
                                    @if(!empty($badges))
                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                            @foreach($badges as $badge)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold tracking-wide uppercase
                                                    {{ $badge['color'] === 'red' ? 'bg-red-50 text-red-500' : '' }}
                                                    {{ $badge['color'] === 'indigo' ? 'bg-indigo-50 text-indigo-500' : '' }}
                                                    {{ $badge['color'] === 'gray' ? 'bg-gray-100 text-gray-500' : '' }}
                                                ">{{ $badge['label'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                {{-- Description --}}
                                @php
                                    $fieldDescription = $field['description'] ?? $options['description'] ?? null;
                                @endphp
                                @if(!empty($fieldDescription))
                                    <p class="text-[13px] text-gray-400 mb-2.5 leading-relaxed">{{ $fieldDescription }}</p>
                                @endif

                                {{-- Regex format hint --}}
                                @if(($fieldType === 'regex' && ($options['pattern_description'] ?? null)) || ($fieldType === 'text' && ($options['regex'] ?? null) && ($options['regex_message'] ?? null)))
                                    <div class="flex items-center gap-1.5 mb-2.5">
                                        <svg class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="text-xs text-amber-600 font-medium">{{ $options['pattern_description'] ?? $options['regex_message'] ?? '' }}</span>
                                    </div>
                                @endif

                                {{-- Phone hint --}}
                                @if($fieldType === 'phone')
                                    <p class="text-xs text-gray-400 mb-2.5">Ländervorwahl wählen und Nummer eingeben</p>
                                @endif

                                @switch($fieldType)
                                    {{-- ─── Text ─── --}}
                                    @case('text')
                                        @php
                                            $textRegex = $options['regex'] ?? null;
                                            $textRegexMsg = $options['regex_message'] ?? 'Eingabe entspricht nicht dem erwarteten Format.';
                                        @endphp
                                        @if($textRegex)
                                            <div
                                                x-data="{
                                                    value: @entangle('extraFieldValues.' . $fieldId).live,
                                                    pattern: {{ json_encode($textRegex) }},
                                                    touched: false,
                                                    get isValid() {
                                                        if (!this.touched || !this.value || !this.pattern) return true;
                                                        try { return new RegExp(this.pattern).test(this.value); } catch(e) { return true; }
                                                    }
                                                }"
                                            >
                                                <input
                                                    type="text"
                                                    x-model="value"
                                                    x-on:input="touched = true"
                                                    wire:model.live.debounce.500ms="extraFieldValues.{{ $fieldId }}"
                                                    placeholder="{{ $options['placeholder'] ?? 'Eingabe...' }}"
                                                    :class="!isValid
                                                        ? 'w-full px-4 py-3 bg-red-50 border-2 border-red-300 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:ring-2 focus:ring-red-100'
                                                        : 'w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100'"
                                                >
                                                <template x-if="!isValid">
                                                    <p class="mt-1.5 text-sm text-red-500 flex items-center gap-1.5">
                                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        {{ $textRegexMsg }}
                                                    </p>
                                                </template>
                                            </div>
                                        @else
                                            <input
                                                type="text"
                                                wire:model="extraFieldValues.{{ $fieldId }}"
                                                placeholder="{{ $options['placeholder'] ?? 'Eingabe...' }}"
                                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                            >
                                        @endif
                                        @break

                                    {{-- ─── Textarea ─── --}}
                                    @case('textarea')
                                        <textarea
                                            wire:model="extraFieldValues.{{ $fieldId }}"
                                            rows="{{ $options['rows'] ?? 4 }}"
                                            placeholder="{{ $options['placeholder'] ?? 'Eingabe...' }}"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 resize-y"
                                        ></textarea>
                                        @break

                                    {{-- ─── Number ─── --}}
                                    @case('number')
                                        <input
                                            type="number"
                                            wire:model="extraFieldValues.{{ $fieldId }}"
                                            placeholder="{{ $options['placeholder'] ?? '0' }}"
                                            @if(isset($options['min'])) min="{{ $options['min'] }}" @endif
                                            @if(isset($options['max'])) max="{{ $options['max'] }}" @endif
                                            @if(isset($options['step'])) step="{{ $options['step'] }}" @endif
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                        >
                                        @break

                                    {{-- ─── Date (3 Selects) ─── --}}
                                    @case('date')
                                        @php
                                            $currentYear = (int) date('Y');
                                            $yearRange = $options['year_range'] ?? \Platform\Core\Models\CoreExtraFieldDefinition::DATE_YEAR_RANGE_DEFAULT;
                                            $months = [
                                                1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
                                                5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
                                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
                                            ];
                                        @endphp
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="relative">
                                                <select
                                                    wire:model.live="extraFieldValues.{{ $fieldId }}.day"
                                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 outline-none appearance-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-8"
                                                >
                                                    <option value="">Tag</option>
                                                    @for($d = 1; $d <= 31; $d++)
                                                        <option value="{{ $d }}">{{ $d }}</option>
                                                    @endfor
                                                </select>
                                                <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                            <div class="relative">
                                                <select
                                                    wire:model.live="extraFieldValues.{{ $fieldId }}.month"
                                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 outline-none appearance-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-8"
                                                >
                                                    <option value="">Monat</option>
                                                    @foreach($months as $num => $name)
                                                        <option value="{{ $num }}">{{ $name }}</option>
                                                    @endforeach
                                                </select>
                                                <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                            <div class="relative">
                                                <select
                                                    wire:model.live="extraFieldValues.{{ $fieldId }}.year"
                                                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 outline-none appearance-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-8"
                                                >
                                                    <option value="">Jahr</option>
                                                    @for($y = $currentYear; $y >= $currentYear - $yearRange; $y--)
                                                        <option value="{{ $y }}">{{ $y }}</option>
                                                    @endfor
                                                </select>
                                                <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                        </div>
                                        @error("extraFieldValues.{$fieldId}.day") <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p> @enderror
                                        @error("extraFieldValues.{$fieldId}.month") <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p> @enderror
                                        @error("extraFieldValues.{$fieldId}.year") <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p> @enderror
                                        @break

                                    {{-- ─── Boolean (Badge Style) ─── --}}
                                    @case('boolean')
                                        <div class="flex gap-2">
                                            <button
                                                type="button"
                                                wire:click="$set('extraFieldValues.{{ $fieldId }}', '1')"
                                                class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200
                                                    {{ ($extraFieldValues[$fieldId] ?? null) === '1'
                                                        ? 'bg-emerald-500 text-white border-2 border-emerald-500 shadow-lg shadow-emerald-500/20 scale-[1.02]'
                                                        : 'bg-gray-50 text-gray-500 border border-gray-200 hover:border-gray-300 hover:bg-gray-100' }}"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                Ja
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="$set('extraFieldValues.{{ $fieldId }}', '0')"
                                                class="flex-1 flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200
                                                    {{ ($extraFieldValues[$fieldId] ?? null) === '0'
                                                        ? 'bg-red-500 text-white border-2 border-red-500 shadow-lg shadow-red-500/20 scale-[1.02]'
                                                        : 'bg-gray-50 text-gray-500 border border-gray-200 hover:border-gray-300 hover:bg-gray-100' }}"
                                            >
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                Nein
                                            </button>
                                        </div>
                                        @break

                                    {{-- ─── Select ─── --}}
                                    @case('select')
                                        @php
                                            $isMultiple = $options['multiple'] ?? false;
                                            $choices = $options['choices'] ?? [];
                                            $useBadges = count($choices) <= 6;
                                        @endphp
                                        @if($useBadges)
                                            {{-- Badge mode --}}
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($choices as $choice)
                                                    @php
                                                        $currentVal = $extraFieldValues[$fieldId] ?? ($isMultiple ? [] : null);
                                                        $isSelected = $isMultiple
                                                            ? (is_array($currentVal) && in_array($choice, $currentVal))
                                                            : ($currentVal === $choice);
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        @if($isMultiple)
                                                            x-on:click="toggleMulti({{ $fieldId }}, {{ json_encode($choice) }})"
                                                        @else
                                                            wire:click="$set('extraFieldValues.{{ $fieldId }}', {{ json_encode($choice) }})"
                                                        @endif
                                                        class="px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
                                                            {{ $isSelected
                                                                ? 'bg-indigo-500 text-white border-2 border-indigo-500 shadow-lg shadow-indigo-500/20 scale-[1.02]'
                                                                : 'bg-gray-50 text-gray-600 border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600' }}"
                                                    >
                                                        @if($isMultiple && $isSelected)
                                                            <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                        @endif
                                                        {{ $choice }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            {{-- Dropdown mode --}}
                                            @if($isMultiple)
                                                <div class="space-y-1.5">
                                                    @foreach($choices as $choice)
                                                        @php
                                                            $currentVal = $extraFieldValues[$fieldId] ?? [];
                                                            $isSelected = is_array($currentVal) && in_array($choice, $currentVal);
                                                        @endphp
                                                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all duration-200
                                                            {{ $isSelected
                                                                ? 'bg-indigo-50 border-indigo-200'
                                                                : 'bg-gray-50 border-gray-200 hover:bg-gray-100 hover:border-gray-300' }}">
                                                            <input
                                                                type="checkbox"
                                                                value="{{ $choice }}"
                                                                @checked($isSelected)
                                                                x-on:change="toggleMulti({{ $fieldId }}, {{ json_encode($choice) }})"
                                                                class="w-4 h-4 rounded border-gray-300 text-indigo-500 focus:ring-indigo-400"
                                                            >
                                                            <span class="text-sm {{ $isSelected ? 'text-indigo-700 font-medium' : 'text-gray-600' }}">{{ $choice }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="relative">
                                                    <select
                                                        wire:model="extraFieldValues.{{ $fieldId }}"
                                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 outline-none appearance-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-10"
                                                    >
                                                        <option value="">Bitte wählen...</option>
                                                        @foreach($choices as $choice)
                                                            <option value="{{ $choice }}">{{ $choice }}</option>
                                                        @endforeach
                                                    </select>
                                                    <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        @endif
                                        @break

                                    {{-- ─── Lookup ─── --}}
                                    @case('lookup')
                                        @php
                                            $isMultiple = $options['multiple'] ?? false;
                                            $lookupChoices = $field['lookup']['choices'] ?? [];
                                            $useBadges = count($lookupChoices) <= 6;
                                        @endphp
                                        @if($useBadges)
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($lookupChoices as $choice)
                                                    @php
                                                        $currentVal = $extraFieldValues[$fieldId] ?? ($isMultiple ? [] : null);
                                                        $isSelected = $isMultiple
                                                            ? (is_array($currentVal) && in_array($choice['value'], $currentVal))
                                                            : ($currentVal === $choice['value']);
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        @if($isMultiple)
                                                            x-on:click="toggleMulti({{ $fieldId }}, {{ json_encode($choice['value']) }})"
                                                        @else
                                                            wire:click="$set('extraFieldValues.{{ $fieldId }}', {{ json_encode($choice['value']) }})"
                                                        @endif
                                                        class="px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200
                                                            {{ $isSelected
                                                                ? 'bg-indigo-500 text-white border-2 border-indigo-500 shadow-lg shadow-indigo-500/20 scale-[1.02]'
                                                                : 'bg-gray-50 text-gray-600 border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-600' }}"
                                                    >
                                                        @if($isMultiple && $isSelected)
                                                            <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                        @endif
                                                        {{ $choice['label'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            @if($isMultiple)
                                                <div class="space-y-1.5">
                                                    @foreach($lookupChoices as $choice)
                                                        @php
                                                            $currentVal = $extraFieldValues[$fieldId] ?? [];
                                                            $isSelected = is_array($currentVal) && in_array($choice['value'], $currentVal);
                                                        @endphp
                                                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all duration-200
                                                            {{ $isSelected
                                                                ? 'bg-indigo-50 border-indigo-200'
                                                                : 'bg-gray-50 border-gray-200 hover:bg-gray-100 hover:border-gray-300' }}">
                                                            <input
                                                                type="checkbox"
                                                                value="{{ $choice['value'] }}"
                                                                @checked($isSelected)
                                                                x-on:change="toggleMulti({{ $fieldId }}, {{ json_encode($choice['value']) }})"
                                                                class="w-4 h-4 rounded border-gray-300 text-indigo-500 focus:ring-indigo-400"
                                                            >
                                                            <span class="text-sm {{ $isSelected ? 'text-indigo-700 font-medium' : 'text-gray-600' }}">{{ $choice['label'] }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="relative">
                                                    <select
                                                        wire:model="extraFieldValues.{{ $fieldId }}"
                                                        class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 outline-none appearance-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-10"
                                                    >
                                                        <option value="">Bitte wählen...</option>
                                                        @foreach($lookupChoices as $choice)
                                                            <option value="{{ $choice['value'] }}">{{ $choice['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        @endif
                                        @break

                                    {{-- ─── Address ─── --}}
                                    @case('address')
                                        @php $countries = \Platform\Core\Models\CoreExtraFieldDefinition::PHONE_COUNTRIES; @endphp
                                        <div class="bg-gray-50 rounded-2xl border border-gray-200 p-4 space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Straße + Hausnummer @if($isRequired)<span class="text-red-400">*</span>@endif</label>
                                                <input type="text" wire:model="extraFieldValues.{{ $fieldId }}.street" placeholder="Musterstraße 1"
                                                       class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                                                @error("extraFieldValues.{$fieldId}.street") <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Adresszusatz</label>
                                                <input type="text" wire:model="extraFieldValues.{{ $fieldId }}.street2" placeholder="z.B. Hinterhaus, 3. OG"
                                                       class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                                            </div>
                                            <div class="grid grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">PLZ @if($isRequired)<span class="text-red-400">*</span>@endif</label>
                                                    <input type="text" wire:model="extraFieldValues.{{ $fieldId }}.zip" placeholder="12345"
                                                           class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                                                    @error("extraFieldValues.{$fieldId}.zip") <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                                </div>
                                                <div class="col-span-2">
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Ort @if($isRequired)<span class="text-red-400">*</span>@endif</label>
                                                    <input type="text" wire:model="extraFieldValues.{{ $fieldId }}.city" placeholder="Berlin"
                                                           class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                                                    @error("extraFieldValues.{$fieldId}.city") <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Bundesland</label>
                                                    <input type="text" wire:model="extraFieldValues.{{ $fieldId }}.state" placeholder="z.B. Bayern"
                                                           class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 outline-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Land @if($isRequired)<span class="text-red-400">*</span>@endif</label>
                                                    <div class="relative">
                                                        <select wire:model="extraFieldValues.{{ $fieldId }}.country"
                                                                class="w-full px-3.5 py-2.5 bg-white border border-gray-200 rounded-lg text-sm text-gray-900 outline-none appearance-none transition-all focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-8">
                                                            @foreach($countries as $code => $info)
                                                                <option value="{{ $code }}">{{ $info['flag'] }} {{ $info['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    </div>
                                                    @error("extraFieldValues.{$fieldId}.country") <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                                </div>
                                            </div>
                                        </div>
                                        @break

                                    {{-- ─── Regex ─── --}}
                                    @case('regex')
                                        @php
                                            $patternError = $options['pattern_error'] ?? null;
                                            $pattern = $options['pattern'] ?? null;
                                        @endphp
                                        <div
                                            x-data="{
                                                value: @entangle('extraFieldValues.' . $fieldId).live,
                                                pattern: {{ json_encode($pattern) }},
                                                touched: false,
                                                get isValid() {
                                                    if (!this.touched || !this.value || !this.pattern) return true;
                                                    try { return new RegExp(this.pattern).test(this.value); } catch(e) { return true; }
                                                }
                                            }"
                                        >
                                            <input
                                                type="text"
                                                x-model="value"
                                                x-on:input="touched = true"
                                                wire:model.live.debounce.500ms="extraFieldValues.{{ $fieldId }}"
                                                placeholder="{{ $options['pattern_description'] ?? ($options['placeholder'] ?? 'Eingabe...') }}"
                                                :class="!isValid
                                                    ? 'w-full px-4 py-3 bg-red-50 border-2 border-red-300 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:ring-2 focus:ring-red-100'
                                                    : 'w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100'"
                                            >
                                            <template x-if="!isValid">
                                                <p class="mt-1.5 text-sm text-red-500 flex items-center gap-1.5">
                                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    {{ $patternError ?? 'Eingabe entspricht nicht dem erwarteten Format.' }}
                                                </p>
                                            </template>
                                        </div>
                                        @break

                                    {{-- ─── Phone ─── --}}
                                    @case('phone')
                                        @php $phoneCountries = \Platform\Core\Models\CoreExtraFieldDefinition::PHONE_COUNTRIES; @endphp
                                        <div class="flex gap-2">
                                            <div class="relative flex-shrink-0 w-[130px]">
                                                <select
                                                    wire:model="extraFieldValues.{{ $fieldId }}.country"
                                                    class="w-full px-3 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 outline-none appearance-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 pr-7"
                                                >
                                                    @foreach($phoneCountries as $code => $info)
                                                        <option value="{{ $code }}">{{ $info['flag'] }} {{ $info['dial'] }}</option>
                                                    @endforeach
                                                </select>
                                                <svg class="w-4 h-4 text-gray-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </div>
                                            <input
                                                type="tel"
                                                wire:model="extraFieldValues.{{ $fieldId }}.raw"
                                                placeholder="z.B. 0151 1234567"
                                                class="flex-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                            >
                                        </div>
                                        @break

                                    {{-- ─── File ─── --}}
                                    @case('file')
                                        @php
                                            $isMultiple = $options['multiple'] ?? false;
                                            $currentFileIds = $extraFieldValues[$fieldId] ?? ($isMultiple ? [] : null);
                                            $currentFileIds = is_array($currentFileIds) ? $currentFileIds : ($currentFileIds ? [$currentFileIds] : []);
                                        @endphp
                                        <div>
                                            @if(!empty($currentFileIds))
                                                <div class="space-y-2 mb-3">
                                                    @foreach($currentFileIds as $fileId_item)
                                                        @php $fileData = $uploadedFileData[$fileId_item] ?? null; @endphp
                                                        @if($fileData)
                                                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
                                                                @if($fileData['is_image'] && $fileData['thumbnail_url'])
                                                                    <img src="{{ $fileData['thumbnail_url'] }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                                                                @else
                                                                    <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                                                                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                                                        </svg>
                                                                    </div>
                                                                @endif
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm font-medium text-gray-700 truncate">{{ $fileData['original_name'] }}</p>
                                                                    <p class="text-xs text-gray-400">{{ $this->formatFileSize($fileData['file_size'] ?? 0) }}</p>
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    wire:click="removeFile({{ $fieldId }}, {{ $fileId_item }})"
                                                                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors flex-shrink-0"
                                                                >
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if($isMultiple || empty($currentFileIds))
                                                <div
                                                    x-data="{ dragging: false }"
                                                    x-on:dragover.prevent="dragging = true"
                                                    x-on:dragleave.prevent="dragging = false"
                                                    x-on:drop.prevent="dragging = false; $refs.fileInput{{ $fieldId }}.files = $event.dataTransfer.files; $refs.fileInput{{ $fieldId }}.dispatchEvent(new Event('change'))"
                                                >
                                                    <label
                                                        :class="dragging ? 'border-indigo-400 bg-indigo-50/50' : 'border-gray-300 hover:border-indigo-300 hover:bg-indigo-50/30'"
                                                        class="flex flex-col items-center justify-center p-8 border-2 border-dashed rounded-2xl cursor-pointer transition-all duration-200"
                                                    >
                                                        <div wire:loading.remove wire:target="pendingFileUploads.{{ $fieldId }}">
                                                            <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                            </svg>
                                                            <p class="text-sm text-gray-500 text-center">
                                                                <span class="font-semibold text-indigo-500">Datei auswählen</span> oder hierher ziehen
                                                            </p>
                                                        </div>
                                                        <div wire:loading wire:target="pendingFileUploads.{{ $fieldId }}" class="flex flex-col items-center">
                                                            <svg class="animate-spin w-6 h-6 text-indigo-500 mb-2" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                            </svg>
                                                            <p class="text-sm text-gray-500">Wird hochgeladen...</p>
                                                        </div>
                                                        <input
                                                            type="file"
                                                            x-ref="fileInput{{ $fieldId }}"
                                                            wire:model="pendingFileUploads.{{ $fieldId }}"
                                                            class="hidden"
                                                            @if($isMultiple) multiple @endif
                                                        >
                                                    </label>
                                                </div>
                                            @endif
                                        </div>
                                        @break

                                    {{-- ─── Email ─── --}}
                                    @case('email')
                                        <input
                                            type="email"
                                            wire:model="extraFieldValues.{{ $fieldId }}"
                                            placeholder="{{ $options['placeholder'] ?? 'E-Mail-Adresse eingeben...' }}"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                        >
                                        @break

                                    {{-- ─── Fallback ─── --}}
                                    @default
                                        <input
                                            type="text"
                                            wire:model="extraFieldValues.{{ $fieldId }}"
                                            placeholder="{{ $options['placeholder'] ?? 'Eingabe...' }}"
                                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-[15px] text-gray-900 placeholder:text-gray-400 outline-none transition-all duration-200 focus:bg-white focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                        >
                                @endswitch

                                {{-- Error messages --}}
                                @error("extraFieldValues.{$fieldId}")
                                    <p class="mt-1.5 text-sm text-red-500 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                                @error("extraFieldValues.{$fieldId}.raw")
                                    <p class="mt-1.5 text-sm text-red-500 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $message }}
                                    </p>
                                @enderror
                                @error("extraFieldValues.{$fieldId}.street")
                                    {{-- Handled inline for address --}}
                                @enderror
                            </div>
                        @endforeach
                    </div>
