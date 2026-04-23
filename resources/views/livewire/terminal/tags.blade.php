<div class="flex-1 min-h-0 flex flex-col"
     x-data="{ tagSearch: '', personalMode: false }">
  <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
    <div class="py-4 space-y-5 px-4">

      @if($this->contextType && $this->contextId)
        {{-- ── Section A: Assigned Tags ── --}}
        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold text-[var(--t-text)]">Zugeordnet</h4>
            <button
              @click="personalMode = !personalMode"
              class="flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full transition"
              :class="personalMode ? 'bg-purple-500/15 text-purple-400' : 'bg-[var(--t-accent)]/15 text-[var(--t-accent)]'"
            >
              <svg x-show="!personalMode" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 18a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z"/></svg>
              <svg x-show="personalMode" class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
              <span x-text="personalMode ? 'Persönlich' : 'Team'"></span>
            </button>
          </div>

          <div class="flex flex-wrap gap-1.5">
            @foreach($teamTags as $tag)
              <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium transition group"
                    style="background-color: {{ $tag['color'] ? $tag['color'] . '20' : 'var(--t-accent)' . '20' }}; border: 1px solid {{ $tag['color'] ? $tag['color'] . '40' : 'var(--t-accent)' . '40' }}; color: {{ $tag['color'] ?: 'var(--t-accent)' }}">
                @if($tag['color'])
                  <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $tag['color'] }}"></span>
                @endif
                {{ $tag['label'] }}
                <span class="text-[8px] opacity-60">T</span>
                <button wire:click="toggleTag({{ $tag['id'] }}, false)"
                        class="ml-0.5 opacity-40 hover:opacity-100 transition">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
              </span>
            @endforeach
            @foreach($personalTags as $tag)
              <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium transition group"
                    style="background-color: {{ $tag['color'] ? $tag['color'] . '20' : 'var(--t-accent)' . '20' }}; border: 1px solid {{ $tag['color'] ? $tag['color'] . '40' : 'var(--t-accent)' . '40' }}; color: {{ $tag['color'] ?: 'var(--t-accent)' }}">
                @if($tag['color'])
                  <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $tag['color'] }}"></span>
                @endif
                {{ $tag['label'] }}
                <span class="text-[8px] opacity-60">P</span>
                <button wire:click="toggleTag({{ $tag['id'] }}, true)"
                        class="ml-0.5 opacity-40 hover:opacity-100 transition">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                </button>
              </span>
            @endforeach
            @if(empty($teamTags) && empty($personalTags))
              <span class="text-[11px] text-[var(--t-text-muted)]">Noch keine Tags zugeordnet.</span>
            @endif
          </div>
        </div>

        {{-- ── Section B: Available Tags ── --}}
        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold text-[var(--t-text)]">Verfügbar</h4>
            <div class="relative flex-1 max-w-[180px] ml-3">
              <svg class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
              <input type="text"
                     x-model="tagSearch"
                     placeholder="Filtern..."
                     class="w-full pl-7 pr-2 py-1 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent" />
            </div>
          </div>

          <div class="flex flex-wrap gap-1.5">
            @forelse($availableTags as $tag)
              <button
                x-show="!tagSearch || {{ Js::from(strtolower($tag['label'])) }}.includes(tagSearch.toLowerCase())"
                @click="$wire.toggleTag({{ $tag['id'] }}, personalMode)"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium border border-[var(--t-border)]/40 text-[var(--t-text)] hover:bg-white/[0.06] transition"
              >
                @if($tag['color'])
                  <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] }}"></span>
                @endif
                {{ $tag['label'] }}
              </button>
            @empty
              <span class="text-[11px] text-[var(--t-text-muted)]">Keine weiteren Tags verfügbar.</span>
            @endforelse
          </div>
        </div>

        {{-- ── Section C: Color Palette ── --}}
        <div>
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-xs font-semibold text-[var(--t-text)]">Farbe</h4>
            @if($contextColor)
              <button wire:click="removeColor" class="text-[10px] text-red-400 hover:text-red-400/80 transition">Entfernen</button>
            @endif
          </div>

          @php
            $colorPresets = [
              '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4',
              '#3b82f6', '#8b5cf6', '#ec4899', '#6b7280', '#1e293b',
            ];
          @endphp

          <div class="flex flex-wrap gap-2 items-center">
            @foreach($colorPresets as $preset)
              <button
                wire:click="setColorPreset('{{ $preset }}')"
                class="w-6 h-6 rounded-full border-2 transition-all hover:scale-110 flex items-center justify-center {{ $contextColor === $preset ? 'ring-2 ring-offset-1 ring-[var(--t-accent)] border-white/30' : 'border-transparent hover:border-white/20' }}"
                style="background-color: {{ $preset }}"
                title="{{ $preset }}"
              >
                @if($contextColor === $preset)
                  <svg class="w-3 h-3 text-white drop-shadow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                @endif
              </button>
            @endforeach

            {{-- Custom color picker --}}
            <div class="relative" x-data="{ customColor: '{{ $contextColor ?? '#6b7280' }}' }">
              <input type="color"
                     x-model="customColor"
                     @change="$wire.setColorPreset(customColor)"
                     class="w-6 h-6 rounded-full cursor-pointer border border-[var(--t-border)]/40 p-0 overflow-hidden appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch]:rounded-full" />
            </div>

            @if($contextColor)
              <span class="text-[10px] text-[var(--t-text-muted)] font-mono ml-1">{{ $contextColor }}</span>
            @endif
          </div>
        </div>

        {{-- ── Section D: Create New Tag ── --}}
        <div>
          <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Neues Tag erstellen</h4>
          <div class="flex items-center gap-2">
            <input type="text"
                   wire:model="tagInput"
                   placeholder="Name eingeben..."
                   class="flex-1 px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent"
                   @keydown.enter="$wire.set('newTagIsPersonal', personalMode).then(() => $wire.createAndAddTag())" />
            <input type="color"
                   wire:model="newTagColor"
                   class="w-7 h-7 rounded cursor-pointer border border-[var(--t-border)]/40 p-0 overflow-hidden appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch]:rounded"
                   title="Farbe wählen" />
            <button @click="$wire.set('newTagIsPersonal', personalMode).then(() => $wire.createAndAddTag())"
                    class="px-3 py-1.5 bg-[var(--t-accent)] text-white rounded-md text-[11px] font-medium hover:bg-[var(--t-accent)]/90 transition whitespace-nowrap">
              Erstellen
            </button>
          </div>
        </div>

      @else
        {{-- No context — show overview of all tags + colors --}}
        <div class="space-y-6">
          <div>
            <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Alle Tags</h4>
            <div class="space-y-1">
              @forelse($allTags as $tag)
                <div class="flex items-center justify-between p-2 rounded-md hover:bg-white/[0.03] transition">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $tag['color'] ?? 'var(--t-text-muted)' }}"></div>
                    <span class="text-xs font-medium text-[var(--t-text)] truncate">{{ $tag['label'] }}</span>
                    <span class="text-[9px] text-[var(--t-text-muted)] px-1 py-px bg-[var(--t-text-muted)]/5 rounded flex-shrink-0">{{ $tag['is_team_tag'] ? 'Team' : 'Global' }}</span>
                  </div>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $tag['total_count'] }}x</span>
                    @if($tag['total_count'] === 0)
                      <button
                        wire:click="deleteTag({{ $tag['id'] }})"
                        wire:confirm="Tag wirklich löschen?"
                        class="text-[9px] text-red-400 hover:text-red-400/80 px-1 py-0.5 rounded hover:bg-red-400/5 transition"
                      >Löschen</button>
                    @endif
                  </div>
                </div>
              @empty
                <div class="py-4 text-center">
                  <p class="text-xs text-[var(--t-text-muted)]">Noch keine Tags vorhanden.</p>
                </div>
              @endforelse
            </div>
          </div>

          <div>
            <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Alle Farben</h4>
            <div class="space-y-1">
              @forelse($allColors as $color)
                <div class="flex items-center justify-between p-2 rounded-md hover:bg-white/[0.03] transition">
                  <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-md border border-[var(--t-border)]/40" style="background-color: {{ $color['color'] }}"></div>
                    <span class="text-xs font-medium text-[var(--t-text)] font-mono">{{ $color['color'] }}</span>
                  </div>
                  <div class="flex items-center gap-3 text-[10px] text-[var(--t-text-muted)] tabular-nums">
                    <span>{{ $color['total_count'] }}x</span>
                    <span>T:{{ $color['team_count'] }}</span>
                    <span>P:{{ $color['personal_count'] }}</span>
                  </div>
                </div>
              @empty
                <div class="py-4 text-center">
                  <p class="text-xs text-[var(--t-text-muted)]">Noch keine Farben verwendet.</p>
                </div>
              @endforelse
            </div>
          </div>

          {{-- Create tag even without context --}}
          <div>
            <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Neues Tag erstellen</h4>
            <div class="flex items-center gap-2">
              <input type="text"
                     wire:model="tagInput"
                     placeholder="Name eingeben..."
                     class="flex-1 px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent"
                     @keydown.enter="$wire.createAndAddTag()" />
              <input type="color"
                     wire:model="newTagColor"
                     class="w-7 h-7 rounded cursor-pointer border border-[var(--t-border)]/40 p-0 overflow-hidden appearance-none [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:border-none [&::-webkit-color-swatch]:rounded"
                     title="Farbe wählen" />
              <button wire:click="createAndAddTag"
                      class="px-3 py-1.5 bg-[var(--t-accent)] text-white rounded-md text-[11px] font-medium hover:bg-[var(--t-accent)]/90 transition whitespace-nowrap">
                Erstellen
              </button>
            </div>
          </div>
        </div>
      @endif

    </div>
  </div>
</div>
