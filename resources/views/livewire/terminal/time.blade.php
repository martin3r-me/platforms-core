<div class="flex-1 min-h-0 flex flex-col">
  <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
    <div class="py-4 space-y-4 px-4">

      @if($this->contextType && $this->contextId)
        @php
          $tStats = $this->timeStats;
          $tPlanned = $this->timePlannedEntries;
          $tEntries = $this->timeEntries;
          $tUsers = $this->timeAvailableUsers;
          $tTotalPlanned = $tStats['totalPlannedMinutes'];
          $tTotalMin = $tStats['totalMinutes'];
          $tBilledMin = $tStats['billedMinutes'];
          $tUnbilledMin = $tStats['unbilledMinutes'];
          $tUnbilledCents = $tStats['unbilledAmountCents'];
        @endphp

        {{-- Budget bar --}}
        @if($tTotalPlanned)
          @php
            $budgetPct = $tTotalPlanned > 0 ? min(100, round(($tTotalMin / $tTotalPlanned) * 100)) : 0;
            $budgetColor = $budgetPct >= 100 ? '#ef4444' : ($budgetPct >= 80 ? '#f59e0b' : '#10b981');
          @endphp
          <div class="p-3 rounded-lg border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)]">
            <div class="flex items-center justify-between text-[11px] mb-1.5">
              <span class="text-[var(--t-text-muted)]">Budget</span>
              <span class="font-medium text-[var(--t-text)]">{{ number_format($tTotalMin / 60, 1, ',', '.') }}h / {{ number_format($tTotalPlanned / 60, 1, ',', '.') }}h</span>
            </div>
            <div class="h-2 rounded-full bg-[var(--t-border)]/30 overflow-hidden">
              <div class="h-full rounded-full transition-all" style="width: {{ $budgetPct }}%; background-color: {{ $budgetColor }}"></div>
            </div>
            <div class="text-[10px] text-[var(--t-text-muted)] mt-1 text-right">{{ $budgetPct }}%</div>
          </div>
        @endif

        {{-- Time entry form --}}
        <div class="p-3 rounded-lg border border-[var(--t-border)]/40 bg-[var(--t-glass-surface)]" x-data="{ showBudget: false }">
          <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)] mb-2">Zeit erfassen</h3>

          {{-- Date --}}
          <div class="mb-2">
            <input type="date"
                   wire:model="timeWorkDate"
                   class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] [color-scheme:dark]" />
          </div>

          {{-- Rate --}}
          <div class="mb-2">
            <input type="text"
                   wire:model="timeRate"
                   placeholder="Stundensatz (optional, z.B. 120)"
                   class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
            @error('timeRate') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
          </div>

          {{-- Duration badges --}}
          <div class="mb-2">
            <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Dauer</label>
            <div class="flex flex-wrap gap-1">
              @foreach([15 => '15m', 30 => '30m', 45 => '45m', 60 => '1h', 90 => '1.5h', 120 => '2h', 180 => '3h', 240 => '4h', 360 => '6h', 480 => '8h'] as $mins => $label)
                <button wire:click="$set('timeMinutes', {{ $mins }})"
                        class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timeMinutes === $mins ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5' }}">
                  {{ $label }}
                </button>
              @endforeach
            </div>
          </div>

          {{-- Note --}}
          <div class="mb-2">
            <textarea wire:model="timeNote"
                      rows="2"
                      placeholder="Notiz (optional)"
                      class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] resize-none"></textarea>
          </div>

          {{-- Amount preview --}}
          @if($this->timeRate && $this->timeMinutes)
            @php
              $previewRateCents = null;
              $normalizedRate = str_replace([' ', "'", ','], ['', '', '.'], $this->timeRate);
              if (is_numeric($normalizedRate) && (float)$normalizedRate > 0) {
                $previewRateCents = (int) round((float)$normalizedRate * 100);
              }
              $previewAmount = $previewRateCents ? round($previewRateCents * ($this->timeMinutes / 60)) / 100 : null;
            @endphp
            @if($previewAmount)
              <div class="mb-2 px-2.5 py-1.5 rounded-md bg-white/[0.03] border border-[var(--t-border)]/30 text-[10px] text-[var(--t-text-muted)]">
                Betrag: <span class="text-[var(--t-text)] font-medium">{{ number_format($previewAmount, 2, ',', '.') }} €</span>
              </div>
            @endif
          @endif

          {{-- Submit button --}}
          <button wire:click="saveTimeEntry"
                  class="w-full px-3 py-2 rounded-md text-[11px] font-semibold transition bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 mb-3">
            Zeit erfassen
          </button>

          {{-- Budget section (collapsible) --}}
          <div>
            <button @click="showBudget = !showBudget"
                    class="flex items-center gap-1.5 w-full text-left">
              <svg class="w-3 h-3 text-[var(--t-text-muted)] transition-transform" :class="showBudget && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
              <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]">Budget</h3>
            </button>

            <div x-show="showBudget" x-collapse class="mt-2 space-y-2">
              {{-- Hours badges --}}
              <div>
                <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Stunden</label>
                <div class="flex flex-wrap gap-1">
                  @foreach([60 => '1h', 120 => '2h', 180 => '3h', 240 => '4h', 300 => '5h', 360 => '6h', 420 => '7h', 480 => '8h'] as $mins => $label)
                    <button wire:click="$set('timePlannedMinutes', {{ $mins }})"
                            class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timePlannedMinutes === $mins ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5' }}">
                      {{ $label }}
                    </button>
                  @endforeach
                </div>
              </div>

              {{-- Days badges --}}
              <div>
                <label class="text-[10px] text-[var(--t-text-muted)] mb-1 block">Tage (à 8h)</label>
                <div class="flex flex-wrap gap-1">
                  @foreach([480 => '1d', 960 => '2d', 2400 => '5d', 4800 => '10d', 9600 => '20d'] as $mins => $label)
                    <button wire:click="$set('timePlannedMinutes', {{ $mins }})"
                            class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timePlannedMinutes === $mins ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/60 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5' }}">
                      {{ $label }}
                    </button>
                  @endforeach
                </div>
              </div>

              {{-- Manual minutes --}}
              <div>
                <input type="number"
                       wire:model="timePlannedMinutes"
                       placeholder="Minuten manuell"
                       min="1"
                       class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
                @error('timePlannedMinutes') <span class="text-[10px] text-red-400 mt-0.5">{{ $message }}</span> @enderror
              </div>

              {{-- Budget note --}}
              <div>
                <input type="text"
                       wire:model="timePlannedNote"
                       placeholder="Budget-Notiz (optional)"
                       class="w-full px-2.5 py-1.5 text-[11px] bg-transparent border border-[var(--t-border)]/60 text-[var(--t-text)] placeholder-[var(--t-text-muted)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
              </div>

              {{-- Budget submit --}}
              <button wire:click="saveTimePlanned"
                      @if(!$this->timePlannedMinutes) disabled @endif
                      class="w-full px-3 py-1.5 rounded-md text-[11px] font-semibold transition {{ $this->timePlannedMinutes ? 'bg-emerald-600 text-white hover:bg-emerald-500' : 'bg-[var(--t-text-muted)]/20 text-[var(--t-text-muted)] cursor-not-allowed' }}">
                Budget hinzufügen
              </button>
            </div>
          </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex gap-1">
            @foreach(['all' => 'Alle', 'current_week' => 'Woche', 'current_month' => 'Monat', 'current_year' => 'Jahr'] as $range => $label)
              <button wire:click="$set('timeOverviewRange', '{{ $range }}')"
                      class="px-2 py-0.5 rounded text-[10px] font-medium transition border {{ $this->timeOverviewRange === $range ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'border-[var(--t-border)]/40 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-[var(--t-glass-surface)]' }}">
                {{ $label }}
              </button>
            @endforeach
          </div>

          @if(count($tUsers) > 1)
            <select wire:model.live="timeSelectedUserId"
                    class="px-2 py-0.5 text-[10px] bg-[var(--t-glass-surface)] border border-[var(--t-border)]/40 text-[var(--t-text)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
              <option value="">Alle Personen</option>
              @foreach($tUsers as $u)
                <option value="{{ $u['id'] }}">{{ $u['name'] }}</option>
              @endforeach
            </select>
          @endif
        </div>

        {{-- Stats tiles --}}
        <div class="grid grid-cols-4 gap-2">
          <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
            <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Gesamt</div>
            <div class="text-sm font-bold text-[var(--t-text)] tabular-nums">{{ number_format($tTotalMin / 60, 1, ',', '.') }}h</div>
          </div>
          <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
            <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Abgerechnet</div>
            <div class="text-sm font-bold text-emerald-500 tabular-nums">{{ number_format($tBilledMin / 60, 1, ',', '.') }}h</div>
          </div>
          <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
            <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Offen</div>
            <div class="text-sm font-bold text-amber-500 tabular-nums">{{ number_format($tUnbilledMin / 60, 1, ',', '.') }}h</div>
          </div>
          <div class="p-2 rounded-lg border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)]">
            <div class="text-[10px] text-[var(--t-text-muted)] mb-0.5">Offen €</div>
            <div class="text-sm font-bold text-amber-500 tabular-nums">{{ number_format($tUnbilledCents / 100, 2, ',', '.') }}</div>
          </div>
        </div>

        {{-- Budget entries (collapsible) --}}
        @if(!empty($tPlanned))
          <div x-data="{ budgetOpen: false }">
            <button @click="budgetOpen = !budgetOpen" class="flex items-center gap-1.5 text-[11px] font-semibold text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition mb-1">
              <svg class="w-3 h-3 transition-transform" :class="budgetOpen && 'rotate-90'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
              Budgets ({{ count($tPlanned) }})
            </button>
            <div x-show="budgetOpen" x-collapse class="space-y-1">
              @foreach($tPlanned as $pe)
                <div class="flex items-center justify-between px-3 py-1.5 rounded-md border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)] text-[11px]">
                  <div class="flex items-center gap-2 min-w-0">
                    <span class="font-medium text-[var(--t-text)] tabular-nums">{{ number_format($pe['planned_minutes'] / 60, 1, ',', '.') }}h</span>
                    @if($pe['note'])
                      <span class="text-[var(--t-text-muted)] truncate">{{ $pe['note'] }}</span>
                    @endif
                    <span class="text-[9px] text-[var(--t-text-muted)]">{{ $pe['user_name'] }} · {{ $pe['created_at'] }}</span>
                  </div>
                  <button wire:click="deleteTimePlanned({{ $pe['id'] }})"
                          wire:confirm="Budget-Eintrag deaktivieren?"
                          class="flex-shrink-0 p-1 rounded hover:bg-red-500/10 text-[var(--t-text-muted)] hover:text-red-400 transition">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                  </button>
                </div>
              @endforeach
            </div>
          </div>
        @endif

        {{-- Entries table --}}
        <div>
          <h4 class="text-[11px] font-semibold text-[var(--t-text)] mb-2">Einträge ({{ count($tEntries) }})</h4>
          @if(!empty($tEntries))
            <div class="space-y-1">
              @foreach($tEntries as $entry)
                <div class="flex items-center gap-2 px-3 py-2 rounded-md border border-[var(--t-border)]/30 bg-[var(--t-glass-surface)] text-[11px] group">
                  {{-- Date --}}
                  <span class="text-[var(--t-text-muted)] tabular-nums w-16 flex-shrink-0">{{ $entry['work_date'] }}</span>

                  {{-- Duration --}}
                  <span class="font-medium text-[var(--t-text)] tabular-nums w-10 flex-shrink-0">{{ number_format($entry['minutes'] / 60, 1, ',', '.') }}h</span>

                  {{-- Amount --}}
                  <span class="text-[var(--t-text-muted)] tabular-nums w-14 flex-shrink-0 text-right">
                    @if($entry['amount_cents'])
                      {{ number_format($entry['amount_cents'] / 100, 2, ',', '.') }}€
                    @else
                      –
                    @endif
                  </span>

                  {{-- User --}}
                  <span class="text-[var(--t-text-muted)] truncate flex-1 min-w-0">
                    {{ $entry['user_name'] }}
                    @if($entry['note'])
                      <span class="text-[var(--t-text-muted)]/60"> · {{ Str::limit($entry['note'], 30) }}</span>
                    @endif
                  </span>

                  {{-- Billed status toggle --}}
                  <button wire:click="toggleTimeBilled({{ $entry['id'] }})"
                          class="flex-shrink-0 px-1.5 py-0.5 rounded text-[9px] font-medium transition border {{ $entry['is_billed'] ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/25' : 'bg-amber-500/10 border-amber-500/20 text-amber-400 hover:bg-amber-500/20' }}"
                          title="{{ $entry['is_billed'] ? 'Abgerechnet — klicken zum Ändern' : 'Offen — klicken zum Abrechnen' }}">
                    {{ $entry['is_billed'] ? '✓ Abgr.' : 'Offen' }}
                  </button>

                  {{-- Delete --}}
                  <button wire:click="deleteTimeEntry({{ $entry['id'] }})"
                          wire:confirm="Zeiteintrag löschen?"
                          class="flex-shrink-0 p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-red-500/10 text-[var(--t-text-muted)] hover:text-red-400 transition">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.519.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                  </button>
                </div>
              @endforeach
            </div>
          @else
            <div class="py-6 text-center">
              <div class="text-2xl opacity-20 mb-2">⏱️</div>
              <p class="text-[11px] text-[var(--t-text-muted)]">Noch keine Zeiteinträge vorhanden.</p>
            </div>
          @endif
        </div>

      @else
        <div class="py-8 text-center">
          <div class="text-3xl opacity-20 mb-3">⏱️</div>
          <p class="text-sm font-medium text-[var(--t-text)]">Kein Kontext ausgewählt</p>
          <p class="text-xs text-[var(--t-text-muted)] mt-1">Öffne ein Element um Zeiten zu erfassen.</p>
        </div>
      @endif

    </div>
  </div>
</div>
