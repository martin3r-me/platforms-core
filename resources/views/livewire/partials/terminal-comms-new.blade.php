{{-- ═══ Comms: New Message View ═══ --}}
<div class="flex-1 min-h-0 flex flex-col"
     x-data="{ channelType: 'email', autoGrow(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; } }">

  {{-- Header --}}
  <div class="px-4 py-3 border-b border-[var(--t-border)]/40 flex-shrink-0">
    <div class="flex items-center gap-2">
      <button wire:click="$set('commsView', 'timeline')" class="text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
      </button>
      <span class="text-xs font-semibold text-[var(--t-text)]">Neue Nachricht</span>
    </div>
  </div>

  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4">

    {{-- Channel Type Selector --}}
    <div class="flex rounded-md border border-[var(--t-border)]/40 overflow-hidden">
      <button @click="channelType = 'email'"
              class="flex-1 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider transition"
              :class="channelType === 'email' ? 'bg-[var(--t-accent)]/20 text-[var(--t-accent)]' : 'text-[var(--t-text-muted)] hover:bg-white/5'">
        E-Mail
      </button>
      <button @click="channelType = 'whatsapp'"
              class="flex-1 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wider transition"
              :class="channelType === 'whatsapp' ? 'bg-emerald-500/20 text-emerald-400' : 'text-[var(--t-text-muted)] hover:bg-white/5'">
        WhatsApp
      </button>
    </div>

    {{-- Email Channel Select --}}
    <div x-show="channelType === 'email'" x-cloak class="space-y-3">
      @if(!empty($emailChannels))
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Absender-Kanal</label>
          <select wire:model.live="activeEmailChannelId"
                  class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
            @foreach($emailChannels as $ec)
              <option value="{{ $ec['id'] }}">{{ $ec['label'] ?? $ec['sender_identifier'] ?? '' }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">An</label>
          <input type="email" wire:model.live="emailCompose.to"
                 placeholder="empfaenger@firma.de"
                 class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Betreff</label>
          <input type="text" wire:model.live="emailCompose.subject"
                 placeholder="Betreff..."
                 class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Nachricht</label>
          <textarea x-ref="newEmailBody"
                    x-init="$nextTick(() => autoGrow($refs.newEmailBody))"
                    @input="autoGrow($event.target)"
                    wire:model="emailCompose.body"
                    rows="4"
                    class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] resize-none"
                    placeholder="Nachricht verfassen..."></textarea>
        </div>
        <div class="flex justify-end">
          <button wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail"
                  class="px-4 py-1.5 rounded-md text-xs font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-50">
            <span wire:loading.remove wire:target="sendEmail">E-Mail senden</span>
            <span wire:loading wire:target="sendEmail">Sende...</span>
          </button>
        </div>
        @error('emailCompose.body')
          <div class="text-[10px] text-red-400">{{ $message }}</div>
        @enderror
        @if($emailMessage)
          <div class="text-[10px] text-[var(--t-text-muted)]">{{ $emailMessage }}</div>
        @endif
      @else
        <div class="text-xs text-[var(--t-text-muted)] bg-white/5 rounded-md px-3 py-4 text-center">
          Keine E-Mail Kanäle konfiguriert.
          <button wire:click="openCommsSettings" class="text-[var(--t-accent)] hover:underline ml-1">Kanäle einrichten</button>
        </div>
      @endif
    </div>

    {{-- WhatsApp Channel Select --}}
    <div x-show="channelType === 'whatsapp'" x-cloak class="space-y-3">
      @if(!empty($whatsappChannels))
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">WhatsApp-Kanal</label>
          <select wire:model.live="activeWhatsAppChannelId"
                  class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
            @foreach($whatsappChannels as $wc)
              <option value="{{ $wc['id'] }}">{{ $wc['name'] ?? $wc['label'] ?? $wc['sender_identifier'] ?? '' }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">An (Telefonnummer)</label>
          <input type="text" wire:model.live="whatsappCompose.to"
                 placeholder="+49 172 123 45 67"
                 class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Nachricht</label>
          <textarea x-ref="newWaBody"
                    x-init="$nextTick(() => autoGrow($refs.newWaBody))"
                    @input="autoGrow($event.target)"
                    wire:model="whatsappCompose.body"
                    rows="4"
                    class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] resize-none"
                    placeholder="Nachricht verfassen..."></textarea>
        </div>
        <div class="flex justify-end">
          <button wire:click="sendWhatsApp" wire:loading.attr="disabled" wire:target="sendWhatsApp"
                  class="px-4 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition disabled:opacity-50">
            <span wire:loading.remove wire:target="sendWhatsApp">WhatsApp senden</span>
            <span wire:loading wire:target="sendWhatsApp">Sende...</span>
          </button>
        </div>
        @error('whatsappCompose.body')
          <div class="text-[10px] text-red-400">{{ $message }}</div>
        @enderror
        @if($whatsappMessage)
          <div class="text-[10px] text-[var(--t-text-muted)]">{{ $whatsappMessage }}</div>
        @endif
      @else
        <div class="text-xs text-[var(--t-text-muted)] bg-white/5 rounded-md px-3 py-4 text-center">
          Keine WhatsApp Kanäle konfiguriert.
          <button wire:click="openCommsSettings" class="text-[var(--t-accent)] hover:underline ml-1">Kanäle einrichten</button>
        </div>
      @endif
    </div>

  </div>
</div>
