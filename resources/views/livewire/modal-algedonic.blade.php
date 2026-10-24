{{--
    Algedonic-Signal — Schublade von rechts (gehört zur rechten Leiste, siehe context-panel).
    Desktop: 440px breit über dem Inhalt · Mobil: Vollbild.
    Geöffnet per Event `open-modal-algedonic` (optional { payload: { entity_id, entity_name } }).
--}}
<div
    x-data
    x-on:keydown.escape.window="if ($wire.modalShow) $wire.close()"
    x-init="$wire.$watch('modalShow', v => v && setTimeout(() => $refs.msg?.focus(), 220))"
>
    <style>
        .alg-backdrop { position: fixed; inset: 0; z-index: 59; background: rgba(15,15,15,.28); }
        .alg-drawer { position: fixed; top: 0; right: 0; z-index: 60; height: 100vh; height: 100dvh; width: min(440px, 100vw);
                      display: flex; flex-direction: column; background: var(--nx-surface); color: var(--nx-text);
                      box-shadow: -12px 0 40px -12px rgba(15,15,15,.25); }
        .alg-slide-enter { transition: transform .24s cubic-bezier(.2,.8,.2,1); }
        .alg-slide-leave { transition: transform .18s ease-in; }
        .alg-off { transform: translateX(100%); }
        .alg-on { transform: translateX(0); }
        .alg-fade-enter, .alg-fade-leave { transition: opacity .2s ease; }
        .alg-fade-off { opacity: 0; }
        .alg-fade-on { opacity: 1; }
        .alg-head { flex-shrink: 0; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--nx-line); }
        .alg-dot { position: relative; width: 28px; height: 28px; flex-shrink: 0; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
                   background: color-mix(in srgb, var(--nx-danger) 12%, transparent); color: var(--nx-danger); }
        .alg-body { flex: 1; min-height: 0; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 18px; }
        .alg-foot { flex-shrink: 0; padding: 12px 16px 16px; border-top: 1px solid var(--nx-line); background: var(--nx-bg); }
        .alg-label { display: block; font-size: 12px; font-weight: 600; color: var(--nx-text); margin-bottom: 6px; }
        .alg-hint { font-size: 12px; color: var(--nx-muted); }
        .alg-input { width: 100%; font-size: 14px; color: var(--nx-text); background: var(--nx-surface); border: 1px solid var(--nx-line-strong);
                     border-radius: var(--nx-radius); padding: 8px 10px; outline: none; transition: border-color .15s, box-shadow .15s; }
        .alg-input::placeholder { color: var(--nx-faint); }
        .alg-input:focus { border-color: var(--nx-danger); box-shadow: 0 0 0 3px color-mix(in srgb, var(--nx-danger) 15%, transparent); }
        .alg-chip { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: var(--nx-radius);
                    background: var(--nx-bg); border: 1px solid var(--nx-line); font-size: 14px; }
        .alg-menu { position: absolute; z-index: 5; left: 0; right: 0; margin-top: 4px; max-height: 240px; overflow-y: auto; background: var(--nx-elevated);
                    border: 1px solid var(--nx-line); border-radius: var(--nx-radius); box-shadow: var(--nx-shadow-pop); }
        .alg-opt { width: 100%; display: flex; align-items: center; gap: 8px; padding: 8px 10px; font-size: 14px; text-align: left; color: var(--nx-text); }
        .alg-opt:hover { background: var(--nx-hover); }
        .alg-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; color: var(--nx-muted); }
        .alg-icon-btn:hover { background: var(--nx-hover); color: var(--nx-text); }
        .alg-link { font-size: 12px; font-weight: 500; color: var(--nx-text); text-decoration: underline; text-underline-offset: 2px; text-decoration-color: var(--nx-line-strong); }
        .alg-when { display: grid; gap: 8px; font-size: 12px; }
        .alg-when > div { border-radius: var(--nx-radius); padding: 10px 12px; background: var(--nx-bg); }
        .alg-when ul { margin: 6px 0 0; padding-left: 16px; list-style: disc; display: grid; gap: 3px; color: var(--nx-muted); }
        .alg-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; font-size: 14px; }
        .alg-switch { position: relative; width: 32px; height: 18px; flex-shrink: 0; border-radius: 999px; background: var(--nx-line-strong); transition: background .15s; }
        .alg-switch::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 999px; background: #fff;
                             box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s; }
        .alg-toggle input:checked + .alg-switch { background: var(--nx-text); }
        .alg-toggle input:checked + .alg-switch::after { transform: translateX(14px); }
        .alg-route { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; line-height: 1.5; color: var(--nx-muted); margin-bottom: 12px; }
        .alg-route strong { color: var(--nx-text); font-weight: 600; }
        .alg-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 36px; padding: 0 14px; border-radius: var(--nx-radius);
                   font-size: 14px; font-weight: 500; transition: background .15s, color .15s, opacity .15s; }
        .alg-btn-ghost { color: var(--nx-muted); }
        .alg-btn-ghost:hover { background: var(--nx-hover); color: var(--nx-text); }
        .alg-btn-danger { background: var(--nx-danger); color: #fff; }
        .alg-btn-danger:hover { background: color-mix(in srgb, var(--nx-danger) 85%, #000); }
        .alg-btn-danger:disabled { opacity: .45; cursor: not-allowed; }
        .alg-error { margin-top: 4px; font-size: 12px; color: var(--nx-danger); }
        @media (prefers-reduced-motion: reduce) { .alg-slide-enter, .alg-slide-leave, .alg-fade-enter, .alg-fade-leave { transition: none; } }
    </style>

    <div x-show="$wire.modalShow" x-cloak x-on:click="$wire.close()" class="alg-backdrop"
        x-transition:enter="alg-fade-enter" x-transition:enter-start="alg-fade-off" x-transition:enter-end="alg-fade-on"
        x-transition:leave="alg-fade-leave" x-transition:leave-start="alg-fade-on" x-transition:leave-end="alg-fade-off"></div>

    <aside x-show="$wire.modalShow" x-cloak class="alg-drawer" role="dialog" aria-modal="true" aria-labelledby="alg-title"
        x-transition:enter="alg-slide-enter" x-transition:enter-start="alg-off" x-transition:enter-end="alg-on"
        x-transition:leave="alg-slide-leave" x-transition:leave-start="alg-on" x-transition:leave-end="alg-off">

        {{-- Kopf --}}
        <div class="alg-head">
            <span class="alg-dot">@svg('heroicon-o-bell-alert', 'w-4 h-4')</span>
            <div class="min-w-0 flex-1">
                <h2 id="alg-title" class="text-sm font-semibold m-0">Algedonic-Signal</h2>
                <div class="alg-hint">Direkt an die oberste Ebene</div>
            </div>
            <button type="button" wire:click="close" class="alg-icon-btn" title="Schließen">
                @svg('heroicon-o-x-mark', 'w-4 h-4')
            </button>
        </div>

        @if ($sent)
            {{-- Bestätigung --}}
            <div class="alg-body" style="align-items: center; justify-content: center; text-align: center">
                <span class="alg-dot" style="width: 48px; height: 48px; background: color-mix(in srgb, var(--nx-success) 12%, transparent); color: var(--nx-success)">
                    @svg('heroicon-o-check', 'w-6 h-6')
                </span>
                <div>
                    <div class="text-base font-semibold">Gesendet</div>
                    <p class="alg-hint mt-1">Das Signal ist auf dem Weg an die oberste Ebene.</p>
                    @if ($anonymous)
                        <p class="alg-hint mt-1">Anonym — deine Identität wurde nicht mitgesendet.</p>
                    @endif
                </div>
            </div>
            <div class="alg-foot flex justify-end">
                <button type="button" wire:click="close" class="alg-btn alg-btn-ghost">Schließen</button>
            </div>
        @else
            <div class="alg-body" x-data="{ showSuggestions: false, showHelp: false }">
                <p class="text-sm m-0" style="color: var(--nx-muted); line-height: 1.55">
                    Ein Signal, das alle Hierarchien überspringt (Stafford Beer). Für alles, was
                    <strong style="color: var(--nx-text)">jetzt</strong> Aufmerksamkeit von ganz oben braucht.
                    <button type="button" x-on:click="showHelp = !showHelp" class="alg-link ml-1" x-text="showHelp ? 'weniger' : 'Wann genau?'"></button>
                </p>

                <div x-show="showHelp" x-cloak x-transition.opacity class="alg-when">
                    <div style="background: color-mix(in srgb, var(--nx-danger) 7%, transparent)">
                        <strong style="color: var(--nx-danger)">Signal senden, wenn …</strong>
                        <ul>
                            <li>etwas schief läuft und die normalen Wege es nicht auffangen.</li>
                            <li>wir kurz davor sind, einen schwerwiegenden Fehler zu machen.</li>
                            <li>das Top-Level etwas <em>jetzt</em> wissen muss.</li>
                        </ul>
                    </div>
                    <div>
                        <strong>Lieber den normalen Weg …</strong>
                        <ul>
                            <li>Hilfe bei der eigenen Aufgabe → S3 / direkte Führung</li>
                            <li>Ideen, Verbesserungen, Strategie → S4</li>
                            <li>Abstimmung zwischen Teams → S2</li>
                        </ul>
                    </div>
                </div>

                <div>
                    <label for="algedonic-message" class="alg-label">Was ist los?</label>
                    <textarea id="algedonic-message" x-ref="msg" wire:model.live.debounce.300ms="message" rows="5"
                        placeholder="Kurz und konkret: Was sieht das System gerade nicht, was es sehen müsste?"
                        class="alg-input" style="resize: vertical; min-height: 110px"></textarea>
                    @error('message') <p class="alg-error">{{ $message }}</p> @enderror
                </div>

                <div class="relative">
                    <label for="algedonic-entity-search" class="alg-label">
                        Betroffene Einheit <span style="font-weight: 400; color: var(--nx-faint)">(optional)</span>
                    </label>
                    @if ($entityId && $entityName)
                        <div class="alg-chip">
                            @svg('heroicon-o-building-office', 'w-4 h-4 flex-shrink-0', ['style' => 'color: var(--nx-muted)'])
                            <span class="flex-1 min-w-0 truncate">{{ $entityName }}</span>
                            <button type="button" wire:click="clearEntity" class="alg-icon-btn" style="width: 22px; height: 22px" title="Entfernen">
                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                            </button>
                        </div>
                    @else
                        <input id="algedonic-entity-search" type="text" autocomplete="off" class="alg-input"
                            wire:model.live.debounce.250ms="entitySearch"
                            x-on:focus="showSuggestions = true" x-on:click.outside="showSuggestions = false"
                            placeholder="Name tippen — leer lassen, wenn es die Organisation als Ganzes betrifft">
                        @php($suggestions = $this->entitySuggestions)
                        @if (! empty($suggestions))
                            <div x-show="showSuggestions" x-transition.opacity class="alg-menu">
                                @foreach ($suggestions as $sug)
                                    <button type="button" class="alg-opt"
                                        wire:click="selectEntity({{ $sug['id'] }}, @js($sug['name']))" x-on:click="showSuggestions = false">
                                        @svg('heroicon-o-building-office', 'w-4 h-4 flex-shrink-0', ['style' => 'color: var(--nx-muted)'])
                                        <span class="truncate">{{ $sug['name'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (strlen(trim($entitySearch)) >= 2)
                            <div x-show="showSuggestions" class="alg-menu alg-hint" style="padding: 8px 10px">Keine Treffer.</div>
                        @endif
                    @endif
                </div>

                <label class="alg-toggle">
                    <input type="checkbox" wire:model.live="anonymous" class="sr-only">
                    <span class="alg-switch"></span>
                    <span>Anonym senden <span class="alg-hint">— ohne deine Identität</span></span>
                </label>
            </div>

            {{-- Fuß: Empfänger + Aktionen --}}
            <div class="alg-foot">
                @php($routing = $this->routingInfo)
                @if (! empty($routing))
                    <div class="alg-route">
                        @svg('heroicon-o-arrow-right-circle', 'w-4 h-4 flex-shrink-0', ['style' => 'margin-top: 1px'])
                        <div>
                            Geht an
                            @if ($routing['owner_vacant'])
                                <strong>{{ $routing['perspective_name'] }}</strong> (S5-Owner vakant — landet beim Carrier)
                            @else
                                <strong>{{ $routing['owner_name'] }}</strong> (S5-Owner in {{ $routing['perspective_name'] }})
                            @endif
                            · Deadline <strong>{{ $routing['deadline_hours'] }}h</strong>
                        </div>
                    </div>
                @endif
                <div class="flex items-center justify-end gap-2">
                    <button type="button" wire:click="close" class="alg-btn alg-btn-ghost">Abbrechen</button>
                    <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send"
                        @disabled(strlen(trim($message)) < 3) class="alg-btn alg-btn-danger">
                        @svg('heroicon-o-bell-alert', 'w-4 h-4')
                        Signal senden
                    </button>
                </div>
            </div>
        @endif
    </aside>
</div>
