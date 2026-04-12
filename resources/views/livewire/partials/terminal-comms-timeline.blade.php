{{-- ═══ Comms: Timeline View ═══ --}}
<div class="flex-1 min-h-0 flex flex-col">

  {{-- Active Thread Header --}}
  @if($activeContextThreadIndex !== null && isset($allContextThreads[$activeContextThreadIndex]))
    @php $activeThread = $allContextThreads[$activeContextThreadIndex]; @endphp
    <div class="px-4 py-2 border-b border-[var(--t-border)]/40 flex items-center gap-2 flex-shrink-0 bg-white/[0.02]">
      @if($activeThread['type'] === 'email')
        <span class="w-5 h-5 rounded-md bg-blue-500/20 text-blue-400 flex items-center justify-center">
          <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
        </span>
      @else
        <span class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
          <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
        </span>
      @endif
      <div class="flex-1 min-w-0">
        <div class="text-xs font-semibold text-[var(--t-text)] truncate">{{ $activeThread['label'] }}</div>
        <div class="text-[10px] text-[var(--t-text-muted)]">{{ $activeThread['counterpart'] }} · {{ $activeThread['channel_label'] }}</div>
      </div>
    </div>
  @endif

  {{-- Timeline Scroll Area --}}
  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3">

    {{-- Email Timeline --}}
    @if($activeEmailThreadId && (!isset($activeThread) || ($activeThread['type'] ?? '') === 'email'))
      @if(!$activeEmailChannelId)
        <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2">
          Kein E-Mail Kanal ausgewählt.
        </div>
      @else
        @forelse($emailTimeline as $m)
          @php
            $isInbound = ($m['direction'] ?? '') === 'inbound';
            $from = (string) ($m['from'] ?? '');
            $to = (string) ($m['to'] ?? '');
            $subject = (string) ($m['subject'] ?? '');
            $body = trim((string) ($m['text'] ?? ''));
            if ($body === '' && !empty($m['html'])) {
                $body = trim(strip_tags((string) $m['html']));
            }
          @endphp
          <div class="rounded-lg border {{ $isInbound ? 'border-[var(--t-border)]/40' : 'border-[var(--t-accent)]/20' }} bg-white/[0.03] overflow-hidden">
            <div class="px-3 py-2 border-b border-[var(--t-border)]/30 {{ $isInbound ? 'bg-white/[0.02]' : 'bg-[var(--t-accent)]/[0.04]' }}">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <span class="text-[11px] font-semibold text-[var(--t-text)] truncate">{{ $subject ?: 'Ohne Betreff' }}</span>
                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-medium {{ $isInbound ? 'bg-white/10 text-[var(--t-text-muted)] border border-[var(--t-border)]/40' : 'bg-[var(--t-accent)]/10 text-[var(--t-accent)] border border-[var(--t-accent)]/20' }}">
                      {{ $isInbound ? 'Inbound' : 'Outbound' }}
                    </span>
                  </div>
                  <div class="text-[10px] text-[var(--t-text-muted)] truncate mt-0.5">
                    <span class="font-semibold">Von:</span> {{ $from ?: '—' }}
                    <span class="mx-0.5">·</span>
                    <span class="font-semibold">An:</span> {{ $to ?: '—' }}
                  </div>
                </div>
                <span class="text-[10px] text-[var(--t-text-muted)] whitespace-nowrap flex-shrink-0">{{ $m['at'] ?? '' }}</span>
              </div>
            </div>
            <div class="px-3 py-3 text-xs text-[var(--t-text)]/80 whitespace-pre-wrap">{{ $body }}</div>
            @if(!empty($m['attachments']))
              <div class="px-3 pb-2 flex flex-wrap gap-1.5">
                @foreach($m['attachments'] as $att)
                  <a href="{{ $att['url'] ?? '#' }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[10px] text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
                    <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                    {{ $att['filename'] ?? 'Anhang' }}
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        @empty
          <div class="text-xs text-[var(--t-text-muted)] text-center py-6">Noch keine Nachrichten im Thread.</div>
        @endforelse

        {{-- Forward Banner --}}
        @if($isForwarding)
          <div class="rounded-lg border border-blue-500/20 bg-blue-500/10 px-3 py-2 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3"/></svg>
            <div class="text-[11px] text-blue-300">
              <span class="font-semibold">Weiterleitung</span> von {{ $forwardingFrom }}
              @if($forwardingSubject) — {{ $forwardingSubject }} @endif
            </div>
            <button wire:click="cancelForward" class="ml-auto text-[10px] text-blue-400 hover:text-blue-300">Abbrechen</button>
          </div>
        @endif
      @endif
    @endif

    {{-- WhatsApp Timeline --}}
    @if($activeWhatsAppThreadId && (!isset($activeThread) || ($activeThread['type'] ?? '') === 'whatsapp'))
      @if(!$activeWhatsAppChannelId)
        <div class="text-xs text-amber-300 bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2">
          Kein WhatsApp Kanal ausgewählt.
        </div>
      @else
        {{-- 24h Window Indicator --}}
        <div class="flex items-center gap-1.5 flex-wrap text-[10px]">
          @if($whatsappWindowOpen)
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                  x-data="{
                    expiresAt: @js($whatsappWindowExpiresAt),
                    remaining: '',
                    interval: null,
                    update() {
                      if (!this.expiresAt) { this.remaining = ''; return; }
                      const diff = new Date(this.expiresAt) - new Date();
                      if (diff <= 0) { this.remaining = ''; clearInterval(this.interval); return; }
                      const h = Math.floor(diff / 3600000);
                      const m = Math.floor((diff % 3600000) / 60000);
                      this.remaining = h + 'h ' + String(m).padStart(2, '0') + 'min';
                    },
                    init() { this.update(); this.interval = setInterval(() => this.update(), 30000); },
                    destroy() { clearInterval(this.interval); }
                  }">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              Fenster offen
              <template x-if="remaining"><span class="text-emerald-500" x-text="'· ' + remaining"></span></template>
            </span>
          @else
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
              <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
              Nur Templates
            </span>
          @endif
        </div>

        {{-- Conversation Thread Selector --}}
        @if(!empty($conversationThreads))
          <div class="flex items-center gap-1 flex-wrap">
            <button wire:click="setActiveConversationThread(null)"
                    class="px-2 py-0.5 rounded-full text-[10px] font-medium border transition
                      {{ !$activeConversationThreadId ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/40 hover:text-[var(--t-text)]' }}">
              Alle
            </button>
            @foreach($conversationThreads as $ct)
              <button wire:click="setActiveConversationThread({{ intval($ct['id']) }})"
                      class="px-2 py-0.5 rounded-full text-[10px] font-medium border transition inline-flex items-center gap-1
                        {{ (int) $activeConversationThreadId === (int) $ct['id']
                            ? 'bg-[var(--t-accent)] text-white border-[var(--t-accent)]'
                            : ($ct['is_active']
                                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
                                : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/40 hover:text-[var(--t-text)]') }}"
                      title="{{ $ct['started_at'] }}{{ $ct['ended_at'] ? ' – ' . $ct['ended_at'] : ' (aktiv)' }}">
                @if($ct['is_active'])
                  <span class="w-1.5 h-1.5 rounded-full {{ (int) $activeConversationThreadId === (int) $ct['id'] ? 'bg-white' : 'bg-emerald-400' }}"></span>
                @endif
                {{ $ct['label'] }}
                <span class="{{ (int) $activeConversationThreadId === (int) $ct['id'] ? 'text-white/60' : 'text-[var(--t-text-muted)]/60' }}">({{ $ct['messages_count'] }})</span>
              </button>
            @endforeach
          </div>
        @endif

        {{-- Read-only archived indicator --}}
        @if($viewingConversationHistory && $activeConversationThreadId)
          <div class="rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
            <span class="text-[10px] text-amber-300 font-medium">Archivierter Thread (nur lesen)</span>
          </div>
        @endif

        {{-- WhatsApp Messages --}}
        <div class="space-y-2">
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
              <div class="max-w-[85%] rounded-xl px-3 py-2 {{ $isInbound ? 'bg-white/[0.06] border border-[var(--t-border)]/30' : 'bg-emerald-500/10 border border-emerald-500/20' }}">
                {{-- Sender label --}}
                <div class="flex items-center {{ $isInbound ? 'justify-start' : 'justify-end' }} gap-1.5 text-[9px] text-[var(--t-text-muted)]">
                  @if($isInbound)
                    <span>Extern</span>
                  @else
                    @if($messageType === 'template')
                      <span class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded border border-[var(--t-border)]/30 bg-white/5 text-[9px]">
                        <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        Template
                      </span>
                    @endif
                    <span>{{ $sentBy ?: 'Ich' }}</span>
                  @endif
                </div>

                {{-- Media --}}
                @if($hasMedia && !empty($attachments))
                  @foreach($attachments as $att)
                    @php
                      $attUrl = $att['url'] ?? null;
                      $attThumb = $att['thumbnail'] ?? $attUrl;
                      $attTitle = $att['title'] ?? 'Datei';
                    @endphp
                    @if($mediaDisplayType === 'image' && $attUrl)
                      <a href="{{ $attUrl }}" target="_blank" class="block my-1.5">
                        <img src="{{ $attThumb }}" alt="{{ $attTitle }}" class="rounded-lg max-w-full max-h-48 object-cover" loading="lazy" />
                      </a>
                    @elseif($mediaDisplayType === 'sticker' && $attUrl)
                      <div class="my-1.5"><img src="{{ $attUrl }}" alt="Sticker" class="w-24 h-24 object-contain" loading="lazy" /></div>
                    @elseif($mediaDisplayType === 'video' && $attUrl)
                      <div class="my-1.5">
                        <video controls preload="metadata" class="rounded-lg max-w-full max-h-48"><source src="{{ $attUrl }}" /></video>
                      </div>
                    @elseif(($mediaDisplayType === 'voice' || $mediaDisplayType === 'audio') && $attUrl)
                      <div class="my-1.5 flex items-center gap-2">
                        <audio controls preload="metadata" class="h-7 w-full min-w-[140px]"><source src="{{ $attUrl }}" /></audio>
                      </div>
                    @elseif($mediaDisplayType === 'document' && $attUrl)
                      <a href="{{ $attUrl }}" target="_blank" class="flex items-center gap-2 my-1.5 px-2 py-1.5 rounded-lg bg-white/5 border border-[var(--t-border)]/30 hover:bg-white/10 transition">
                        <svg class="w-4 h-4 text-[var(--t-text-muted)] flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span class="text-[11px] text-[var(--t-text)] truncate">{{ $attTitle }}</span>
                      </a>
                    @endif
                  @endforeach
                @endif

                {{-- Body --}}
                @if($body)
                  <div class="text-xs text-[var(--t-text)]/80 whitespace-pre-wrap mt-0.5">{{ $body }}</div>
                @endif

                {{-- Reactions --}}
                @if(!empty($reactions))
                  <div class="flex gap-0.5 mt-0.5">@foreach($reactions as $r)<span class="text-xs">{{ $r['emoji'] }}</span>@endforeach</div>
                @endif

                {{-- Timestamp + Status --}}
                <div class="mt-0.5 flex items-center {{ $isInbound ? '' : 'justify-end' }} gap-1 text-[9px] text-[var(--t-text-muted)]">
                  <span title="{{ $fullAt }}">{{ $at }}</span>
                  @if(!$isInbound)
                    @if($status === 'read')
                      <span class="text-blue-400">✓✓</span>
                    @elseif($status === 'delivered')
                      <span>✓✓</span>
                    @elseif($status === 'sent')
                      <span>✓</span>
                    @elseif($status === 'failed')
                      <span class="text-red-400">✕</span>
                    @endif
                  @endif
                </div>
              </div>
            </div>
          @empty
            <div class="text-xs text-[var(--t-text-muted)] text-center py-6">Noch keine Nachrichten im Thread.</div>
          @endforelse
        </div>
      @endif
    @endif

    {{-- No thread selected --}}
    @if(!$activeEmailThreadId && !$activeWhatsAppThreadId)
      <div class="flex-1 flex items-center justify-center py-12">
        <div class="text-center">
          <svg class="w-8 h-8 mx-auto text-[var(--t-text-muted)] opacity-20 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
          <p class="text-xs font-medium text-[var(--t-text)]">Comms</p>
          <p class="text-[10px] text-[var(--t-text-muted)] mt-1">Thread in der Sidebar auswählen oder neue Nachricht erstellen.</p>
        </div>
      </div>
    @endif
  </div>

  {{-- Compose Area (sticky bottom) --}}
  @if($activeEmailThreadId || $activeWhatsAppThreadId)
    <div class="border-t border-[var(--t-border)]/40 px-4 py-3 flex-shrink-0 bg-white/[0.02]"
         x-data="{ autoGrow(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; } }">

      {{-- Email Compose --}}
      @if($activeEmailThreadId)
        <div class="space-y-2">
          @if(!$activeEmailThreadId)
            <div class="grid grid-cols-2 gap-2">
              <input type="text" wire:model.live="emailCompose.to" placeholder="An..." class="px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
              <input type="text" wire:model.live="emailCompose.subject" placeholder="Betreff..." class="px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
            </div>
          @endif
          <div class="flex gap-2 items-end">
            <textarea
              x-ref="emailBody"
              x-init="$nextTick(() => autoGrow($refs.emailBody))"
              @input="autoGrow($event.target)"
              @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendEmail(); }"
              rows="1"
              wire:model="emailCompose.body"
              class="flex-1 px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] resize-none"
              placeholder="Antwort..."
            ></textarea>
            <button wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail"
                    class="px-3 py-1.5 rounded-md text-xs font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-50">
              <span wire:loading.remove wire:target="sendEmail">Senden</span>
              <span wire:loading wire:target="sendEmail">...</span>
            </button>
          </div>
          @error('emailCompose.body')
            <div class="text-[10px] text-red-400">{{ $message }}</div>
          @enderror
          @if($emailMessage)
            <div class="text-[10px] text-[var(--t-text-muted)]">{{ $emailMessage }}</div>
          @endif
        </div>
      @endif

      {{-- WhatsApp Compose --}}
      @if($activeWhatsAppThreadId)
        <div class="space-y-2">
          @if($viewingConversationHistory)
            <div class="flex items-center gap-2 px-3 py-2 rounded-md bg-white/5 border border-[var(--t-border)]/30">
              <svg class="w-3.5 h-3.5 text-[var(--t-text-muted)] flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
              <span class="text-[10px] text-[var(--t-text-muted)]">Archivierter Thread — wechsle zum aktiven Thread.</span>
            </div>
          @elseif($whatsappWindowOpen)
            <div class="flex gap-2 items-end">
              <textarea
                x-ref="waBody"
                x-init="$nextTick(() => autoGrow($refs.waBody))"
                @input="autoGrow($event.target)"
                @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendWhatsApp(); }"
                rows="1"
                wire:model="whatsappCompose.body"
                class="flex-1 px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] resize-none"
                placeholder="Nachricht..."
              ></textarea>
              <button wire:click="sendWhatsApp" wire:loading.attr="disabled" wire:target="sendWhatsApp"
                      class="px-3 py-1.5 rounded-md text-xs font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="sendWhatsApp">Senden</span>
                <span wire:loading wire:target="sendWhatsApp">...</span>
              </button>
            </div>
          @else
            {{-- Closed window: Template mode --}}
            <div class="rounded-md border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-[10px] text-amber-300">
              <span class="font-semibold">24h-Fenster geschlossen.</span> Nur Templates möglich.
            </div>
            @if(!empty($whatsappTemplates))
              <div>
                <select wire:model.live="whatsappSelectedTemplateId"
                        class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
                  <option value="">— Template wählen —</option>
                  @foreach($whatsappTemplates as $tpl)
                    <option value="{{ $tpl['id'] }}">{{ $tpl['label'] ?? $tpl['name'] ?? '' }}</option>
                  @endforeach
                </select>
              </div>
              @if(!empty($whatsappTemplatePreview))
                <div class="rounded-md border border-[var(--t-border)]/30 bg-white/[0.03] p-2 space-y-2">
                  <div class="text-[10px] font-semibold text-[var(--t-text)]">{{ $whatsappTemplatePreview['name'] ?? '' }}</div>
                  <div class="rounded-md bg-emerald-500/10 px-2 py-1.5 text-xs text-[var(--t-text)]/80 whitespace-pre-wrap">{{ $this->getTemplatePreviewText() }}</div>
                  @if(($whatsappTemplatePreview['variables_count'] ?? 0) > 0)
                    <div class="space-y-1">
                      @for($i = 1; $i <= $whatsappTemplatePreview['variables_count']; $i++)
                        <div class="flex items-center gap-2">
                          <span class="text-[10px] text-[var(--t-text-muted)] font-mono w-8 flex-shrink-0">&#123;&#123;{{ $i }}&#125;&#125;</span>
                          <input type="text" wire:model.live="whatsappTemplateVariables.{{ $i }}"
                                 class="flex-1 px-2 py-1 text-xs rounded bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]"
                                 placeholder="Wert..." />
                        </div>
                      @endfor
                    </div>
                  @endif
                  <div class="flex justify-end">
                    <button wire:click="sendWhatsAppTemplate" wire:loading.attr="disabled" wire:target="sendWhatsAppTemplate"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-50">
                      <span wire:loading.remove wire:target="sendWhatsAppTemplate">Template senden</span>
                      <span wire:loading wire:target="sendWhatsAppTemplate">...</span>
                    </button>
                  </div>
                </div>
              @endif
            @else
              <div class="text-[10px] text-[var(--t-text-muted)]">Keine Templates verfügbar.</div>
            @endif
          @endif
          @error('whatsappCompose.body')
            <div class="text-[10px] text-red-400">{{ $message }}</div>
          @enderror
          @if($whatsappMessage)
            <div class="text-[10px] text-[var(--t-text-muted)]">{{ $whatsappMessage }}</div>
          @endif
        </div>
      @endif
    </div>
  @endif
</div>
