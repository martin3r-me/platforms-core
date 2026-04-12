{{-- ═══ Comms: New Message View ═══ --}}
<div class="flex-1 min-h-0 flex flex-col"
     x-data="{
       channelType: @js(!empty($emailChannels) ? 'email' : (!empty($whatsappChannels) ? 'whatsapp' : 'email')),
       autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 200) + 'px'; }
     }">

  {{-- Header --}}
  <div class="px-4 py-2.5 border-b border-[var(--t-border)]/40 flex-shrink-0 bg-white/[0.02]">
    <div class="flex items-center gap-2">
      <button wire:click="$set('commsView', 'timeline')" class="w-6 h-6 rounded-md flex items-center justify-center text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/10 transition">
        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
      </button>
      <svg class="w-4 h-4 text-[var(--t-accent)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
      <span class="text-xs font-semibold text-[var(--t-text)]">Neue Nachricht</span>
    </div>
  </div>

  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4">

    {{-- Channel Type Toggle --}}
    <div class="flex rounded-lg overflow-hidden border border-[var(--t-border)]/30 bg-white/[0.02]">
      @if(!empty($emailChannels))
        <button @click="channelType = 'email'"
                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold tracking-wide transition"
                :class="channelType === 'email'
                  ? 'bg-blue-500/15 text-blue-400 border-b-2 border-blue-400'
                  : 'text-[var(--t-text-muted)] hover:bg-white/5 border-b-2 border-transparent'">
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
          E-Mail
        </button>
      @endif
      @if(!empty($whatsappChannels))
        <button @click="channelType = 'whatsapp'"
                class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-[11px] font-semibold tracking-wide transition"
                :class="channelType === 'whatsapp'
                  ? 'bg-emerald-500/15 text-emerald-400 border-b-2 border-emerald-400'
                  : 'text-[var(--t-text-muted)] hover:bg-white/5 border-b-2 border-transparent'">
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
          WhatsApp
        </button>
      @endif
    </div>

    @if(empty($emailChannels) && empty($whatsappChannels))
      <div class="rounded-lg border border-[var(--t-border)]/20 bg-white/[0.02] px-4 py-8 text-center space-y-2">
        <svg class="w-8 h-8 mx-auto text-[var(--t-text-muted)] opacity-30" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
        <p class="text-xs text-[var(--t-text-muted)]">Keine Kanäle konfiguriert.</p>
        <button wire:click="openCommsSettings" class="text-[11px] text-[var(--t-accent)] hover:underline font-semibold">Kanäle einrichten</button>
      </div>
    @endif

    {{-- ════ Email Form ════ --}}
    <div x-show="channelType === 'email'" x-cloak class="space-y-3">
      @if(!empty($emailChannels))
        {{-- Channel Select --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Von</label>
          <select wire:model.live="activeEmailChannelId"
                  class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-blue-500/50 focus:border-blue-500/30">
            @foreach($emailChannels as $ec)
              <option value="{{ $ec['id'] }}">{{ $ec['label'] ?? $ec['sender_identifier'] ?? '' }}</option>
            @endforeach
          </select>
        </div>

        {{-- To --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">An</label>
          <input type="email" wire:model.live="emailCompose.to"
                 placeholder="empfaenger@firma.de"
                 @if(empty($emailCompose['to']) && !empty($contextRecipients))
                   x-init="if(!$wire.emailCompose.to) { $wire.set('emailCompose.to', '{{ addslashes($this->findContextRecipientByType('email') ?? '') }}') }"
                 @endif
                 class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-blue-500/50 focus:border-blue-500/30" />
        </div>

        {{-- Subject --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Betreff</label>
          <input type="text" wire:model.live="emailCompose.subject"
                 placeholder="Betreff..."
                 class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-blue-500/50 focus:border-blue-500/30" />
        </div>

        {{-- Body --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Nachricht</label>
          <textarea x-ref="newEmailBody"
                    x-init="$nextTick(() => autoGrow($refs.newEmailBody))"
                    @input="autoGrow($event.target)"
                    wire:model="emailCompose.body"
                    rows="5"
                    class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-blue-500/50 focus:border-blue-500/30 resize-none"
                    placeholder="Nachricht verfassen..."></textarea>
        </div>

        {{-- Error / Status --}}
        @error('emailCompose.body')
          <div class="text-[10px] text-red-400 font-medium">{{ $message }}</div>
        @enderror
        @if($emailMessage)
          <div class="text-[10px] text-emerald-400 font-medium">{{ $emailMessage }}</div>
        @endif

        {{-- Send Button --}}
        <div class="flex items-center justify-between">
          <span class="text-[10px] text-[var(--t-text-muted)]/50">Enter zum Senden</span>
          <button wire:click="sendNewEmail" wire:loading.attr="disabled" wire:target="sendNewEmail"
                  class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-600 transition disabled:opacity-50 shadow-sm shadow-blue-500/20">
            <svg class="w-3.5 h-3.5" wire:loading.remove wire:target="sendNewEmail" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
            <span wire:loading.remove wire:target="sendNewEmail">E-Mail senden</span>
            <span wire:loading wire:target="sendNewEmail">Sende...</span>
          </button>
        </div>
      @endif
    </div>

    {{-- ════ WhatsApp Form ════ --}}
    <div x-show="channelType === 'whatsapp'" x-cloak class="space-y-3">
      @if(!empty($whatsappChannels))
        {{-- Channel Select --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Von</label>
          <select wire:model.live="activeWhatsAppChannelId"
                  class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-emerald-500/50 focus:border-emerald-500/30">
            @foreach($whatsappChannels as $wc)
              <option value="{{ $wc['id'] }}">{{ $wc['name'] ?? $wc['label'] ?? $wc['sender_identifier'] ?? '' }}</option>
            @endforeach
          </select>
        </div>

        {{-- To (Phone) --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">An (Telefonnummer)</label>
          <input type="text" wire:model.live="whatsappCompose.to"
                 placeholder="+49 172 123 45 67"
                 @if(empty($whatsappCompose['to']) && !empty($contextRecipients))
                   x-init="if(!$wire.whatsappCompose.to) { $wire.set('whatsappCompose.to', '{{ addslashes($this->findContextRecipientByType('phone') ?? '') }}') }"
                 @endif
                 class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/50 focus:border-emerald-500/30" />
        </div>

        {{-- Freeform Message --}}
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Nachricht</label>
          <textarea x-ref="newWaBody"
                    x-init="$nextTick(() => autoGrow($refs.newWaBody))"
                    @input="autoGrow($event.target)"
                    wire:model="whatsappCompose.body"
                    rows="4"
                    class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/50 focus:border-emerald-500/30 resize-none"
                    placeholder="Nachricht verfassen..."></textarea>
        </div>

        {{-- Error / Status --}}
        @error('whatsappCompose.body')
          <div class="text-[10px] text-red-400 font-medium">{{ $message }}</div>
        @enderror
        @if($whatsappMessage)
          <div class="text-[10px] text-emerald-400 font-medium">{{ $whatsappMessage }}</div>
        @endif

        {{-- Send Button --}}
        <div class="flex items-center justify-between">
          <span class="text-[10px] text-[var(--t-text-muted)]/50">Enter zum Senden</span>
          <button wire:click="sendNewWhatsApp" wire:loading.attr="disabled" wire:target="sendNewWhatsApp"
                  class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition disabled:opacity-50 shadow-sm shadow-emerald-600/20">
            <svg class="w-3.5 h-3.5" wire:loading.remove wire:target="sendNewWhatsApp" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
            <span wire:loading.remove wire:target="sendNewWhatsApp">WhatsApp senden</span>
            <span wire:loading wire:target="sendNewWhatsApp">Sende...</span>
          </button>
        </div>

        {{-- Templates Section --}}
        @if(!empty($whatsappTemplates))
          <div class="border-t border-[var(--t-border)]/30 pt-3 space-y-2">
            <div class="flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
              <span class="text-[10px] font-semibold text-[var(--t-text)] uppercase tracking-wider">Oder Template verwenden</span>
            </div>

            <select wire:model.live="whatsappSelectedTemplateId"
                    class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-amber-500/50">
              <option value="">Template wählen...</option>
              @foreach($whatsappTemplates as $tpl)
                <option value="{{ $tpl['id'] }}">{{ $tpl['label'] ?? $tpl['name'] ?? '' }}</option>
              @endforeach
            </select>

            @if(!empty($whatsappTemplatePreview))
              <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/[0.04] p-3 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-semibold text-emerald-400">{{ $whatsappTemplatePreview['name'] ?? '' }}</span>
                  @if(!empty($whatsappTemplatePreview['language']))
                    <span class="text-[9px] text-[var(--t-text-muted)] px-1.5 py-0.5 rounded bg-white/5 border border-[var(--t-border)]/30">{{ $whatsappTemplatePreview['language'] }}</span>
                  @endif
                </div>
                <div class="rounded-lg bg-white/[0.04] px-3 py-2 text-xs text-[var(--t-text)]/80 whitespace-pre-wrap">{{ $this->getTemplatePreviewText() }}</div>
                @if(($whatsappTemplatePreview['variables_count'] ?? 0) > 0)
                  <div class="space-y-1.5">
                    <span class="text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider">Variablen</span>
                    @for($i = 1; $i <= $whatsappTemplatePreview['variables_count']; $i++)
                      <div class="flex items-center gap-2">
                        <span class="text-[10px] text-emerald-400/70 font-mono w-8 flex-shrink-0 text-right">&#123;&#123;{{ $i }}&#125;&#125;</span>
                        <input type="text" wire:model.live="whatsappTemplateVariables.{{ $i }}"
                               class="flex-1 px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-emerald-500/50 placeholder-[var(--t-text-muted)]/50"
                               placeholder="Wert für Variable {{ $i }}..." />
                      </div>
                    @endfor
                  </div>
                @endif
                <div class="flex justify-end pt-1">
                  <button wire:click="sendNewWhatsAppTemplate" wire:loading.attr="disabled" wire:target="sendNewWhatsAppTemplate"
                          class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition disabled:opacity-50 shadow-sm shadow-emerald-600/20">
                    <svg class="w-3.5 h-3.5" wire:loading.remove wire:target="sendNewWhatsAppTemplate" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                    <span wire:loading.remove wire:target="sendNewWhatsAppTemplate">Template senden</span>
                    <span wire:loading wire:target="sendNewWhatsAppTemplate">Sende...</span>
                  </button>
                </div>
              </div>
            @endif
          </div>
        @endif
      @endif
    </div>

  </div>
</div>
