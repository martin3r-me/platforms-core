<div class="min-h-screen relative overflow-hidden font-[system-ui,-apple-system,'Segoe_UI',Roboto,sans-serif]">

    {{-- Background --}}
    @php
        $bgFiles = glob(public_path('images/bg-images/*.{jpeg,jpg,png,webp}'), GLOB_BRACE);
        $bgImage = !empty($bgFiles) ? basename($bgFiles[array_rand($bgFiles)]) : null;
    @endphp
    <div class="fixed inset-0 -z-10" aria-hidden="true">
        <div class="fixed inset-0 bg-gradient-to-br from-[#1a1a2e] via-[#16213e] to-[#0f3460]"></div>
        @if($bgImage)
            <img src="{{ asset('images/bg-images/' . $bgImage) }}"
                 class="absolute inset-0 w-full h-full object-cover"
                 alt="" loading="eager">
        @endif
        <div class="absolute inset-0 bg-gradient-to-br from-black/50 via-black/30 to-black/50"></div>
        <div class="absolute inset-0 backdrop-blur-[6px]"></div>
    </div>

    {{-- Loading --}}
    @if($state === 'loading')
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl w-full max-w-md p-10 text-center">
                <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mx-auto mb-6">
                    <svg class="animate-spin w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <p class="text-gray-500 text-lg">Wird geladen...</p>
            </div>
        </div>

    {{-- Not Found --}}
    @elseif($state === 'notFound')
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl w-full max-w-md p-10 text-center">
                <div class="w-20 h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">Link ungültig</h1>
                <p class="text-gray-500 text-lg">Dieser Link ist ungültig oder existiert nicht mehr.</p>
            </div>
        </div>

    {{-- Not Active --}}
    @elseif($state === 'notActive')
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl w-full max-w-md p-10 text-center">
                <div class="w-20 h-20 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">Formular nicht aktiv</h1>
                <p class="text-gray-500 text-lg">Dieses Formular ist derzeit nicht aktiv.</p>
            </div>
        </div>

    {{-- Form --}}
    @elseif($state === 'form')
        {{-- Header --}}
        <header class="sticky top-0 z-50">
            <div class="bg-black/60 backdrop-blur-[30px] border-b border-white/[0.06]">
                <div class="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h1 class="text-base font-semibold text-white truncate">Formular</h1>
                    </div>
                    <div class="flex items-center gap-4 flex-shrink-0 ml-4">
                        @if($totalFields > 0)
                            <span class="text-sm font-medium text-white/50">
                                {{ $filledFields }}<span class="text-white/30">/</span>{{ $totalFields }} ausgefüllt
                            </span>
                        @endif
                    </div>
                </div>
                @if($totalFields > 0)
                    <div class="h-0.5 bg-white/5">
                        <div class="h-full transition-all duration-700 ease-out bg-gradient-to-r from-blue-500 via-indigo-500 to-violet-500 shadow-[0_0_12px_rgba(99,102,241,0.5)]"
                             style="width: {{ round(($filledFields / $totalFields) * 100) }}%"></div>
                    </div>
                @endif
            </div>
        </header>

        @php
            $lookupCache = [];
            $allDefsForJs = $allFieldDefinitions;
            foreach ($allDefsForJs as &$def) {
                $vc = $def['visibility_config'] ?? null;
                if (!$vc || !($vc['enabled'] ?? false)) continue;
                foreach ($vc['groups'] ?? [] as $gi => $group) {
                    foreach ($group['conditions'] ?? [] as $ci => $condition) {
                        if (in_array($condition['operator'] ?? '', ['is_in', 'is_not_in'])) {
                            $source = $condition['list_source'] ?? 'manual';
                            if ($source === 'lookup') {
                                $lookupId = $condition['list_lookup_id'] ?? null;
                                if ($lookupId) {
                                    if (!isset($lookupCache[$lookupId])) {
                                        $lookup = \Platform\Core\Models\CoreLookup::with('activeValues')->find($lookupId);
                                        $lookupCache[$lookupId] = $lookup ? $lookup->activeValues->pluck('value')->all() : [];
                                    }
                                    $def['visibility_config']['groups'][$gi]['conditions'][$ci]['_resolved_list'] = $lookupCache[$lookupId];
                                }
                            }
                        }
                    }
                }
            }
            unset($def);
            $formFieldIds = collect($extraFieldDefinitions)->pluck('id')->all();
            $defsForJs = array_values(array_filter($allDefsForJs, fn($d) => in_array($d['id'], $formFieldIds)));
        @endphp

        <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
            <form wire:submit="save"
                x-on:scroll-to-field.window="$nextTick(() => { const el = document.getElementById('ef-field-' + $event.detail.fieldId); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); const inp = el.querySelector('input, select, textarea'); if (inp) inp.focus({ preventScroll: true }); } })"
                x-data="{
                    fieldValues: @entangle('extraFieldValues').live,
                    allFieldValues: @entangle('allFieldValues').live,
                    fieldDefinitions: @js($defsForJs),
                    allFieldDefinitions: @js($allDefsForJs),

                    init() {
                        this.$watch('fieldValues', (values) => {
                            for (const [key, val] of Object.entries(values)) {
                                this.allFieldValues[key] = val;
                            }
                        });
                    },

                    isFieldVisible(fieldId) {
                        const field = this.allFieldDefinitions.find(f => f.id === fieldId);
                        if (!field) return true;
                        const visibility = field.visibility_config;
                        if (!visibility || !visibility.enabled) return true;
                        return this.evaluateVisibility(visibility);
                    },

                    evaluateVisibility(config) {
                        if (!config.groups || config.groups.length === 0) return true;
                        const mainLogic = (config.logic || 'AND').toUpperCase();
                        const groupResults = config.groups.map(group => this.evaluateGroup(group));
                        if (mainLogic === 'OR') return groupResults.includes(true);
                        return !groupResults.includes(false);
                    },

                    evaluateGroup(group) {
                        if (!group.conditions || group.conditions.length === 0) return true;
                        const groupLogic = (group.logic || 'AND').toUpperCase();
                        const conditionResults = group.conditions.map(cond => this.evaluateCondition(cond));
                        if (groupLogic === 'OR') return conditionResults.includes(true);
                        return !conditionResults.includes(false);
                    },

                    evaluateCondition(condition) {
                        if (!condition.field) return true;
                        const targetField = this.allFieldDefinitions.find(f => f.name === condition.field);
                        if (!targetField) return true;
                        const actualValue = this.allFieldValues[targetField.id] !== undefined
                            ? this.allFieldValues[targetField.id]
                            : this.fieldValues[targetField.id];
                        const operator = condition.operator || 'equals';
                        if (operator === 'is_in' || operator === 'is_not_in') {
                            let comparisonList;
                            const source = condition.list_source || 'manual';
                            if (source === 'lookup' && condition._resolved_list) {
                                comparisonList = condition._resolved_list;
                            } else {
                                comparisonList = Array.isArray(condition.value) ? condition.value : (condition.value ? [condition.value] : []);
                            }
                            return this.compareValues(actualValue, operator, comparisonList);
                        }
                        return this.compareValues(actualValue, operator, condition.value);
                    },

                    compareValues(actual, operator, expected) {
                        switch (operator) {
                            case 'equals': return this.isEqual(actual, expected);
                            case 'not_equals': return !this.isEqual(actual, expected);
                            case 'greater_than': return parseFloat(actual) > parseFloat(expected);
                            case 'greater_or_equal': return parseFloat(actual) >= parseFloat(expected);
                            case 'less_than': return parseFloat(actual) < parseFloat(expected);
                            case 'less_or_equal': return parseFloat(actual) <= parseFloat(expected);
                            case 'is_null': return this.isEmpty(actual);
                            case 'is_not_null': return !this.isEmpty(actual);
                            case 'in': case 'is_in': return this.isIn(actual, expected);
                            case 'not_in': case 'is_not_in': return !this.isIn(actual, expected);
                            case 'contains': return String(actual || '').toLowerCase().includes(String(expected || '').toLowerCase());
                            case 'starts_with': return String(actual || '').toLowerCase().startsWith(String(expected || '').toLowerCase());
                            case 'ends_with': return String(actual || '').toLowerCase().endsWith(String(expected || '').toLowerCase());
                            case 'is_true': return actual === true || actual === 1 || actual === '1' || String(actual).toLowerCase() === 'true';
                            case 'is_false': return actual === false || actual === 0 || actual === '0' || String(actual).toLowerCase() === 'false' || this.isEmpty(actual);
                            default: return true;
                        }
                    },

                    isEqual(a, b) {
                        if (a === b) return true;
                        if (a === null && b === null) return true;
                        if (typeof a === 'number' && typeof b === 'number') return a === b;
                        if (!isNaN(a) && !isNaN(b)) return parseFloat(a) === parseFloat(b);
                        return String(a || '').toLowerCase() === String(b || '').toLowerCase();
                    },

                    isEmpty(value) {
                        if (value === null || value === undefined) return true;
                        if (typeof value === 'string' && value.trim() === '') return true;
                        if (Array.isArray(value) && value.length === 0) return true;
                        return false;
                    },

                    isIn(actual, expected) {
                        if (!Array.isArray(expected)) expected = [expected];
                        if (Array.isArray(actual)) return actual.some(item => expected.includes(item));
                        return expected.includes(actual);
                    },

                    toggleMulti(fieldId, value) {
                        let v = this.fieldValues[fieldId] || [];
                        if (!Array.isArray(v)) v = [];
                        const idx = v.indexOf(value);
                        if (idx > -1) { v.splice(idx, 1); } else { v.push(value); }
                        $wire.set('extraFieldValues.' + fieldId, [...v]);
                    }
                }"
            >
                @php
                    // Modus-Variable fuer das Render: Akkordeon oder klassisch
                    $isAccordionMode = $useAccordionLayout && (count($openFieldIds ?? []) > 0 || count($accordionFieldIds ?? []) > 0);
                @endphp

                @if($isAccordionMode)
                    {{-- Pflichtfelder + bedingt sichtbar (oben offen) --}}
                    <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl p-6 sm:p-8">
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-900 mb-1">Pflichtfelder</h2>
                            <p class="text-sm text-gray-400">Bitte fülle die folgenden Felder aus.</p>
                            @if($requiredTotal > 0)
                                <div class="mt-3 inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-50 text-xs font-medium text-gray-600">
                                    <span>{{ $requiredFilled }}/{{ $requiredTotal }} ausgefüllt</span>
                                    @if($requiredFilled === $requiredTotal && $requiredTotal > 0)
                                        <span class="text-emerald-500">✓</span>
                                    @endif
                                </div>
                            @endif

                            {{-- A3: welche Pflichtfelder noch fehlen (klickbar → scrollt zum Feld) --}}
                            @if(count($missingRequiredFields ?? []) > 0)
                                <div class="mt-3">
                                    <p class="text-xs font-medium text-gray-500 mb-1.5">Diese Pflichtfelder fehlen noch:</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($missingRequiredFields as $missingField)
                                            <button
                                                type="button"
                                                x-on:click="$dispatch('scroll-to-field', { fieldId: {{ $missingField['id'] }} })"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-50 text-red-600 text-xs font-medium hover:bg-red-100 transition-colors"
                                            >
                                                {{ $missingField['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        @include('platform::livewire.public._public-extra-field-form-loop', ['renderBlock' => 'open'])
                    </div>

                    {{-- Optionale Felder im Akkordeon --}}
                    @if(count($accordionFieldIds ?? []) > 0)
                        <div class="mt-6 bg-white rounded-3xl border border-black/[0.06] shadow-2xl overflow-hidden"
                             x-data="{ open: {{ $requiredFilled === $requiredTotal && $requiredTotal > 0 ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open"
                                    class="w-full text-left px-6 sm:px-8 py-5 flex items-center justify-between hover:bg-gray-50 transition">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900">Weitere Angaben (optional)</h3>
                                    <p class="text-sm text-gray-400 mt-0.5">Diese kannst du gerne machen — sie sind freiwillig.</p>
                                    @if($optionalTotal > 0)
                                        <div class="mt-2 inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full bg-gray-50 text-xs text-gray-500">
                                            {{ $optionalFilled }}/{{ $optionalTotal }} ausgefüllt
                                        </div>
                                    @endif
                                </div>
                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition class="px-6 sm:px-8 pb-8 border-t border-gray-100 pt-6">
                                @include('platform::livewire.public._public-extra-field-form-loop', ['renderBlock' => 'accordion'])
                            </div>
                        </div>
                    @endif

                    {{-- Submit-Button (auf der Form-Ebene, ausserhalb der Cards) --}}
                    <div class="mt-10 flex justify-end">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-7 py-3 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-semibold rounded-xl transition-all duration-200 hover:shadow-lg hover:shadow-indigo-500/25 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="save">Speichern</span>
                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Wird gespeichert...
                            </span>
                        </button>
                    </div>
                @else
                    {{-- Klassisches Layout (Default — andere Module unveraendert) --}}
                    <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl p-6 sm:p-8">
                        <div class="mb-8">
                            <h2 class="text-xl font-bold text-gray-900 mb-1">Offene Felder</h2>
                            <p class="text-sm text-gray-400">Bitte füllen Sie die folgenden Felder aus.</p>
                        </div>
                        @include('platform::livewire.public._public-extra-field-form-loop', ['renderBlock' => null])

                        {{-- Submit --}}
                        <div class="mt-10 flex justify-end">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                class="px-7 py-3 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-semibold rounded-xl transition-all duration-200 hover:shadow-lg hover:shadow-indigo-500/25 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span wire:loading.remove wire:target="save">Speichern</span>
                                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    Wird gespeichert...
                                </span>
                            </button>
                        </div>
                    </div>
                @endif
            </form>
        </main>

        <footer class="max-w-3xl mx-auto px-6 pb-8 text-center">
            <p class="text-[11px] text-white/20 tracking-wider uppercase">Powered by Platform</p>
        </footer>

    {{-- Saved --}}
    @elseif($state === 'saved')
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl w-full max-w-md p-10 text-center">
                <div class="w-20 h-20 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">Gespeichert!</h1>
                <p class="text-gray-500 text-lg mb-2">Ihre Angaben wurden erfolgreich gespeichert.</p>

                @if($totalFields > 0)
                    <div class="mt-6 mb-6">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-sm font-medium text-gray-600">Fortschritt</span>
                            <span class="text-sm font-semibold text-gray-900">{{ $filledFields }}/{{ $totalFields }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 h-2.5 rounded-full transition-all" style="width: {{ round(($filledFields / $totalFields) * 100) }}%"></div>
                        </div>
                    </div>
                @endif

                @if($filledFields < $totalFields)
                    <button
                        wire:click="continueEditing"
                        wire:loading.attr="disabled"
                        class="px-7 py-3 bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-semibold rounded-xl transition-all duration-200 hover:shadow-lg hover:shadow-indigo-500/25 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="continueEditing">Weiter bearbeiten</span>
                        <span wire:loading wire:target="continueEditing" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </span>
                    </button>
                @endif

                {{-- Modul-Hook: optionales Snippet (z.B. Recruiting Schulungs-Bestaetigung) --}}
                @php $completionExtras = $this->getCompletionExtras(); @endphp
                @if($completionExtras)
                    <div class="mt-6">{!! $completionExtras !!}</div>
                @endif
            </div>
        </div>

    {{-- Completed --}}
    @elseif($state === 'completed')
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-3xl border border-black/[0.06] shadow-2xl w-full max-w-md p-10 text-center">
                <div class="w-20 h-20 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-3">Alles erledigt!</h1>
                <p class="text-gray-500 text-lg">Vielen Dank! Alle Felder sind ausgefüllt. Sie können diese Seite jetzt schließen.</p>

                {{-- Modul-Hook: optionales Snippet (z.B. Recruiting Schulungs-Bestaetigung) --}}
                @php $completionExtras = $this->getCompletionExtras(); @endphp
                @if($completionExtras)
                    <div class="mt-6">{!! $completionExtras !!}</div>
                @endif
            </div>
        </div>
    @endif
</div>
