{{--
    ExtraFields-Editor — aus der Terminal-Shell extrahiert (App-Komponente).
    Intern zweispaltig: Liste (war Shell-Sidebar) | Editor (war Main-Panel).
    Kontext kommt als reaktive Props (efContextType/efContextId), Breadcrumb als Prop.
    Alpine-Vars (fullscreen/open) erben aus dem Shell-Root-Scope.
--}}
<div class="flex-1 min-h-0 flex flex-col">

    {{-- ── Header ── --}}
    <div class="px-4 flex items-center gap-2.5 border-b border-[var(--t-border)]/60 flex-shrink-0"
         :class="fullscreen ? 'h-14 text-sm' : 'h-11 text-xs'">
            @if($this->efContextType && $this->efContextId)
              @php $efHeaderBreadcrumb = $this->contextBreadcrumb; @endphp
              <span class="text-[14px]">{{ $efHeaderBreadcrumb['icon'] ?? '📎' }}</span>
              <div class="flex flex-col leading-tight">
                @php $efContextTitle = $efHeaderBreadcrumb['title'] ?? $this->efContextLabel() ?? 'Kontext'; @endphp
                <span class="font-bold text-[13px] text-[var(--t-text)]">{{ $efContextTitle }}</span>
                <span class="text-[10px] text-[var(--t-text-muted)]">Extra-Felder · {{ $this->efTab === 'fields' ? 'Felder' : 'Lookups' }}</span>
              </div>
            @else
              <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 0 0 2.25-2.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v2.25A2.25 2.25 0 0 0 6 10.5Zm0 9.75h2.25A2.25 2.25 0 0 0 10.5 18v-2.25a2.25 2.25 0 0 0-2.25-2.25H6a2.25 2.25 0 0 0-2.25 2.25V18A2.25 2.25 0 0 0 6 20.25Zm9.75-9.75H18a2.25 2.25 0 0 0 2.25-2.25V6A2.25 2.25 0 0 0 18 3.75h-2.25A2.25 2.25 0 0 0 13.5 6v2.25a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
              <span class="font-bold text-[13px] text-[var(--t-text)]">Extra-Felder</span>
            @endif
    </div>

    {{-- ── Two-column: Liste | Editor ── --}}
    <div class="flex-1 min-h-0 flex">

        {{-- Liste (war Shell-Sidebar) --}}
        <aside class="w-64 flex-shrink-0 border-r border-[var(--t-border)]/60 flex flex-col overflow-y-auto"
               x-data="{ showNewField: false, showNewLookup: false }">
          <div class="px-3 py-3 space-y-3">

            {{-- Tab switcher: Felder | Lookups --}}
            <div class="flex rounded-md border border-[var(--t-border)]/60 overflow-hidden">
              <button wire:click="$set('efTab', 'fields')"
                      class="flex-1 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider transition {{ $this->efTab === 'fields' ? 'bg-[var(--t-accent)]/20 text-[var(--t-accent)]' : 'text-[var(--t-text-muted)] hover:bg-white/5' }}">
                Felder
              </button>
              <button wire:click="$set('efTab', 'lookups')"
                      class="flex-1 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider transition {{ $this->efTab === 'lookups' ? 'bg-[var(--t-accent)]/20 text-[var(--t-accent)]' : 'text-[var(--t-text-muted)] hover:bg-white/5' }}">
                Lookups
              </button>
            </div>

            {{-- === Fields Tab === --}}
            @if($this->efTab === 'fields')
              {{-- Field list --}}
              <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Felder ({{ count($this->efDefinitions) }})</h3>
                <div class="space-y-0.5">
                  @forelse($this->efDefinitions as $def)
                    <div class="group flex items-center gap-1.5 px-2 py-1.5 rounded-md transition cursor-pointer {{ $this->efEditingDefinitionId === $def['id'] ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'hover:bg-white/[0.03]' }}"
                         wire:click="efStartEditDefinition({{ $def['id'] }})">
                      <div class="min-w-0 flex-1">
                        <div class="text-[11px] text-[var(--t-text)] truncate">{{ $def['label'] }}</div>
                        <div class="flex items-center gap-1 mt-0.5">
                          <span class="text-[9px] px-1 py-0 rounded bg-white/[0.06] text-[var(--t-text-muted)]">{{ $def['type_label'] ?? $def['type'] }}</span>
                          @if($def['is_required'])<span class="text-[9px] text-amber-400">pflicht</span>@endif
                          @if($def['is_encrypted'])<span class="text-[9px] text-purple-400">🔒</span>@endif
                          @if($def['has_visibility_conditions'])<span class="text-[9px] text-blue-400">👁</span>@endif
                        </div>
                      </div>
                      <button wire:click.stop="efDeleteDefinition({{ $def['id'] }})"
                              class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-red-500/20 text-[var(--t-text-muted)] hover:text-red-400 transition"
                              onclick="return confirm('Feld wirklich löschen?')">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                      </button>
                    </div>
                  @empty
                    <p class="text-[10px] text-[var(--t-text-muted)] text-center py-3">Keine Felder definiert</p>
                  @endforelse
                </div>
              </div>

              {{-- New field form (collapsible) --}}
              <div>
                <button @click="showNewField = !showNewField"
                        class="flex items-center gap-1.5 w-full text-left">
                  <svg class="w-3 h-3 text-[var(--t-text-muted)] transition-transform" :class="showNewField && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                  <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Neues Feld</h3>
                </button>

                <div x-show="showNewField" x-collapse class="mt-2 space-y-2">
                  {{-- Label --}}
                  <div>
                    <input type="text" wire:model="efNewField.label" placeholder="Feldname"
                           class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                    @error('efNewField.label') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                  </div>

                  {{-- Type --}}
                  <div>
                    <select wire:model.live="efNewField.type"
                            class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] [&>option]:bg-[var(--t-bg)] [&>option]:text-[var(--t-text)]">
                      @foreach($this->efAvailableTypes() as $typeKey => $typeLabel)
                        <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                      @endforeach
                    </select>
                  </div>

                  {{-- Select options --}}
                  @if($this->efNewField['type'] === 'select')
                    <div>
                      <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Optionen</label>
                      <div class="flex gap-1">
                        <input type="text" wire:model="efNewOptionText" wire:keydown.enter="efAddNewOption" placeholder="Option hinzufügen"
                               class="flex-1 px-2 py-1 text-[10px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                        <button wire:click="efAddNewOption" class="px-2 py-1 text-[10px] bg-[var(--t-accent)]/20 text-[var(--t-accent)] rounded-md hover:bg-[var(--t-accent)]/30 transition">+</button>
                      </div>
                      @if(!empty($this->efNewField['options']))
                        <div class="flex flex-wrap gap-1 mt-1.5">
                          @foreach($this->efNewField['options'] as $i => $opt)
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[10px] bg-white/[0.06] text-[var(--t-text)] rounded">
                              {{ $opt }}
                              <button wire:click="efRemoveNewOption({{ $i }})" class="text-[var(--t-text-muted)] hover:text-red-400">&times;</button>
                            </span>
                          @endforeach
                        </div>
                      @endif
                      @error('efNewField.options') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                    </div>
                  @endif

                  {{-- Lookup --}}
                  @if($this->efNewField['type'] === 'lookup')
                    <div>
                      <select wire:model="efNewField.lookup_id"
                              class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] [&>option]:bg-[var(--t-bg)] [&>option]:text-[var(--t-text)]">
                        <option value="">Lookup wählen…</option>
                        @foreach($this->efLookups as $lu)
                          <option value="{{ $lu['id'] }}">{{ $lu['label'] }} ({{ $lu['values_count'] }})</option>
                        @endforeach
                      </select>
                      @error('efNewField.lookup_id') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                    </div>
                  @endif

                  {{-- Regex --}}
                  @if($this->efNewField['type'] === 'regex')
                    <div>
                      <input type="text" wire:model="efNewField.regex_pattern" placeholder="Regex Pattern"
                             class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                      @error('efNewField.regex_pattern') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                    </div>
                  @endif

                  {{-- Checkboxes --}}
                  <div class="flex flex-wrap gap-3">
                    <label class="flex items-center gap-1.5 text-[10px] text-[var(--t-text-muted)] cursor-pointer">
                      <input type="checkbox" wire:model="efNewField.is_required" class="rounded border-[var(--t-border)]/60 text-[var(--t-accent)] focus:ring-[var(--t-accent)] w-3 h-3" />
                      Pflicht
                    </label>
                    <label class="flex items-center gap-1.5 text-[10px] text-[var(--t-text-muted)] cursor-pointer">
                      <input type="checkbox" wire:model="efNewField.is_encrypted" class="rounded border-[var(--t-border)]/60 text-[var(--t-accent)] focus:ring-[var(--t-accent)] w-3 h-3" />
                      Verschlüsselt
                    </label>
                  </div>

                  {{-- Submit --}}
                  <button wire:click="efCreateDefinition"
                          class="w-full px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition">
                    Feld erstellen
                  </button>
                </div>
              </div>
            @endif

            {{-- === Lookups Tab === --}}
            @if($this->efTab === 'lookups')
              {{-- Lookup list --}}
              <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Lookups ({{ count($this->efLookups) }})</h3>
                <div class="space-y-0.5">
                  @forelse($this->efLookups as $lu)
                    <div class="group flex items-center gap-1.5 px-2 py-1.5 rounded-md transition {{ $this->efSelectedLookupId === $lu['id'] ? 'bg-[var(--t-accent)]/15 ring-1 ring-[var(--t-accent)]/30' : 'hover:bg-white/[0.03]' }}">
                      @if($this->efEditingLookupId === $lu['id'])
                        <div class="flex-1 space-y-1">
                          <input type="text" wire:model="efEditLookup.label"
                                 class="w-full px-2 py-1 text-[10px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                          <div class="flex gap-1">
                            <button wire:click="efSaveEditLookup" class="px-2 py-0.5 text-[9px] bg-[var(--t-accent)] text-white rounded hover:bg-[var(--t-accent)]/80 transition">Speichern</button>
                            <button wire:click="efCancelEditLookup" class="px-2 py-0.5 text-[9px] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">Abbrechen</button>
                          </div>
                        </div>
                      @else
                        <div class="min-w-0 flex-1 cursor-pointer" wire:click="efSelectLookup({{ $lu['id'] }})">
                          <div class="text-[11px] text-[var(--t-text)] truncate">{{ $lu['label'] }}</div>
                          <div class="flex items-center gap-1 mt-0.5">
                            <span class="text-[9px] text-[var(--t-text-muted)]">{{ $lu['values_count'] }} Werte</span>
                            @if($lu['is_system'])<span class="text-[9px] px-1 rounded bg-white/[0.06] text-[var(--t-text-muted)]">System</span>@endif
                          </div>
                        </div>
                        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                          @if(!$lu['is_system'])
                            <button wire:click.stop="efStartEditLookup({{ $lu['id'] }})" class="p-1 rounded hover:bg-white/10 text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z"/></svg>
                            </button>
                            <button wire:click.stop="efDeleteLookup({{ $lu['id'] }})" class="p-1 rounded hover:bg-red-500/20 text-[var(--t-text-muted)] hover:text-red-400 transition"
                                    onclick="return confirm('Lookup wirklich löschen?')">
                              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                            </button>
                          @endif
                        </div>
                      @endif
                    </div>
                  @empty
                    <p class="text-[10px] text-[var(--t-text-muted)] text-center py-3">Keine Lookups vorhanden</p>
                  @endforelse
                </div>
              </div>

              {{-- New lookup form (collapsible) --}}
              <div>
                <button @click="showNewLookup = !showNewLookup"
                        class="flex items-center gap-1.5 w-full text-left">
                  <svg class="w-3 h-3 text-[var(--t-text-muted)] transition-transform" :class="showNewLookup && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                  <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Neuer Lookup</h3>
                </button>

                <div x-show="showNewLookup" x-collapse class="mt-2 space-y-2">
                  <div>
                    <input type="text" wire:model="efNewLookup.label" placeholder="Lookup-Name"
                           class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                    @error('efNewLookup.label') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
                  </div>
                  <div>
                    <input type="text" wire:model="efNewLookup.description" placeholder="Beschreibung (optional)"
                           class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                  </div>
                  <button wire:click="efCreateLookup"
                          class="w-full px-3 py-1.5 rounded-md text-[11px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition">
                    Lookup erstellen
                  </button>
                </div>
              </div>
            @endif

          </div>
        </aside>

        {{-- Editor (war Main-Panel) --}}
        <div class="flex-1 min-h-0 flex flex-col overflow-y-auto">

            {{-- Field Editor (5 sub-tabs) --}}
            @if($this->efEditingDefinitionId)
              @php $editDef = collect($this->efDefinitions)->firstWhere('id', $this->efEditingDefinitionId); @endphp
              <div class="flex-1 flex flex-col" :class="fullscreen ? 'p-6' : 'p-4'">
                {{-- Sub-tab navigation --}}
                <div class="flex items-center gap-1 mb-4 border-b border-[var(--ui-border)]/40 pb-2">
                  @foreach(['basis' => 'Basis', 'options' => 'Optionen', 'conditions' => 'Bedingungen', 'verification' => 'Verifizierung', 'autofill' => 'Autofill'] as $tabKey => $tabLabel)
                    <button wire:click="$set('efEditFieldTab', '{{ $tabKey }}')"
                            class="px-3 py-1.5 text-[11px] font-medium rounded-md transition {{ $this->efEditFieldTab === $tabKey ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)] hover:bg-[var(--ui-muted-5)]' }}">
                      {{ $tabLabel }}
                    </button>
                  @endforeach
                </div>

                {{-- Sub-tab: Basis --}}
                @if($this->efEditFieldTab === 'basis')
                  <div class="space-y-4 max-w-lg">
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Label</label>
                      <input type="text" wire:model="efEditField.label"
                             class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                      @error('efEditField.label') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Beschreibung</label>
                      <textarea wire:model="efEditField.description" rows="2"
                                class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 resize-none"></textarea>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Typ</label>
                      <select wire:model.live="efEditField.type"
                              class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        @foreach($this->efAvailableTypes() as $typeKey => $typeLabel)
                          <option value="{{ $typeKey }}">{{ $typeLabel }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Platzhalter</label>
                      <input type="text" wire:model="efEditField.placeholder" placeholder="Platzhalter-Text (optional)"
                             class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                    </div>
                    <div class="flex flex-wrap gap-4">
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_required" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Pflichtfeld
                      </label>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_mandatory" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mandatory
                      </label>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_encrypted" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Verschlüsselt
                      </label>
                    </div>
                  </div>
                @endif

                {{-- Sub-tab: Optionen --}}
                @if($this->efEditFieldTab === 'options')
                  <div class="space-y-4 max-w-lg">
                    @if($this->efEditField['type'] === 'select')
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Optionen</label>
                        <div class="flex gap-2 mb-2">
                          <input type="text" wire:model="efEditOptionText" wire:keydown.enter="efAddEditOption" placeholder="Option hinzufügen"
                                 class="flex-1 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                          <button wire:click="efAddEditOption" class="px-3 py-2 text-sm bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/80 transition">+</button>
                        </div>
                        @if(!empty($this->efEditField['options']))
                          <div class="flex flex-wrap gap-1.5">
                            @foreach($this->efEditField['options'] as $i => $opt)
                              <span class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-[var(--ui-muted-5)] text-[var(--ui-text)] rounded-md border border-[var(--ui-border)]/40">
                                {{ $opt }}
                                <button wire:click="efRemoveEditOption({{ $i }})" class="text-[var(--ui-muted)] hover:text-red-500">&times;</button>
                              </span>
                            @endforeach
                          </div>
                        @endif
                        @error('efEditField.options') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                      </div>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_multiple" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mehrfachauswahl
                      </label>
                    @elseif($this->efEditField['type'] === 'lookup')
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Lookup</label>
                        <select wire:model="efEditField.lookup_id"
                                class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                          <option value="">Lookup wählen…</option>
                          @foreach($this->efLookups as $lu)
                            <option value="{{ $lu['id'] }}">{{ $lu['label'] }} ({{ $lu['values_count'] }})</option>
                          @endforeach
                        </select>
                        @error('efEditField.lookup_id') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                      </div>
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_multiple" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mehrfachauswahl
                      </label>
                    @elseif($this->efEditField['type'] === 'regex')
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Regex Pattern</label>
                        <input type="text" wire:model="efEditField.regex_pattern"
                               class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                        @error('efEditField.regex_pattern') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Pattern-Beschreibung</label>
                        <input type="text" wire:model="efEditField.regex_description" placeholder="z.B. Nur Buchstaben und Zahlen"
                               class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                      </div>
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Fehlermeldung</label>
                        <input type="text" wire:model="efEditField.regex_error" placeholder="Benutzerdefinierte Fehlermeldung"
                               class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                      </div>
                    @elseif($this->efEditField['type'] === 'file')
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.is_multiple" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        Mehrere Dateien
                      </label>
                    @else
                      <p class="text-sm text-[var(--ui-muted)]">Keine typ-spezifischen Optionen für diesen Feldtyp.</p>
                    @endif
                  </div>
                @endif

                {{-- Sub-tab: Bedingungen --}}
                @if($this->efEditFieldTab === 'conditions')
                  <div class="space-y-4 max-w-2xl">
                    <div class="flex items-center justify-between">
                      <label class="text-sm font-medium text-[var(--ui-text)]">Bedingte Sichtbarkeit</label>
                      <button wire:click="efToggleVisibilityEnabled"
                              class="px-3 py-1 text-xs rounded-md transition {{ ($this->efEditField['visibility']['enabled'] ?? false) ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">
                        {{ ($this->efEditField['visibility']['enabled'] ?? false) ? 'Aktiv' : 'Inaktiv' }}
                      </button>
                    </div>

                    @if($this->efEditField['visibility']['enabled'] ?? false)
                      <p class="text-xs text-[var(--ui-muted)] italic">{{ $this->efVisibilityDescription() }}</p>
                      @error('efEditField.visibility') <span class="text-xs text-red-500">{{ $message }}</span> @enderror

                      {{-- Main logic toggle --}}
                      <div class="flex items-center gap-2">
                        <span class="text-xs text-[var(--ui-muted)]">Gruppen-Logik:</span>
                        <button wire:click="efSetVisibilityLogic('AND')"
                                class="px-2 py-0.5 text-[10px] rounded transition {{ ($this->efEditField['visibility']['logic'] ?? 'AND') === 'AND' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">AND</button>
                        <button wire:click="efSetVisibilityLogic('OR')"
                                class="px-2 py-0.5 text-[10px] rounded transition {{ ($this->efEditField['visibility']['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-primary)] text-white' : 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]' }}">OR</button>
                      </div>

                      {{-- Condition groups --}}
                      @foreach($this->efEditField['visibility']['groups'] ?? [] as $gi => $group)
                        <div class="border border-[var(--ui-border)]/40 rounded-lg p-3 space-y-2">
                          <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                              <span class="text-[10px] font-semibold text-[var(--ui-muted)] uppercase">Gruppe {{ $gi + 1 }}</span>
                              <button wire:click="efSetGroupLogic({{ $gi }}, 'AND')"
                                      class="px-1.5 py-0.5 text-[9px] rounded transition {{ ($group['logic'] ?? 'AND') === 'AND' ? 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }}">AND</button>
                              <button wire:click="efSetGroupLogic({{ $gi }}, 'OR')"
                                      class="px-1.5 py-0.5 text-[9px] rounded transition {{ ($group['logic'] ?? 'AND') === 'OR' ? 'bg-[var(--ui-primary)]/20 text-[var(--ui-primary)]' : 'text-[var(--ui-muted)]' }}">OR</button>
                            </div>
                            <button wire:click="efRemoveConditionGroup({{ $gi }})" class="text-[var(--ui-muted)] hover:text-red-500 transition">
                              <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                            </button>
                          </div>

                          @foreach($group['conditions'] ?? [] as $ci => $condition)
                            <div class="flex items-start gap-1.5">
                              {{-- Field select --}}
                              <select wire:change="efUpdateConditionField({{ $gi }}, {{ $ci }}, $event.target.value)"
                                      class="flex-1 px-2 py-1.5 text-[11px] border border-[var(--ui-border)] rounded bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]/30">
                                <option value="">Feld…</option>
                                @foreach($this->efConditionFields() as $cf)
                                  <option value="{{ $cf['name'] }}" {{ ($condition['field'] ?? '') === $cf['name'] ? 'selected' : '' }}>{{ $cf['label'] }}</option>
                                @endforeach
                              </select>

                              {{-- Operator select --}}
                              @if(!empty($condition['field']))
                                @php $ops = $this->efGetOperatorsForField($condition['field']); @endphp
                                <select wire:change="efUpdateConditionOperator({{ $gi }}, {{ $ci }}, $event.target.value)"
                                        class="w-28 px-2 py-1.5 text-[11px] border border-[var(--ui-border)] rounded bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]/30">
                                  @foreach($ops as $opKey => $opLabel)
                                    <option value="{{ $opKey }}" {{ ($condition['operator'] ?? '') === $opKey ? 'selected' : '' }}>{{ $opLabel }}</option>
                                  @endforeach
                                </select>
                              @endif

                              {{-- Value input --}}
                              @if(!empty($condition['field']) && !empty($condition['operator']))
                                @php
                                  $opMeta = \Platform\Core\Services\ExtraFieldConditionEvaluator::OPERATORS[$condition['operator']] ?? null;
                                  $requiresValue = $opMeta['requiresValue'] ?? true;
                                @endphp
                                @if($requiresValue && !in_array($condition['operator'], ['is_in', 'is_not_in']))
                                  <input type="text" wire:change="efUpdateConditionValue({{ $gi }}, {{ $ci }}, $event.target.value)"
                                         value="{{ $condition['value'] ?? '' }}"
                                         placeholder="Wert"
                                         class="w-32 px-2 py-1.5 text-[11px] border border-[var(--ui-border)] rounded bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-1 focus:ring-[var(--ui-primary)]/30" />
                                @endif
                              @endif

                              <button wire:click="efRemoveCondition({{ $gi }}, {{ $ci }})" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition flex-shrink-0">
                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                              </button>
                            </div>
                          @endforeach

                          <button wire:click="efAddCondition({{ $gi }})"
                                  class="text-[10px] text-[var(--ui-primary)] hover:underline">+ Bedingung</button>
                        </div>
                      @endforeach

                      <button wire:click="efAddConditionGroup"
                              class="text-xs text-[var(--ui-primary)] hover:underline">+ Gruppe hinzufügen</button>
                    @endif
                  </div>
                @endif

                {{-- Sub-tab: Verifizierung --}}
                @if($this->efEditFieldTab === 'verification')
                  <div class="space-y-4 max-w-lg">
                    @if($this->efEditField['type'] === 'file')
                      <label class="flex items-center gap-2 text-sm text-[var(--ui-text)] cursor-pointer">
                        <input type="checkbox" wire:model="efEditField.verify_by_llm" class="rounded border-[var(--ui-border)] text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]" />
                        LLM-Verifizierung aktivieren
                      </label>
                      @if($this->efEditField['verify_by_llm'])
                        <div>
                          <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Verifizierungs-Anweisungen</label>
                          <textarea wire:model="efEditField.verify_instructions" rows="4"
                                    placeholder="Anweisungen für die LLM-Verifizierung…"
                                    class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 resize-none"></textarea>
                        </div>
                      @endif
                    @else
                      <p class="text-sm text-[var(--ui-muted)]">LLM-Verifizierung ist nur für Datei-Felder verfügbar.</p>
                    @endif
                  </div>
                @endif

                {{-- Sub-tab: Autofill --}}
                @if($this->efEditFieldTab === 'autofill')
                  <div class="space-y-4 max-w-lg">
                    <div>
                      <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Autofill-Quelle</label>
                      <select wire:model.live="efEditField.auto_fill_source"
                              class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30">
                        <option value="">Kein Autofill</option>
                        @foreach($this->efAutoFillSources() as $srcKey => $srcLabel)
                          <option value="{{ $srcKey }}">{{ $srcLabel }}</option>
                        @endforeach
                      </select>
                    </div>
                    @if(!empty($this->efEditField['auto_fill_source']))
                      <div>
                        <label class="block text-xs font-medium text-[var(--ui-text)] mb-1">Autofill-Prompt</label>
                        <textarea wire:model="efEditField.auto_fill_prompt" rows="4"
                                  placeholder="Prompt für das Autofill…"
                                  class="w-full px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30 resize-none"></textarea>
                      </div>
                    @endif
                  </div>
                @endif

                {{-- Action buttons --}}
                <div class="flex items-center gap-2 mt-6 pt-4 border-t border-[var(--ui-border)]/40">
                  <button wire:click="efSaveEditDefinition"
                          class="px-4 py-2 text-sm font-medium bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/80 transition">
                    Speichern
                  </button>
                  <button wire:click="efCancelEditDefinition"
                          class="px-4 py-2 text-sm font-medium text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition">
                    Abbrechen
                  </button>
                </div>
              </div>

            {{-- Lookup Values Manager --}}
            @elseif($this->efSelectedLookupId && $this->efTab === 'lookups')
              @php $selectedLu = $this->efSelectedLookup(); @endphp
              <div class="flex-1 flex flex-col" :class="fullscreen ? 'p-6' : 'p-4'">
                @if($selectedLu)
                  <div class="flex items-center justify-between mb-4">
                    <div>
                      <h3 class="text-sm font-bold text-[var(--ui-text)]">{{ $selectedLu['label'] }}</h3>
                      @if($selectedLu['description'])<p class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $selectedLu['description'] }}</p>@endif
                    </div>
                    <button wire:click="efDeselectLookup" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition">Zurück</button>
                  </div>

                  {{-- Add value form --}}
                  <div class="flex gap-2 mb-4">
                    <input type="text" wire:model="efNewLookupValueLabel" placeholder="Label"
                           class="flex-1 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                    <input type="text" wire:model="efNewLookupValueText" placeholder="Wert (optional)"
                           class="w-32 px-3 py-2 text-sm border border-[var(--ui-border)] rounded-lg bg-[var(--ui-surface)] text-[var(--ui-text)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/30" />
                    <button wire:click="efAddLookupValue"
                            class="px-4 py-2 text-sm font-medium bg-[var(--ui-primary)] text-white rounded-lg hover:bg-[var(--ui-primary)]/80 transition">
                      Hinzufügen
                    </button>
                  </div>
                  @error('efNewLookupValueText') <span class="text-xs text-red-500 mb-2 block">{{ $message }}</span> @enderror

                  {{-- Values list --}}
                  <div class="space-y-1">
                    @forelse($this->efLookupValues as $lv)
                      <div class="group flex items-center gap-2 px-3 py-2 rounded-lg border border-[var(--ui-border)]/40 hover:border-[var(--ui-border)] transition">
                        <div class="min-w-0 flex-1">
                          <span class="text-sm text-[var(--ui-text)]">{{ $lv['label'] }}</span>
                          @if($lv['value'] !== $lv['label'])
                            <span class="text-xs text-[var(--ui-muted)] ml-1">({{ $lv['value'] }})</span>
                          @endif
                        </div>
                        <button wire:click="efToggleLookupValue({{ $lv['id'] }})"
                                class="px-2 py-0.5 text-[10px] rounded transition {{ $lv['is_active'] ? 'bg-emerald-500/15 text-emerald-600' : 'bg-red-500/10 text-red-400' }}">
                          {{ $lv['is_active'] ? 'Aktiv' : 'Inaktiv' }}
                        </button>
                        <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition">
                          <button wire:click="efMoveLookupValueUp({{ $lv['id'] }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition" title="Nach oben">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 01-1.06-.02L10 8.832 6.29 12.77a.75.75 0 11-1.08-1.04l4.25-4.5a.75.75 0 011.08 0l4.25 4.5a.75.75 0 01-.02 1.06z" clip-rule="evenodd"/></svg>
                          </button>
                          <button wire:click="efMoveLookupValueDown({{ $lv['id'] }})" class="p-1 text-[var(--ui-muted)] hover:text-[var(--ui-text)] transition" title="Nach unten">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                          </button>
                          <button wire:click="efDeleteLookupValue({{ $lv['id'] }})" class="p-1 text-[var(--ui-muted)] hover:text-red-500 transition" title="Löschen">
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                          </button>
                        </div>
                      </div>
                    @empty
                      <p class="text-sm text-[var(--ui-muted)] text-center py-6">Keine Werte vorhanden</p>
                    @endforelse
                  </div>
                @endif
              </div>

            {{-- Placeholder --}}
            @else
              <div class="flex-1 flex items-center justify-center">
                <div class="text-center py-12">
                  <div class="text-3xl opacity-20 mb-3">
                    @if($this->efTab === 'fields')
                      📋
                    @else
                      📖
                    @endif
                  </div>
                  <p class="text-sm font-medium text-[var(--ui-text)]">
                    @if($this->efTab === 'fields')
                      Feld auswählen zum Bearbeiten
                    @else
                      Lookup auswählen für Werte-Verwaltung
                    @endif
                  </p>
                  <p class="text-xs text-[var(--ui-muted)] mt-1 max-w-[200px] mx-auto">
                    @if($this->efTab === 'fields')
                      Wähle ein Feld aus der Sidebar oder erstelle ein neues.
                    @else
                      Klicke auf einen Lookup in der Sidebar um dessen Werte zu verwalten.
                    @endif
                  </p>
                </div>
              </div>
            @endif

        </div>

    </div>
</div>
