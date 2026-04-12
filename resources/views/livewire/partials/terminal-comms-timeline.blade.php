{{-- ═══ Comms: Timeline View (always rendered) ═══ --}}
<div class="flex-1 min-h-0 flex flex-col">

  {{-- ══ New Message Panel (slide-down overlay above timeline) ══ --}}
  @if($commsShowNewMessage)
    <div class="flex-shrink-0 border-b border-[var(--t-border)]/60 bg-white/[0.03]"
         x-data="{ autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 160) + 'px'; } }">
      <div class="px-4 py-2.5 flex items-center justify-between">
        <span class="text-[11px] font-semibold text-[var(--t-text)]">Neue Nachricht</span>
        <button wire:click="openCommsNewMessage" class="w-5 h-5 rounded flex items-center justify-center text-[var(--t-text-muted)]/60 hover:text-[var(--t-text)] hover:bg-white/10 transition">
          <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="px-4 pb-3 space-y-2">
        {{-- Channel type tabs --}}
        <div class="flex gap-1">
          @if(!empty($emailChannels))
            <button wire:click="$set('commsComposeChannel', 'email')"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-semibold transition border
                      {{ $commsComposeChannel === 'email' ? 'bg-blue-500/15 text-blue-400 border-blue-500/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/8' }}">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
              Email
            </button>
          @endif
          @if(!empty($whatsappChannels))
            <button wire:click="$set('commsComposeChannel', 'whatsapp')"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-semibold transition border
                      {{ $commsComposeChannel === 'whatsapp' ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/8' }}">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
              WhatsApp
            </button>
          @endif
        </div>

        {{-- Context bar (new message) --}}
        @if($contextSubject || $contextDescription || !empty($contextMeta))
          <div x-data="{ expanded: false }" class="rounded-md border border-[var(--t-border)]/25 bg-white/[0.025] overflow-hidden">
            <button @click="expanded = !expanded" type="button" class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left hover:bg-white/[0.03] transition">
              <svg class="w-3 h-3 text-[var(--t-text-muted)]/60 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
              <span class="flex-1 min-w-0 text-[10px] font-medium text-[var(--t-text)] truncate">{{ $contextSubject ?? 'Kontext' }}</span>
              <svg class="w-2.5 h-2.5 text-[var(--t-text-muted)]/40 transition-transform duration-150 flex-shrink-0" :class="expanded && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="expanded" x-collapse class="px-2.5 pb-2 space-y-1 border-t border-[var(--t-border)]/15">
              @if($contextDescription)
                <div class="text-[10px] text-[var(--t-text)]/70 leading-relaxed mt-1.5">{{ $contextDescription }}</div>
              @endif
              @foreach($contextMeta as $metaKey => $metaValue)
                @if(is_string($metaValue) && $metaValue !== '')
                  <div class="flex items-center gap-1.5">
                    <span class="text-[9px] text-[var(--t-text-muted)]/50 uppercase tracking-wider">{{ ucfirst($metaKey) }}</span>
                    <span class="text-[10px] text-[var(--t-text)]/70">{{ $metaValue }}</span>
                  </div>
                @endif
              @endforeach
              @if($contextUrl)
                <a href="{{ $contextUrl }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] text-[var(--t-accent)] hover:text-[var(--t-accent)]/80 transition">
                  <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                  Datensatz öffnen
                </a>
              @endif
              <label class="flex items-center gap-1.5 pt-1 cursor-pointer">
                <input type="checkbox" wire:model.live="commsIncludeContext" class="rounded border-[var(--t-border)]/40 bg-white/5 text-[var(--t-accent)] w-3 h-3 focus:ring-1 focus:ring-[var(--t-accent)]/50" />
                <span class="text-[9px] text-[var(--t-text-muted)]">Kontext unter Nachricht mitsenden</span>
              </label>
            </div>
          </div>
        @endif

        {{-- Email new message --}}
        @if($commsComposeChannel === 'email' && !empty($emailChannels))
          <div class="space-y-1.5">
            {{-- Channel select (if multiple) --}}
            @if(count($emailChannels) > 1)
              <div x-data="{ open: false, options: @js(collect($emailChannels)->map(fn($c) => ['id' => $c['id'], 'label' => $c['label'] ?? $c['sender_identifier'] ?? ''])->values()->all()), get selectedLabel() { const v = String($wire.activeEmailChannelId); const opt = this.options.find(o => String(o.id) === v); return opt ? opt.label : 'Kanal...'; }, select(id) { $wire.set('activeEmailChannelId', id); this.open = false; } }" @click.outside="open = false" class="relative">
                <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] hover:bg-white/8 focus:outline-none transition cursor-pointer">
                  <span class="flex items-center gap-1.5"><span class="text-[9px] text-[var(--t-text-muted)]">Von:</span> <span x-text="selectedLabel" class="truncate"></span></span>
                  <svg class="w-3 h-3 text-[var(--t-text-muted)]/50" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg bg-[var(--t-glass-surface)] backdrop-blur-xl border border-[var(--t-border-bright)] shadow-xl shadow-black/30 max-h-36 overflow-auto py-1" style="display: none;">
                  <template x-for="opt in options" :key="opt.id"><button @click="select(opt.id)" type="button" class="w-full text-left px-2.5 py-1.5 text-[11px] transition" :class="String($wire.activeEmailChannelId) === String(opt.id) ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] font-medium' : 'text-[var(--t-text)] hover:bg-white/8'"><span x-text="opt.label"></span></button></template>
                </div>
              </div>
            @endif
            <input type="email" wire:model.live="emailCompose.to" placeholder="An..."
                   @if(empty($emailCompose['to']) && !empty($contextRecipients))
                     x-init="if(!$wire.emailCompose.to) { $wire.set('emailCompose.to', '{{ addslashes($this->findContextRecipientByType('email') ?? '') }}') }"
                   @endif
                   class="w-full px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 transition" />
            <input type="text" wire:model.live="emailCompose.subject" placeholder="Betreff..."
                   class="w-full px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 transition" />
            <div class="flex items-end gap-1.5">
              <textarea x-ref="newEmailBody" x-init="$nextTick(() => autoGrow($refs.newEmailBody))" @input="autoGrow($event.target)"
                        @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendNewEmail(); }"
                        wire:model="emailCompose.body" rows="2"
                        class="flex-1 px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 resize-none transition"
                        placeholder="Nachricht..."></textarea>
              <button wire:click="sendNewEmail" wire:loading.attr="disabled" wire:target="sendNewEmail"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40 flex-shrink-0">
                <svg class="w-3.5 h-3.5" wire:loading.remove wire:target="sendNewEmail" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                <svg class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="sendNewEmail" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              </button>
            </div>
          </div>
        @endif

        {{-- WhatsApp new message --}}
        @if($commsComposeChannel === 'whatsapp' && !empty($whatsappChannels))
          <div class="space-y-1.5">
            @if(count($whatsappChannels) > 1)
              <div x-data="{ open: false, options: @js(collect($whatsappChannels)->map(fn($c) => ['id' => $c['id'], 'label' => $c['name'] ?? $c['label'] ?? $c['sender_identifier'] ?? ''])->values()->all()), get selectedLabel() { const v = String($wire.activeWhatsAppChannelId); const opt = this.options.find(o => String(o.id) === v); return opt ? opt.label : 'Kanal...'; }, select(id) { $wire.set('activeWhatsAppChannelId', id).then(() => $wire.commsLoadTemplatesForChannel()); this.open = false; } }" @click.outside="open = false" class="relative">
                <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] hover:bg-white/8 focus:outline-none transition cursor-pointer">
                  <span class="flex items-center gap-1.5"><span class="text-[9px] text-[var(--t-text-muted)]">Von:</span> <span x-text="selectedLabel" class="truncate"></span></span>
                  <svg class="w-3 h-3 text-[var(--t-text-muted)]/50" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg bg-[var(--t-glass-surface)] backdrop-blur-xl border border-[var(--t-border-bright)] shadow-xl shadow-black/30 max-h-36 overflow-auto py-1" style="display: none;">
                  <template x-for="opt in options" :key="opt.id"><button @click="select(opt.id)" type="button" class="w-full text-left px-2.5 py-1.5 text-[11px] transition" :class="String($wire.activeWhatsAppChannelId) === String(opt.id) ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] font-medium' : 'text-[var(--t-text)] hover:bg-white/8'"><span x-text="opt.label"></span></button></template>
                </div>
              </div>
            @endif
            <input type="text" wire:model.live="whatsappCompose.to" placeholder="Telefonnummer (+49...)"
                   @if(empty($whatsappCompose['to']) && !empty($contextRecipients))
                     x-init="if(!$wire.whatsappCompose.to) { $wire.set('whatsappCompose.to', '{{ addslashes($this->findContextRecipientByType('phone') ?? '') }}') }"
                   @endif
                   class="w-full px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 transition" />
            <div class="flex items-end gap-1.5">
              <textarea x-ref="newWaBody" x-init="$nextTick(() => autoGrow($refs.newWaBody))" @input="autoGrow($event.target)"
                        @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendNewWhatsApp(); }"
                        wire:model="whatsappCompose.body" rows="2"
                        class="flex-1 px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 resize-none transition"
                        placeholder="Nachricht..."></textarea>
              <button wire:click="sendNewWhatsApp" wire:loading.attr="disabled" wire:target="sendNewWhatsApp"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40 flex-shrink-0">
                <svg class="w-3.5 h-3.5" wire:loading.remove wire:target="sendNewWhatsApp" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                <svg class="w-3.5 h-3.5 animate-spin" wire:loading wire:target="sendNewWhatsApp" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              </button>
            </div>
            {{-- Template section --}}
            @if(!empty($whatsappTemplates))
              <div class="border-t border-[var(--t-border)]/20 pt-1.5 space-y-1.5">
                <span class="text-[9px] font-semibold text-[var(--t-text-muted)]/60 uppercase tracking-wider">Oder Template</span>
                @include('platform::livewire.partials.terminal-comms-template-picker')
              </div>
            @endif
          </div>
        @endif

        @if(empty($emailChannels) && empty($whatsappChannels))
          <div class="text-center py-2">
            <p class="text-[10px] text-[var(--t-text-muted)]">Keine Kanäle konfiguriert.</p>
            <button wire:click="openCommsSettings" class="text-[10px] text-[var(--t-accent)] font-semibold mt-1">Kanäle einrichten</button>
          </div>
        @endif
      </div>
    </div>
  @endif

  {{-- ══ Active Thread Header ══ --}}
  @if($activeContextThreadIndex !== null && isset($allContextThreads[$activeContextThreadIndex]))
    @php $activeThread = $allContextThreads[$activeContextThreadIndex]; @endphp
    <div class="px-4 py-2 border-b border-[var(--t-border)]/40 flex items-center gap-3 flex-shrink-0 bg-white/[0.02]">
      @if($activeThread['type'] === 'email')
        <div class="w-6 h-6 rounded-lg bg-blue-500/15 text-blue-400 flex items-center justify-center flex-shrink-0">
          <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
        </div>
      @else
        <div class="w-6 h-6 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center flex-shrink-0">
          <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
        </div>
      @endif
      <div class="flex-1 min-w-0">
        <div class="text-[11px] font-semibold text-[var(--t-text)] truncate">{{ $activeThread['label'] }}</div>
        <div class="text-[10px] text-[var(--t-text-muted)] truncate">{{ $activeThread['counterpart'] }}@if($activeThread['channel_label']) · {{ $activeThread['channel_label'] }}@endif</div>
      </div>
      @if($activeThread['type'] === 'whatsapp')
        @if($whatsappWindowOpen)
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex-shrink-0"
                x-data="{ expiresAt: @js($whatsappWindowExpiresAt), remaining: '', interval: null, update() { if (!this.expiresAt) { this.remaining = ''; return; } const diff = new Date(this.expiresAt) - new Date(); if (diff <= 0) { this.remaining = 'abgelaufen'; clearInterval(this.interval); return; } const h = Math.floor(diff / 3600000); const m = Math.floor((diff % 3600000) / 60000); this.remaining = h + 'h ' + String(m).padStart(2, '0') + 'min'; }, init() { this.update(); this.interval = setInterval(() => this.update(), 30000); }, destroy() { clearInterval(this.interval); } }">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span x-text="remaining || 'offen'"></span>
          </span>
        @else
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20 flex-shrink-0">
            <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            Templates
          </span>
        @endif
      @endif
    </div>
  @endif

  {{-- ══ Timeline Scroll Area ══ --}}
  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3"
       x-ref="commsTimeline"
       x-data="{ scrollToBottom() { this.$refs.commsTimeline.scrollTop = this.$refs.commsTimeline.scrollHeight; } }"
       x-init="$nextTick(() => scrollToBottom())"
       @comms-scroll-bottom.window="$nextTick(() => scrollToBottom())">

    {{-- Email Timeline --}}
    @if($activeEmailThreadId && (!isset($activeThread) || ($activeThread['type'] ?? '') === 'email'))
      @if(!$activeEmailChannelId)
        <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2.5 flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
          Kein E-Mail Kanal ausgewählt.
        </div>
      @else
        @forelse($emailTimeline as $m)
          @php
            $isInbound = ($m['direction'] ?? '') === 'inbound';
            $from = (string) ($m['from'] ?? '');
            $to = (string) ($m['to'] ?? '');
            $subject = (string) ($m['subject'] ?? '');
            $mailKey = (string) ($m['mail_key'] ?? '');
            $hasHtml = !empty($m['html']);
            $body = trim((string) ($m['text'] ?? ''));
            if ($body === '' && $hasHtml) { $body = trim(strip_tags((string) $m['html'])); }
          @endphp
          <div class="group rounded-lg border {{ $isInbound ? 'border-[var(--t-border)]/30 bg-white/[0.02]' : 'border-blue-500/15 bg-blue-500/[0.03]' }} overflow-hidden transition hover:border-[var(--t-border)]/50">
            <div class="px-3 py-2 flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="text-[11px] font-semibold text-[var(--t-text)] truncate">{{ $subject ?: 'Ohne Betreff' }}</span>
                  @if($isInbound)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider bg-white/8 text-[var(--t-text-muted)] border border-[var(--t-border)]/30">IN</span>
                  @else
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">OUT</span>
                  @endif
                </div>
                <div class="text-[10px] text-[var(--t-text-muted)] mt-0.5 truncate">{{ $isInbound ? $from : $to }}</div>
              </div>
              <div class="flex items-center gap-1.5 flex-shrink-0 pt-0.5">
                <span class="text-[9px] text-[var(--t-text-muted)]/60 whitespace-nowrap">{{ $m['at'] ?? '' }}</span>
                @if($mailKey)
                  <button wire:click="forwardEmail('{{ $mailKey }}')" class="opacity-0 group-hover:opacity-100 w-5 h-5 rounded flex items-center justify-center text-[var(--t-text-muted)]/50 hover:text-blue-400 hover:bg-blue-500/10 transition" title="Weiterleiten">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/></svg>
                  </button>
                @endif
              </div>
            </div>
            @if($hasHtml)
              <div class="px-3 pb-2.5"
                   x-data="{ expanded: false, iframeHeight: 80, htmlContent: '', renderHtml() { const iframe = this.$refs.emailFrame; const doc = iframe.contentDocument || iframe.contentWindow.document; const css = 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:13px;line-height:1.5;color:#e4e4e7;background:transparent;margin:0;padding:0;word-break:break-word}a{color:#60a5fa}img{max-width:100%;height:auto}blockquote{border-left:2px solid #3f3f46;margin:8px 0;padding-left:8px;color:#a1a1aa}details summary{cursor:pointer;color:#a1a1aa;font-size:11px}'; doc.open(); doc.write('<html><head><style>' + css + '</style></head><body>' + this.htmlContent + '</body></html>'); doc.close(); setTimeout(() => { this.iframeHeight = Math.min(doc.body.scrollHeight + 4, this.expanded ? 2000 : 200); }, 80); } }"
                   x-init="htmlContent = @js($m['html']); $nextTick(() => renderHtml())">
                <iframe x-ref="emailFrame" sandbox="allow-same-origin" class="w-full border-0 rounded bg-transparent overflow-hidden" :style="'height:' + iframeHeight + 'px'" style="height: 80px;"></iframe>
                <button x-show="iframeHeight >= 200 && !expanded" @click="expanded = true; iframeHeight = Math.min($refs.emailFrame.contentDocument.body.scrollHeight + 4, 2000)" class="mt-1 text-[10px] text-[var(--t-accent)] hover:text-[var(--t-accent)]/80 font-medium transition" style="display:none;">Mehr anzeigen...</button>
              </div>
            @else
              <div class="px-3 pb-2.5 text-xs text-[var(--t-text)]/75 whitespace-pre-wrap leading-relaxed">{{ $body }}</div>
            @endif
            @if(!empty($m['attachments']))
              <div class="px-3 pb-2.5 flex flex-wrap gap-1.5">
                @foreach($m['attachments'] as $att)
                  <a href="{{ $att['url'] ?? '#' }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[10px] text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/10 transition">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                    {{ $att['filename'] ?? 'Anhang' }}
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        @empty
          <div class="flex flex-col items-center justify-center py-10 gap-2">
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-400/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </div>
            <p class="text-[11px] text-[var(--t-text-muted)]">Noch keine E-Mails in diesem Thread.</p>
          </div>
        @endforelse

        {{-- Forward Banner --}}
        @if($isForwarding)
          <div class="rounded-lg border border-blue-500/20 bg-blue-500/[0.06] px-3 py-2.5 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/></svg>
            <div class="flex-1 min-w-0">
              <div class="text-[11px] text-blue-300 font-semibold">Weiterleitung</div>
              <div class="text-[10px] text-blue-300/70 truncate">{{ $forwardingFrom }}@if($forwardingSubject) — {{ $forwardingSubject }}@endif</div>
            </div>
            <button wire:click="cancelForward" class="text-[10px] text-blue-400 hover:text-blue-300 px-2 py-1 rounded hover:bg-blue-500/10 transition">Abbrechen</button>
          </div>
        @endif
      @endif
    @endif

    {{-- WhatsApp Timeline --}}
    @if($activeWhatsAppThreadId && (!isset($activeThread) || ($activeThread['type'] ?? '') === 'whatsapp'))
      @if(!$activeWhatsAppChannelId)
        <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2.5 flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
          Kein WhatsApp Kanal ausgewählt.
        </div>
      @else
        {{-- Conversation Thread Selector --}}
        @if(!empty($conversationThreads))
          <div class="flex items-center gap-1.5 flex-wrap pb-1">
            <button wire:click="setActiveConversationThread(null)" class="px-2.5 py-1 rounded-full text-[10px] font-semibold border transition {{ !$activeConversationThreadId ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/10' }}">Alle</button>
            @foreach($conversationThreads as $ct)
              <button wire:click="setActiveConversationThread({{ intval($ct['id']) }})" class="px-2.5 py-1 rounded-full text-[10px] font-semibold border transition inline-flex items-center gap-1 {{ (int) $activeConversationThreadId === (int) $ct['id'] ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : ($ct['is_active'] ? 'bg-emerald-500/5 text-emerald-400/70 border-emerald-500/15 hover:bg-emerald-500/10' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/10') }}">
                @if($ct['is_active'])<span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>@endif
                {{ $ct['label'] }} <span class="opacity-50">({{ $ct['messages_count'] }})</span>
              </button>
            @endforeach
          </div>
        @endif

        @if($viewingConversationHistory && $activeConversationThreadId)
          <div class="rounded-lg border border-amber-500/20 bg-amber-500/[0.06] px-3 py-2 flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
            <span class="text-[10px] text-amber-300 font-medium">Archiviert — nur lesen</span>
          </div>
        @endif

        <div class="space-y-1.5">
          @forelse($whatsappTimeline as $wm)
            @php
              $isInbound = ($wm['direction'] ?? '') === 'inbound';
              $body = (string) ($wm['body'] ?? '');
              $at = (string) ($wm['at'] ?? '');
              $fullAt = (string) ($wm['full_at'] ?? '');
              $sentBy = (string) ($wm['sent_by'] ?? '');
              $status = (string) ($wm['status'] ?? '');
              $messageType = (string) ($wm['message_type'] ?? 'text');
              $mediaDisplayType = (string) ($wm['media_display_type'] ?? $messageType);
              $hasMedia = (bool) ($wm['has_media'] ?? false);
              $attachments = $wm['attachments'] ?? [];
              $reactions = $wm['reactions'] ?? [];
            @endphp
            <div class="flex {{ $isInbound ? 'justify-start' : 'justify-end' }}">
              <div class="max-w-[80%] rounded-2xl px-3 py-2 {{ $isInbound ? 'bg-white/[0.06] border border-[var(--t-border)]/25 rounded-tl-md' : 'bg-emerald-500/10 border border-emerald-500/15 rounded-tr-md' }}">
                @if($hasMedia && !empty($attachments))
                  @foreach($attachments as $att)
                    @php $attUrl = $att['url'] ?? null; $attThumb = $att['thumbnail'] ?? $attUrl; $attTitle = $att['title'] ?? 'Datei'; @endphp
                    @if($mediaDisplayType === 'image' && $attUrl)
                      <a href="{{ $attUrl }}" target="_blank" class="block mb-1.5"><img src="{{ $attThumb }}" alt="{{ $attTitle }}" class="rounded-xl max-w-full max-h-48 object-cover" loading="lazy" /></a>
                    @elseif($mediaDisplayType === 'sticker' && $attUrl)
                      <div class="mb-1.5"><img src="{{ $attUrl }}" alt="Sticker" class="w-24 h-24 object-contain" loading="lazy" /></div>
                    @elseif($mediaDisplayType === 'video' && $attUrl)
                      <div class="mb-1.5"><video controls preload="metadata" class="rounded-xl max-w-full max-h-48"><source src="{{ $attUrl }}" /></video></div>
                    @elseif(($mediaDisplayType === 'voice' || $mediaDisplayType === 'audio') && $attUrl)
                      <div class="mb-1.5"><audio controls preload="metadata" class="h-8 w-full min-w-[160px]"><source src="{{ $attUrl }}" /></audio></div>
                    @elseif($mediaDisplayType === 'document' && $attUrl)
                      <a href="{{ $attUrl }}" target="_blank" class="flex items-center gap-2 mb-1.5 px-2.5 py-2 rounded-xl bg-white/5 border border-[var(--t-border)]/25 hover:bg-white/10 transition">
                        <svg class="w-4 h-4 text-[var(--t-text-muted)] flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span class="text-[11px] text-[var(--t-text)] truncate">{{ $attTitle }}</span>
                      </a>
                    @endif
                  @endforeach
                @endif
                @if(!$isInbound && $messageType === 'template')
                  <div class="flex items-center gap-1 mb-0.5">
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">Template</span>
                  </div>
                @endif
                @if($body)<div class="text-[12px] text-[var(--t-text)]/85 whitespace-pre-wrap leading-relaxed">{{ $body }}</div>@endif
                @if(!empty($reactions))<div class="flex gap-0.5 mt-1">@foreach($reactions as $r)<span class="text-sm">{{ $r['emoji'] }}</span>@endforeach</div>@endif
                <div class="mt-1 flex items-center {{ $isInbound ? '' : 'justify-end' }} gap-1 text-[9px] text-[var(--t-text-muted)]/50">
                  <span title="{{ $fullAt }}">{{ $at }}</span>
                  @if(!$isInbound)
                    @if($sentBy)<span class="opacity-70">· {{ $sentBy }}</span>@endif
                    @if($status === 'read')<span class="text-blue-400 ml-0.5">✓✓</span>
                    @elseif($status === 'delivered')<span class="ml-0.5">✓✓</span>
                    @elseif($status === 'sent')<span class="ml-0.5">✓</span>
                    @elseif($status === 'failed')<span class="text-red-400 ml-0.5">✕</span>@endif
                  @endif
                </div>
              </div>
            </div>
          @empty
            <div class="flex flex-col items-center justify-center py-10 gap-2">
              <div class="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-400/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
              </div>
              <p class="text-[11px] text-[var(--t-text-muted)]">Noch keine Nachrichten.</p>
            </div>
          @endforelse
        </div>
      @endif
    @endif

    {{-- No thread selected --}}
    @if(!$activeEmailThreadId && !$activeWhatsAppThreadId)
      <div class="flex-1 flex items-center justify-center py-16">
        <div class="text-center space-y-3 max-w-[200px]">
          <div class="w-12 h-12 mx-auto rounded-2xl bg-[var(--t-accent)]/10 flex items-center justify-center">
            <svg class="w-6 h-6 text-[var(--t-accent)]/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-[var(--t-text)]">Comms</p>
            <p class="text-[10px] text-[var(--t-text-muted)] mt-1 leading-relaxed">Thread auswählen oder neue Nachricht verfassen.</p>
          </div>
          <button wire:click="openCommsNewMessage"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition shadow-sm">
            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
            Neue Nachricht
          </button>
        </div>
      </div>
    @endif
  </div>

  {{-- ══ Compose Area (sticky bottom) ══ --}}
  @if($activeEmailThreadId || $activeWhatsAppThreadId)
    <div class="border-t border-[var(--t-border)]/60 flex-shrink-0"
         x-data="{ autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'; } }">

      {{-- Status messages (auto-dismiss) --}}
      @if($emailMessage || $whatsappMessage)
        <div class="px-4 pt-2 pb-0"
             x-data="{ show: true }"
             x-init="setTimeout(() => { show = false; @if($emailMessage) $wire.set('emailMessage', null) @endif @if($whatsappMessage) $wire.set('whatsappMessage', null) @endif }, 3000)"
             x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
          @if($emailMessage)
            <div class="text-[10px] font-medium {{ str_contains($emailMessage, '✅') ? 'text-emerald-400' : 'text-red-400' }}">{{ $emailMessage }}</div>
          @endif
          @if($whatsappMessage)
            <div class="text-[10px] font-medium {{ str_contains($whatsappMessage, '✅') ? 'text-emerald-400' : 'text-red-400' }}">{{ $whatsappMessage }}</div>
          @endif
        </div>
      @endif
      @error('emailCompose.body')<div class="px-4 pt-2 pb-0 text-[10px] text-red-400 font-medium">{{ $message }}</div>@enderror
      @error('whatsappCompose.body')<div class="px-4 pt-2 pb-0 text-[10px] text-red-400 font-medium">{{ $message }}</div>@enderror

      {{-- Context indicator (reply) — compact, read-only --}}
      @if($contextSubject)
        <div class="px-4 pt-2 pb-0">
          <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white/[0.025] border border-[var(--t-border)]/15">
            <svg class="w-2.5 h-2.5 text-[var(--t-text-muted)]/40 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
            <span class="text-[9px] text-[var(--t-text-muted)]/60 truncate">{{ $contextSubject }}</span>
            @if($contextUrl)
              <a href="{{ $contextUrl }}" target="_blank" class="ml-auto flex-shrink-0 text-[var(--t-text-muted)]/30 hover:text-[var(--t-accent)] transition">
                <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
              </a>
            @endif
          </div>
        </div>
      @endif

      <div class="px-4 py-2.5">
        {{-- Email Compose --}}
        @if($activeEmailThreadId)
          @if($isForwarding)
            <div class="mb-2 flex items-center gap-2">
              <span class="text-[10px] font-semibold text-[var(--t-text-muted)] w-6 flex-shrink-0">An</span>
              <input type="email" wire:model.live="emailCompose.to" class="flex-1 px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 transition" placeholder="empfaenger@firma.de" />
            </div>
          @endif
          <div class="flex items-end gap-2">
            <div class="flex-1 min-w-0 rounded-lg border transition-all border-[var(--t-border)]/80 focus-within:border-[var(--t-accent)]/50 focus-within:shadow-[0_0_0_1px_var(--t-accent)]">
              <textarea x-ref="emailBody" x-init="$nextTick(() => autoGrow($refs.emailBody))" @input="autoGrow($event.target)"
                @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendEmail(); }" rows="1" wire:model="emailCompose.body"
                class="w-full px-3 py-2 text-xs bg-transparent text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none resize-none"
                placeholder="{{ $isForwarding ? 'Weiterleitung...' : 'Antworten...' }}"></textarea>
            </div>
            <button wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail" title="Senden (Enter)"
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0 shadow-sm">
              <svg class="w-4 h-4" wire:loading.remove wire:target="sendEmail" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
              <svg class="w-4 h-4 animate-spin" wire:loading wire:target="sendEmail" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            </button>
          </div>
        @endif

        {{-- WhatsApp Compose --}}
        @if($activeWhatsAppThreadId)
          @if($viewingConversationHistory)
            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5 border border-[var(--t-border)]/25">
              <svg class="w-3.5 h-3.5 text-[var(--t-text-muted)] flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
              <span class="text-[10px] text-[var(--t-text-muted)]">Archiviert — zum aktiven Thread wechseln.</span>
            </div>
          @elseif($whatsappWindowOpen)
            <div class="flex items-end gap-2">
              <div class="flex-1 min-w-0 rounded-lg border transition-all border-[var(--t-border)]/80 focus-within:border-[var(--t-accent)]/50 focus-within:shadow-[0_0_0_1px_var(--t-accent)]">
                <textarea x-ref="waBody" x-init="$nextTick(() => autoGrow($refs.waBody))" @input="autoGrow($event.target)"
                  @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendWhatsApp(); }" rows="1" wire:model="whatsappCompose.body"
                  class="w-full px-3 py-2 text-xs bg-transparent text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none resize-none"
                  placeholder="Nachricht..."></textarea>
              </div>
              <button wire:click="sendWhatsApp" wire:loading.attr="disabled" wire:target="sendWhatsApp" title="Senden (Enter)"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0 shadow-sm">
                <svg class="w-4 h-4" wire:loading.remove wire:target="sendWhatsApp" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="sendWhatsApp" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              </button>
            </div>
          @else
            {{-- Closed window: Template mode --}}
            <div class="space-y-2">
              <div class="rounded-lg border border-amber-500/20 bg-amber-500/[0.06] px-3 py-2 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span class="text-[10px] text-amber-300 font-medium">24h-Fenster geschlossen — nur Templates.</span>
              </div>
              @if(!empty($whatsappTemplates))
                @include('platform::livewire.partials.terminal-comms-template-picker')
              @else
                <div class="text-[10px] text-[var(--t-text-muted)] text-center py-1">Keine Templates verfügbar.</div>
              @endif
            </div>
          @endif
        @endif
      </div>
    </div>
  @endif
</div>
