<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Semantic Layer" icon="heroicon-o-document-text" />
    </x-slot>

    <x-ui-page-container>
        <div class="p-4 space-y-6">

            {{-- Save-Feedback --}}
            @if($lastSaveMessage)
                <div class="rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-700 px-4 py-2 text-sm">
                    {{ $lastSaveMessage }}
                </div>
            @endif

            {{-- Section 1: Modul-Vorschau-Selector --}}
            <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] p-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wider">
                        Vorschau-Modul
                    </span>
                    <button
                        wire:click="selectModule(null)"
                        class="text-xs px-2 py-1 rounded-md border transition-colors
                            {{ $selectedModule === null
                                ? 'bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white'
                                : 'border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-[var(--ui-primary)]' }}">
                        — kein Modul —
                    </button>
                    @foreach($availableModules as $mod)
                        <button
                            wire:click="selectModule('{{ $mod }}')"
                            class="text-xs px-2 py-1 rounded-md border transition-colors
                                {{ $selectedModule === $mod
                                    ? 'bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white'
                                    : 'border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-[var(--ui-primary)]' }}">
                            {{ $mod }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-[var(--ui-muted)]">
                    Der Resolver wird mit dem ausgewählten Modul-Kontext ausgeführt. Bei <em>— kein Modul —</em> gilt nur das production-Gate.
                </p>
            </div>

            {{-- Section 2: Resolved-Previews --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                    <div class="px-4 py-2 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                            Resolved für <strong>aktuelles Team</strong>
                        </span>
                        @if($resolvedForCurrentTeam)
                            <span class="text-[10px] text-[var(--ui-muted)]">
                                {{ $resolvedForCurrentTeam['token_count'] }} tokens · {{ implode(' → ', $resolvedForCurrentTeam['scope_chain']) }}
                            </span>
                        @endif
                    </div>
                    <div class="p-4">
                        @if($resolvedForCurrentTeam)
                            <pre class="text-[11px] leading-relaxed whitespace-pre-wrap font-mono text-[var(--ui-secondary)] bg-[var(--ui-muted-5)] p-3 rounded">{{ $resolvedForCurrentTeam['rendered_block'] }}</pre>
                        @else
                            <div class="text-sm text-[var(--ui-muted)] text-center py-6">
                                Kein Layer aktiv für diese Kombination.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                    <div class="px-4 py-2 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                            Resolved <strong>ohne Team</strong> (nur global)
                        </span>
                        @if($resolvedForNoTeam)
                            <span class="text-[10px] text-[var(--ui-muted)]">
                                {{ $resolvedForNoTeam['token_count'] }} tokens · {{ implode(' → ', $resolvedForNoTeam['scope_chain']) }}
                            </span>
                        @endif
                    </div>
                    <div class="p-4">
                        @if($resolvedForNoTeam)
                            <pre class="text-[11px] leading-relaxed whitespace-pre-wrap font-mono text-[var(--ui-secondary)] bg-[var(--ui-muted-5)] p-3 rounded">{{ $resolvedForNoTeam['rendered_block'] }}</pre>
                        @else
                            <div class="text-sm text-[var(--ui-muted)] text-center py-6">
                                Kein globaler Layer aktiv.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Section 3: Editor (inline) --}}
            @if($editMode)
                <div class="rounded-lg border border-[var(--ui-primary)]/40 bg-[var(--ui-surface)] shadow-sm">
                    <div class="px-4 py-2 border-b border-[var(--ui-border)]/60 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-primary)]">
                            @if($editMode === 'new-layer')
                                Neuen Layer anlegen · {{ $editScope === 'global' ? 'global' : 'team · ' . $currentTeamLabel }} · <span class="font-mono">{{ $editLabel }}</span>
                            @else
                                Neue Version · Layer #{{ $editLayerId }}
                            @endif
                        </span>
                        <button
                            wire:click="cancelEdit"
                            class="text-[10px] px-2 py-1 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-red-500 hover:text-red-600 transition-colors">
                            Abbrechen
                        </button>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
                        {{-- Form --}}
                        <div class="p-4 space-y-4 border-r border-[var(--ui-border)]/40">
                            {{-- Label (nur bei neuem Layer) --}}
                            @if($editMode === 'new-layer')
                                <div>
                                    <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                        Label
                                        <span class="ml-1 text-[var(--ui-muted)]/70 font-normal normal-case">(z.B. leitbild, mcp, planner — pro Scope+Label max. 1 Layer)</span>
                                    </label>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="editLabel"
                                        placeholder="leitbild"
                                        class="w-full px-2 py-1.5 text-sm font-mono rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80" />
                                </div>
                            @endif

                            {{-- SemVer + Type --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                        SemVer
                                    </label>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="formSemver"
                                        placeholder="1.0.0"
                                        class="w-full px-2 py-1.5 text-sm font-mono rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80" />
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                        Version-Type
                                    </label>
                                    <select
                                        wire:model.live="formVersionType"
                                        class="w-full px-2 py-1.5 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80">
                                        <option value="patch">patch — Schärfung ohne Richtungsänderung</option>
                                        <option value="minor">minor — Erweiterung im bestehenden Rahmen</option>
                                        <option value="major">major — Fundamentale Identitätsänderung</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Perspektive --}}
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                    Perspektive
                                    <span class="ml-1 text-[var(--ui-muted)]/70 font-normal normal-case">(1 String, max 500 Zeichen — aus wessen Sicht spricht die Plattform?)</span>
                                </label>
                                <textarea
                                    wire:model.live.debounce.400ms="formPerspektive"
                                    rows="2"
                                    placeholder="Wir sind ehrliche Handwerker, die Werkzeuge zuerst für sich selbst bauen."
                                    class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80"></textarea>
                                <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">{{ mb_strlen(trim($formPerspektive)) }}/500</div>
                            </div>

                            {{-- Ton --}}
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                    Ton
                                    <span class="ml-1 text-[var(--ui-muted)]/70 font-normal normal-case">(eine Zeile pro Eintrag, max 12)</span>
                                </label>
                                <textarea
                                    wire:model.live.debounce.400ms="formTon"
                                    rows="4"
                                    placeholder="klar&#10;direkt&#10;kurze Sätze"
                                    class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80"></textarea>
                            </div>

                            {{-- Heuristiken --}}
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                    Heuristiken
                                    <span class="ml-1 text-[var(--ui-muted)]/70 font-normal normal-case">(eine Zeile pro Entscheidungsregel, max 12)</span>
                                </label>
                                <textarea
                                    wire:model.live.debounce.400ms="formHeuristiken"
                                    rows="4"
                                    placeholder="Im Zweifel: weniger sagen.&#10;Outcome immer explizit machen.&#10;Keine Lösung ohne Problemdefinition."
                                    class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80"></textarea>
                            </div>

                            {{-- Negativ-Raum --}}
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                    Negativ-Raum
                                    <span class="ml-1 text-[var(--ui-muted)]/70 font-normal normal-case">(was wir nie sagen — stärkstes Signal, max 12)</span>
                                </label>
                                <textarea
                                    wire:model.live.debounce.400ms="formNegativRaum"
                                    rows="4"
                                    placeholder="keine Buzzwords&#10;kein Weichspüler&#10;nie vage Versprechen"
                                    class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80"></textarea>
                            </div>

                            {{-- Notes --}}
                            <div>
                                <label class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-1 block">
                                    Notizen (optional)
                                    <span class="ml-1 text-[var(--ui-muted)]/70 font-normal normal-case">(Was wurde bewusst weggelassen? Warum?)</span>
                                </label>
                                <textarea
                                    wire:model="formNotes"
                                    rows="2"
                                    class="w-full px-3 py-2 text-sm rounded-md border border-[var(--ui-border)]/60 bg-white/60 focus:outline-2 focus:outline-[var(--ui-primary)] focus:bg-white/80"></textarea>
                            </div>

                            {{-- Errors --}}
                            @if(!empty($formErrors))
                                <div class="rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2">
                                    <div class="text-[10px] font-semibold uppercase tracking-wider text-red-700 mb-1">Schema-Fehler</div>
                                    <ul class="text-[11px] text-red-700 space-y-0.5 list-disc list-inside">
                                        @foreach($formErrors as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 pt-2 border-t border-[var(--ui-border)]/40">
                                <button
                                    wire:click="saveVersion"
                                    wire:loading.attr="disabled"
                                    class="text-xs px-3 py-1.5 rounded-md bg-[var(--ui-primary)] text-white font-medium hover:opacity-90 transition-opacity disabled:opacity-50">
                                    <span wire:loading.remove wire:target="saveVersion">Version speichern</span>
                                    <span wire:loading wire:target="saveVersion">Speichere…</span>
                                </button>
                                <button
                                    wire:click="cancelEdit"
                                    class="text-xs px-3 py-1.5 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-red-500 hover:text-red-600 transition-colors">
                                    Abbrechen
                                </button>
                                <span class="text-[10px] text-[var(--ui-muted)] ml-auto">
                                    Speichern legt eine neue Version an und markiert sie als <strong>aktiv</strong>. Status bleibt unverändert.
                                </span>
                            </div>
                        </div>

                        {{-- Live-Preview --}}
                        <div class="p-4 bg-[var(--ui-muted-5)]/50 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                    Live-Preview · rendered_block
                                </span>
                                <span class="text-[10px] text-[var(--ui-muted)]">
                                    {{ $formTokenCount }} tokens
                                </span>
                            </div>

                            @if($formWarning)
                                <div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-[11px] text-amber-700">
                                    ⚠ {{ $formWarning }}
                                </div>
                            @elseif($formTokenCount >= 80 && $formTokenCount <= 250)
                                <div class="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-[11px] text-emerald-700">
                                    ✓ Token-Budget: {{ $formTokenCount }} im Soft-Bereich 80–250 (Ziel 150–200)
                                </div>
                            @endif

                            @if($formPreviewBlock)
                                <pre class="text-[11px] leading-relaxed whitespace-pre-wrap font-mono text-[var(--ui-secondary)] bg-white/60 p-3 rounded border border-[var(--ui-border)]/40">{{ $formPreviewBlock }}</pre>
                            @else
                                <div class="text-sm text-[var(--ui-muted)] text-center py-10">
                                    Preview erscheint, sobald ein Feld ausgefüllt ist.
                                </div>
                            @endif

                            <div class="text-[10px] text-[var(--ui-muted)] leading-relaxed pt-2 border-t border-[var(--ui-border)]/40">
                                <strong>Hinweis:</strong> Versionen sind immutable. Beim Speichern wird eine neue Version angelegt
                                und als <code class="px-1 bg-white/60 rounded">current_version</code> markiert. Alte Versionen bleiben
                                in der DB als historischer Stand erhalten.
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Section 4: Layer-Liste --}}
            <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                <div class="px-4 py-2 border-b border-[var(--ui-border)]/60 flex items-center justify-between flex-wrap gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                        Layers
                    </span>

                    @if(! $editMode)
                        <div class="flex items-center gap-2">
                            <button
                                wire:click="openNewLayer('global')"
                                class="text-[10px] px-2 py-1 rounded-md bg-[var(--ui-primary)] text-white hover:opacity-90 transition-opacity">
                                + Global-Layer
                            </button>
                            @if($currentTeamId)
                                <button
                                    wire:click="openNewLayer('team')"
                                    class="text-[10px] px-2 py-1 rounded-md border border-[var(--ui-primary)]/60 text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/10 transition-colors">
                                    + Team-Layer «{{ $currentTeamLabel }}»
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if(empty($layers))
                    <div class="p-6 text-center space-y-3">
                        <div class="text-sm text-[var(--ui-muted)]">
                            Noch keine Layer.
                        </div>
                        @if(! $editMode)
                            <button
                                wire:click="openNewLayer('global')"
                                class="text-xs px-3 py-1.5 rounded-md bg-[var(--ui-primary)] text-white hover:opacity-90 transition-opacity">
                                Ersten Layer anlegen (global, v1.0.0)
                            </button>
                        @endif
                        <div class="text-[10px] text-[var(--ui-muted)]">
                            Alternativ via Console: <code>php artisan layer:create --scope=global --semver=1.0.0 --from-file=…</code>
                        </div>
                    </div>
                @else
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @foreach($layers as $layer)
                            @php $isExpanded = in_array($layer['id'], $expandedLayers, true); @endphp
                            <div class="p-4 space-y-3">
                                <div class="flex items-center flex-wrap gap-3">
                                    <span class="text-sm font-semibold text-[var(--ui-secondary)]">
                                        {{ $layer['scope_label'] }}
                                    </span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-mono bg-[var(--ui-muted-5)] text-[var(--ui-secondary)] border border-[var(--ui-border)]/40">
                                        {{ $layer['label'] }}
                                    </span>
                                    @if($layer['is_ungated'])
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-500/10 text-blue-600 border border-blue-500/20">ungated</span>
                                    @endif
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium uppercase tracking-wider
                                        @if($layer['status'] === 'production') bg-emerald-500/15 text-emerald-700 border border-emerald-500/30
                                        @elseif($layer['status'] === 'pilot') bg-amber-500/15 text-amber-700 border border-amber-500/30
                                        @elseif($layer['status'] === 'draft') bg-slate-500/15 text-slate-600 border border-slate-500/30
                                        @else bg-zinc-500/15 text-zinc-600 border border-zinc-500/30
                                        @endif">
                                        {{ $layer['status'] }}
                                    </span>
                                    @if($layer['current_semver'])
                                        <span class="text-[10px] text-[var(--ui-muted)]">
                                            v{{ $layer['current_semver'] }}
                                            @if($layer['token_count']) · {{ $layer['token_count'] }} tokens @endif
                                            · {{ $layer['version_count'] }} Version(en)
                                        </span>
                                    @else
                                        <span class="text-[10px] text-red-500">
                                            keine aktive Version
                                        </span>
                                    @endif

                                    @if(! $editMode)
                                        <div class="ml-auto flex items-center gap-2">
                                            @if($layer['content'])
                                                <button
                                                    wire:click="toggleLayerContent({{ $layer['id'] }})"
                                                    class="text-[10px] px-2 py-0.5 rounded-md border border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-[var(--ui-primary)] hover:text-[var(--ui-primary)] transition-colors">
                                                    {{ $isExpanded ? 'Content ausblenden' : 'Content anzeigen' }}
                                                </button>
                                            @endif
                                            <button
                                                wire:click="openNewVersion({{ $layer['id'] }})"
                                                class="text-[10px] px-2 py-0.5 rounded-md border border-[var(--ui-primary)]/60 text-[var(--ui-primary)] hover:bg-[var(--ui-primary)]/10 transition-colors">
                                                + Neue Version
                                            </button>
                                        </div>
                                    @endif

                                    @if($layer['updated_at'])
                                        <span class="text-[10px] text-[var(--ui-muted)] @if($editMode) ml-auto @endif">
                                            zuletzt {{ $layer['updated_at'] }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Version Content (expandable) --}}
                                @if($isExpanded && $layer['content'])
                                    <div class="rounded-lg border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/50 p-4 space-y-3">
                                        <div class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                            Aktive Version · v{{ $layer['current_semver'] }}
                                        </div>

                                        {{-- Perspektive --}}
                                        <div>
                                            <div class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase mb-1">Perspektive</div>
                                            <div class="text-sm text-[var(--ui-secondary)] bg-white/60 rounded px-3 py-2 border border-[var(--ui-border)]/30">
                                                {{ $layer['content']['perspektive'] ?: '—' }}
                                            </div>
                                        </div>

                                        {{-- Ton --}}
                                        @if(!empty($layer['content']['ton']))
                                            <div>
                                                <div class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase mb-1">Ton</div>
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($layer['content']['ton'] as $item)
                                                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-white/60 border border-[var(--ui-border)]/30 text-[var(--ui-secondary)]">{{ $item }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Heuristiken --}}
                                        @if(!empty($layer['content']['heuristiken']))
                                            <div>
                                                <div class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase mb-1">Heuristiken</div>
                                                <ul class="text-[11px] text-[var(--ui-secondary)] space-y-1 list-disc list-inside bg-white/60 rounded px-3 py-2 border border-[var(--ui-border)]/30">
                                                    @foreach($layer['content']['heuristiken'] as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        {{-- Negativ-Raum --}}
                                        @if(!empty($layer['content']['negativ_raum']))
                                            <div>
                                                <div class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase mb-1">Negativ-Raum</div>
                                                <ul class="text-[11px] text-[var(--ui-secondary)] space-y-1 list-disc list-inside bg-white/60 rounded px-3 py-2 border border-[var(--ui-border)]/30">
                                                    @foreach($layer['content']['negativ_raum'] as $item)
                                                        <li>{{ $item }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        {{-- Notes --}}
                                        @if($layer['content']['notes'])
                                            <div>
                                                <div class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase mb-1">Notizen</div>
                                                <div class="text-[11px] text-[var(--ui-muted)] bg-white/60 rounded px-3 py-2 border border-[var(--ui-border)]/30 italic">
                                                    {{ $layer['content']['notes'] }}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Status-Switcher --}}
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase">Status:</span>
                                    @foreach(['draft','pilot','production','archived'] as $st)
                                        <button
                                            wire:click="setStatus({{ $layer['id'] }}, '{{ $st }}')"
                                            @class([
                                                'text-[10px] px-2 py-0.5 rounded-md border transition-colors',
                                                'bg-[var(--ui-primary)] border-[var(--ui-primary)] text-white' => $layer['status'] === $st,
                                                'border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-[var(--ui-primary)]' => $layer['status'] !== $st,
                                            ])>
                                            {{ $st }}
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Enabled Modules --}}
                                <div class="flex items-start gap-2 flex-wrap">
                                    <span class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase mt-1">
                                        Enabled Modules:
                                    </span>
                                    @if(empty($availableModules))
                                        <span class="text-[11px] text-[var(--ui-muted)] italic">keine Module registriert</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($availableModules as $mod)
                                                @php $on = in_array($mod, $layer['enabled_modules'] ?? [], true); @endphp
                                                <button
                                                    wire:click="toggleModule({{ $layer['id'] }}, '{{ $mod }}')"
                                                    @class([
                                                        'text-[10px] px-2 py-0.5 rounded-md border transition-colors',
                                                        'bg-emerald-500 border-emerald-600 text-white' => $on,
                                                        'border-[var(--ui-border)]/60 text-[var(--ui-secondary)] hover:border-emerald-500 hover:text-emerald-600' => ! $on,
                                                    ])>
                                                    {{ $mod }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <p class="text-[10px] text-[var(--ui-muted)]">
                Hinweis: Versionen sind immutable — jede Änderung erzeugt eine neue Version. Status + enabled_modules sind direkt umschaltbar.
                Der Resolver cacht 1 h; alle Änderungen invalidieren den Cache automatisch.
            </p>
        </div>
    </x-ui-page-container>
</x-ui-page>
