<div class="flex-1 min-h-0 flex flex-col">
  <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
    <div class="py-4 space-y-5 px-4">

      @if(!$devAvailable)
        <div class="py-8 text-center">
          <p class="text-xs text-[var(--t-text-muted)]">Das Dev-Modul ist nicht verfügbar.</p>
        </div>
      @else

        {{-- ── Target package: bound to the current module. No picker in the
             normal case; if the module isn't mapped yet, pick once — it's
             remembered. ── --}}
        @php($selectedPackage = collect($packageOptions)->firstWhere('id', $packageId))
        @if($selectedPackage && !$needsMapping)
          <div class="flex items-center gap-1.5 text-[11px] text-[var(--t-text-muted)]">
            <svg class="w-3.5 h-3.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
            <span>Package: <span class="text-[var(--t-text)] font-medium">{{ $selectedPackage['name'] }}</span></span>
            @if($autoResolved)
              <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-[var(--t-accent)]/15 text-[var(--t-accent)]">{{ $moduleKey ? 'Modul ' . $moduleKey : 'aktuell' }}</span>
            @endif
            <button type="button" wire:click="changePackage"
                    class="ml-auto text-[10px] text-[var(--t-text-muted)] hover:text-[var(--t-text)] underline decoration-dotted">
              ändern
            </button>
          </div>
        @elseif($needsMapping)
          <div>
            <h4 class="text-xs font-semibold text-[var(--t-text)] mb-1">Package für dieses Modul</h4>
            <p class="text-[11px] text-[var(--t-text-muted)] mb-2">
              Modul <span class="text-[var(--t-text)] font-medium">{{ $moduleKey }}</span> ist noch keinem Dev-Package zugeordnet. Einmal wählen — wird gemerkt.
            </p>
            @if(empty($packageOptions))
              <p class="text-[11px] text-[var(--t-text-muted)]">Keine Dev-Packages in diesem Team. Erst eins im Dev-Modul anlegen.</p>
            @else
              <div class="flex flex-wrap gap-1.5">
                @foreach($packageOptions as $opt)
                  <button type="button" wire:click="chooseAndMap({{ $opt['id'] }})"
                          class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-medium border border-[var(--t-border)]/40 text-[var(--t-text)] hover:bg-white/[0.06] transition">
                    {{ $opt['name'] }}
                  </button>
                @endforeach
              </div>
            @endif
          </div>
        @else
          {{-- No module context — plain one-off picker (not persisted). --}}
          <div>
            <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Package</h4>
            @if(empty($packageOptions))
              <p class="text-[11px] text-[var(--t-text-muted)]">Keine Dev-Packages in diesem Team.</p>
            @else
              <select wire:model="packageId"
                      class="w-full px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent">
                <option value="">Package wählen…</option>
                @foreach($packageOptions as $opt)
                  <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                @endforeach
              </select>
              @error('packageId')<p class="mt-1 text-[10px] text-red-400">{{ $message }}</p>@enderror
            @endif
          </div>
        @endif

        {{-- ── Title ── --}}
        <div>
          <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Titel</h4>
          <input type="text"
                 wire:model="title"
                 placeholder="Was soll gebaut werden?"
                 class="w-full px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent" />
          @error('title')<p class="mt-1 text-[10px] text-red-400">{{ $message }}</p>@enderror
        </div>

        {{-- ── Description ── --}}
        <div>
          <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Beschreibung</h4>
          <textarea wire:model="description"
                    rows="4"
                    placeholder="Kontext, Ziel, Nutzen… (optional)"
                    class="w-full px-3 py-1.5 text-[11px] border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] focus:border-transparent resize-none"></textarea>
          @error('description')<p class="mt-1 text-[10px] text-red-400">{{ $message }}</p>@enderror
        </div>

        {{-- ── Priority ── --}}
        <div>
          <h4 class="text-xs font-semibold text-[var(--t-text)] mb-2">Priorität</h4>
          <div class="flex items-center gap-1.5">
            @foreach(['low' => 'Niedrig', 'normal' => 'Normal', 'high' => 'Hoch'] as $value => $label)
              <button type="button"
                      wire:click="$set('priority', '{{ $value }}')"
                      class="px-3 py-1 rounded-full text-[11px] font-medium border transition {{ $priority === $value ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] border-[var(--t-accent)]/40' : 'border-[var(--t-border)]/40 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/[0.06]' }}">
                {{ $label }}
              </button>
            @endforeach
          </div>
        </div>

        {{-- ── Context toggle ── --}}
        @if($contextType && $contextId)
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" wire:model="attachContext"
                   class="rounded border-[var(--t-border)]/40 bg-[var(--t-glass-surface)] text-[var(--t-accent)] focus:ring-[var(--t-accent)]" />
            <span class="text-[11px] text-[var(--t-text-muted)]">
              Aktuellen Kontext anhängen{{ $contextSubject ? ' · ' . \Illuminate\Support\Str::limit($contextSubject, 40) : '' }}
            </span>
          </label>
        @endif

        {{-- ── Submit ── --}}
        <div class="pt-1">
          <button type="button"
                  wire:click="submit"
                  wire:loading.attr="disabled"
                  @disabled(empty($packageOptions))
                  class="w-full px-3 py-2 bg-[var(--t-accent)] text-white rounded-md text-[11px] font-medium hover:bg-[var(--t-accent)]/90 transition disabled:opacity-40 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="submit">Feature Request senden</span>
            <span wire:loading wire:target="submit">Senden…</span>
          </button>
        </div>

      @endif

    </div>
  </div>
</div>
