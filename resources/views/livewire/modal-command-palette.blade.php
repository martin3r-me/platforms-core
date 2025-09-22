<x-ui-modal size="lg" wire:model="modalShow">
    <x-slot name="header">
        Befehl ausführen
    </x-slot>

    <div class="space-y-3" x-data="commandVoice()" x-init="init()">
        <div class="d-flex items-center gap-2">
            <input
                type="text"
                wire:model.defer="input"
                wire:keydown.enter="execute"
                class="flex-grow px-3 py-2 border rounded"
                placeholder="z. B.: lege projekt Alpha an"
                aria-label="Befehl eingeben"
                x-ref="cmdInput"
                autofocus
            >
            <button type="button"
                    class="px-3 py-2 rounded border"
                    :class="listening ? 'bg-danger text-white' : 'bg-white text-secondary'"
                    @click="toggle()"
                    :aria-pressed="listening ? 'true' : 'false'"
                    aria-label="Spracheingabe umschalten">
                <span x-show="!listening">🎙️</span>
                <span x-show="listening">⏹️</span>
            </button>
        </div>
        @error('input')<div class="text-danger text-sm mt-1">{{ $message }}</div>@enderror

        <div class="d-flex items-center gap-2">
            <x-ui-button variant="secondary-outline" wire:click="executeLlm">KI planen</x-ui-button>
            <label class="d-flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="forceExecute" class="border rounded">
                ohne Rückfrage ausführen
            </label>
            @if(!empty($llm))
                @if(($llm['ok'] ?? false) === true)
                    <x-ui-badge variant="info" size="sm">Vorschlag: {{ $llm['intent'] ?? '–' }}</x-ui-badge>
                    @if(($llm['confirmRequired'] ?? false))
                        <x-ui-badge variant="warning" size="sm">Bestätigung benötigt</x-ui-badge>
                    @endif
                @else
                    <div class="d-flex items-center gap-2">
                        <x-ui-badge variant="danger" size="sm">LLM: {{ $llm['message'] ?? 'Fehler' }}</x-ui-badge>
                        @if(!empty($llm['detail']))
                            <span class="text-xs text-gray-500 truncate max-w-64" title="{{ is_array($llm['detail']) ? json_encode($llm['detail']) : $llm['detail'] }}">
                                {{ is_array($llm['detail']) ? json_encode($llm['detail']) : $llm['detail'] }}
                            </span>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        @if(!empty($result))
            <div class="p-3 rounded border bg-muted-5">
                @if(($result['ok'] ?? false) === true)
                    <div class="text-success font-medium mb-1">Erfolg</div>
                    @if(!empty($result['message']))
                        <div class="text-sm">{{ $result['message'] }}</div>
                    @endif
                    @if(!empty($result['navigate']))
                        <div class="mt-2">
                            <a href="{{ $result['navigate'] }}" class="text-primary underline" wire:navigate>Öffnen</a>
                        </div>
                    @endif
                @else
                    <div class="text-danger font-medium mb-1">Fehler</div>
                    <div class="text-sm">{{ $result['message'] ?? 'Unbekannter Fehler' }}</div>
                @endif
            </div>
        @endif
    </div>

    <x-slot name="footer">
        <div class="d-flex items-center gap-2 ml-auto">
            <x-ui-button variant="secondary-outline" wire:click="close">Schließen</x-ui-button>
            <x-ui-button variant="primary" wire:click="execute">Ausführen</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>

<script>
    function commandVoice(){
        return {
            listening: false,
            recognition: null,
            init(){
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR) return;
                this.recognition = new SR();
                this.recognition.lang = document.documentElement.lang || 'de-DE';
                this.recognition.interimResults = false;
                this.recognition.maxAlternatives = 1;
                this.recognition.onresult = (e) => {
                    try {
                        const txt = e.results[0][0].transcript;
                        const el = this.$refs.cmdInput;
                        if (el){
                            el.value = txt;
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        if (typeof $wire !== 'undefined' && $wire){
                            $wire.set('input', txt);
                        }
                    } catch(err){ /* noop */ }
                };
                this.recognition.onerror = () => { this.listening = false; };
                this.recognition.onend = () => { this.listening = false; };
            },
            toggle(){
                if (!this.recognition) { alert('Spracherkennung wird vom Browser nicht unterstützt.'); return; }
                if (this.listening){ this.recognition.stop(); this.listening = false; return; }
                this.listening = true; this.recognition.start();
            }
        }
    }
</script>


