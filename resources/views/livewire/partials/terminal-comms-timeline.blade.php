{{-- ═══ Comms: Timeline View ═══ --}}
<div class="flex-1 min-h-0 flex flex-col">

  {{-- Active Thread Header --}}
  @if($activeContextThreadIndex !== null && isset($allContextThreads[$activeContextThreadIndex]))
    @php $activeThread = $allContextThreads[$activeContextThreadIndex]; @endphp
    <div class="px-4 py-2.5 border-b border-[var(--t-border)]/40 flex items-center gap-3 flex-shrink-0 bg-white/[0.02]">
      @if($activeThread['type'] === 'email')
        <div class="w-7 h-7 rounded-lg bg-blue-500/15 text-blue-400 flex items-center justify-center flex-shrink-0">
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
        </div>
      @else
        <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center flex-shrink-0">
          <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
        </div>
      @endif
      <div class="flex-1 min-w-0">
        <div class="text-[11px] font-semibold text-[var(--t-text)] truncate">{{ $activeThread['label'] }}</div>
        <div class="text-[10px] text-[var(--t-text-muted)] truncate">{{ $activeThread['counterpart'] }}@if($activeThread['channel_label']) · {{ $activeThread['channel_label'] }}@endif</div>
      </div>
      @if($activeThread['type'] === 'whatsapp')
        @if($whatsappWindowOpen)
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex-shrink-0"
                x-data="{
                  expiresAt: @js($whatsappWindowExpiresAt),
                  remaining: '',
                  interval: null,
                  update() {
                    if (!this.expiresAt) { this.remaining = ''; return; }
                    const diff = new Date(this.expiresAt) - new Date();
                    if (diff <= 0) { this.remaining = 'abgelaufen'; clearInterval(this.interval); return; }
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    this.remaining = h + 'h ' + String(m).padStart(2, '0') + 'min';
                  },
                  init() { this.update(); this.interval = setInterval(() => this.update(), 30000); },
                  destroy() { clearInterval(this.interval); }
                }">
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

  {{-- Timeline Scroll Area --}}
  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3"
       x-ref="commsTimeline"
       x-data="{ scrollToBottom() { this.$refs.commsTimeline.scrollTop = this.$refs.commsTimeline.scrollHeight; } }"
       x-init="$nextTick(() => scrollToBottom())"
       @comms-scroll-bottom.window="$nextTick(() => scrollToBottom())">

    {{-- ════ Email Timeline ════ --}}
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
            $body = trim((string) ($m['text'] ?? ''));
            if ($body === '' && !empty($m['html'])) {
                $body = trim(strip_tags((string) $m['html']));
            }
          @endphp
          <div class="group rounded-lg border {{ $isInbound ? 'border-[var(--t-border)]/30 bg-white/[0.02]' : 'border-blue-500/15 bg-blue-500/[0.03]' }} overflow-hidden transition hover:border-[var(--t-border)]/50">
            {{-- Message Header --}}
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
                <div class="text-[10px] text-[var(--t-text-muted)] mt-0.5 flex items-center gap-1">
                  <span class="truncate">{{ $isInbound ? $from : $to }}</span>
                </div>
              </div>
              <span class="text-[9px] text-[var(--t-text-muted)]/60 whitespace-nowrap flex-shrink-0 pt-0.5">{{ $m['at'] ?? '' }}</span>
            </div>
            {{-- Body --}}
            <div class="px-3 pb-2.5 text-xs text-[var(--t-text)]/75 whitespace-pre-wrap leading-relaxed">{{ $body }}</div>
            {{-- Attachments --}}
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

    {{-- ════ WhatsApp Timeline ════ --}}
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
            <button wire:click="setActiveConversationThread(null)"
                    class="px-2.5 py-1 rounded-full text-[10px] font-semibold border transition
                      {{ !$activeConversationThreadId ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:text-[var(--t-text)] hover:bg-white/10' }}">
              Alle
            </button>
            @foreach($conversationThreads as $ct)
              <button wire:click="setActiveConversationThread({{ intval($ct['id']) }})"
                      class="px-2.5 py-1 rounded-full text-[10px] font-semibold border transition inline-flex items-center gap-1
                        {{ (int) $activeConversationThreadId === (int) $ct['id']
                            ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'
                            : ($ct['is_active']
                                ? 'bg-emerald-500/5 text-emerald-400/70 border-emerald-500/15 hover:bg-emerald-500/10'
                                : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:text-[var(--t-text)] hover:bg-white/10') }}"
                      title="{{ $ct['started_at'] }}{{ $ct['ended_at'] ? ' – ' . $ct['ended_at'] : ' (aktiv)' }}">
                @if($ct['is_active'])
                  <span class="w-1.5 h-1.5 rounded-full {{ (int) $activeConversationThreadId === (int) $ct['id'] ? 'bg-emerald-400' : 'bg-emerald-400/60' }} animate-pulse"></span>
                @endif
                {{ $ct['label'] }}
                <span class="opacity-50">({{ $ct['messages_count'] }})</span>
              </button>
            @endforeach
          </div>
        @endif

        {{-- Archived indicator --}}
        @if($viewingConversationHistory && $activeConversationThreadId)
          <div class="rounded-lg border border-amber-500/20 bg-amber-500/[0.06] px-3 py-2 flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0-3-3m3 3 3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
            <span class="text-[10px] text-amber-300 font-medium">Archivierter Thread — nur lesen</span>
          </div>
        @endif

        {{-- WhatsApp Messages --}}
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
              <div class="max-w-[80%] rounded-2xl px-3 py-2 {{ $isInbound
                  ? 'bg-white/[0.06] border border-[var(--t-border)]/25 rounded-tl-md'
                  : 'bg-emerald-500/10 border border-emerald-500/15 rounded-tr-md' }}">

                {{-- Media --}}
                @if($hasMedia && !empty($attachments))
                  @foreach($attachments as $att)
                    @php
                      $attUrl = $att['url'] ?? null;
                      $attThumb = $att['thumbnail'] ?? $attUrl;
                      $attTitle = $att['title'] ?? 'Datei';
                    @endphp
                    @if($mediaDisplayType === 'image' && $attUrl)
                      <a href="{{ $attUrl }}" target="_blank" class="block mb-1.5">
                        <img src="{{ $attThumb }}" alt="{{ $attTitle }}" class="rounded-xl max-w-full max-h-48 object-cover" loading="lazy" />
                      </a>
                    @elseif($mediaDisplayType === 'sticker' && $attUrl)
                      <div class="mb-1.5"><img src="{{ $attUrl }}" alt="Sticker" class="w-24 h-24 object-contain" loading="lazy" /></div>
                    @elseif($mediaDisplayType === 'video' && $attUrl)
                      <div class="mb-1.5">
                        <video controls preload="metadata" class="rounded-xl max-w-full max-h-48"><source src="{{ $attUrl }}" /></video>
                      </div>
                    @elseif(($mediaDisplayType === 'voice' || $mediaDisplayType === 'audio') && $attUrl)
                      <div class="mb-1.5">
                        <audio controls preload="metadata" class="h-8 w-full min-w-[160px]"><source src="{{ $attUrl }}" /></audio>
                      </div>
                    @elseif($mediaDisplayType === 'document' && $attUrl)
                      <a href="{{ $attUrl }}" target="_blank" class="flex items-center gap-2 mb-1.5 px-2.5 py-2 rounded-xl bg-white/5 border border-[var(--t-border)]/25 hover:bg-white/10 transition">
                        <svg class="w-4 h-4 text-[var(--t-text-muted)] flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <span class="text-[11px] text-[var(--t-text)] truncate">{{ $attTitle }}</span>
                      </a>
                    @endif
                  @endforeach
                @endif

                {{-- Template badge --}}
                @if(!$isInbound && $messageType === 'template')
                  <div class="flex items-center gap-1 mb-0.5">
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                      <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                      Template
                    </span>
                  </div>
                @endif

                {{-- Body --}}
                @if($body)
                  <div class="text-[12px] text-[var(--t-text)]/85 whitespace-pre-wrap leading-relaxed">{{ $body }}</div>
                @endif

                {{-- Reactions --}}
                @if(!empty($reactions))
                  <div class="flex gap-0.5 mt-1">@foreach($reactions as $r)<span class="text-sm">{{ $r['emoji'] }}</span>@endforeach</div>
                @endif

                {{-- Timestamp + Status --}}
                <div class="mt-1 flex items-center {{ $isInbound ? '' : 'justify-end' }} gap-1 text-[9px] text-[var(--t-text-muted)]/50">
                  <span title="{{ $fullAt }}">{{ $at }}</span>
                  @if(!$isInbound)
                    @if($sentBy)
                      <span class="opacity-70">· {{ $sentBy }}</span>
                    @endif
                    @if($status === 'read')
                      <span class="text-blue-400 ml-0.5">✓✓</span>
                    @elseif($status === 'delivered')
                      <span class="ml-0.5">✓✓</span>
                    @elseif($status === 'sent')
                      <span class="ml-0.5">✓</span>
                    @elseif($status === 'failed')
                      <span class="text-red-400 ml-0.5">✕</span>
                    @endif
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
        <div class="text-center space-y-3">
          <div class="w-12 h-12 mx-auto rounded-2xl bg-[var(--t-accent)]/10 flex items-center justify-center">
            <svg class="w-6 h-6 text-[var(--t-accent)]/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
          </div>
          <div>
            <p class="text-xs font-semibold text-[var(--t-text)]">Comms</p>
            <p class="text-[10px] text-[var(--t-text-muted)] mt-0.5">Thread in der Sidebar auswählen<br>oder neue Nachricht erstellen.</p>
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- ════ Compose Area (sticky bottom) — matches Chat input style ════ --}}
  @if($activeEmailThreadId || $activeWhatsAppThreadId)
    <div class="border-t border-[var(--t-border)]/60 flex-shrink-0"
         x-data="{ autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'; } }">

      {{-- Status messages --}}
      @if($emailMessage || $whatsappMessage)
        <div class="px-4 pt-2 pb-0">
          @if($emailMessage)
            <div class="text-[10px] text-emerald-400">{{ $emailMessage }}</div>
          @endif
          @if($whatsappMessage)
            <div class="text-[10px] text-emerald-400">{{ $whatsappMessage }}</div>
          @endif
        </div>
      @endif
      @error('emailCompose.body')
        <div class="px-4 pt-2 pb-0 text-[10px] text-red-400">{{ $message }}</div>
      @enderror
      @error('whatsappCompose.body')
        <div class="px-4 pt-2 pb-0 text-[10px] text-red-400">{{ $message }}</div>
      @enderror

      <div class="px-4 py-2.5">

        {{-- Email Compose --}}
        @if($activeEmailThreadId)
          <div class="flex items-end gap-2">
            <div class="flex-1 min-w-0 rounded-lg border transition-all border-[var(--t-border)]/80 focus-within:border-[var(--t-accent)]/50 focus-within:shadow-[0_0_0_1px_var(--t-accent)]">
              <textarea
                x-ref="emailBody"
                x-init="$nextTick(() => autoGrow($refs.emailBody))"
                @input="autoGrow($event.target)"
                @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendEmail(); }"
                rows="1"
                wire:model="emailCompose.body"
                class="w-full px-3 py-2 text-xs bg-transparent text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none resize-none"
                placeholder="Antworten..."
              ></textarea>
            </div>
            <button
              wire:click="sendEmail" wire:loading.attr="disabled" wire:target="sendEmail"
              class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0 bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
            >
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
              <span class="text-[10px] text-[var(--t-text-muted)]">Archiviert — wechsle zum aktiven Thread zum Antworten.</span>
            </div>
          @elseif($whatsappWindowOpen)
            <div class="flex items-end gap-2">
              <div class="flex-1 min-w-0 rounded-lg border transition-all border-[var(--t-border)]/80 focus-within:border-[var(--t-accent)]/50 focus-within:shadow-[0_0_0_1px_var(--t-accent)]">
                <textarea
                  x-ref="waBody"
                  x-init="$nextTick(() => autoGrow($refs.waBody))"
                  @input="autoGrow($event.target)"
                  @keydown.enter="if(!$event.shiftKey){ $event.preventDefault(); $wire.sendWhatsApp(); }"
                  rows="1"
                  wire:model="whatsappCompose.body"
                  class="w-full px-3 py-2 text-xs bg-transparent text-[var(--t-text)] placeholder-[var(--t-text-muted)]/50 focus:outline-none resize-none"
                  placeholder="Nachricht..."
                ></textarea>
              </div>
              <button
                wire:click="sendWhatsApp" wire:loading.attr="disabled" wire:target="sendWhatsApp"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0 bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <svg class="w-4 h-4" wire:loading.remove wire:target="sendWhatsApp" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="sendWhatsApp" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
              </button>
            </div>
          @else
            {{-- Closed window: Template mode --}}
            <div class="space-y-2">
              <div class="rounded-lg border border-amber-500/20 bg-amber-500/[0.06] px-3 py-2 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-amber-400 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <span class="text-[10px] text-amber-300 font-medium">24h-Fenster geschlossen — nur Templates möglich.</span>
              </div>
              @if(!empty($whatsappTemplates))
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
                    </div>
                    <div class="rounded-lg bg-white/[0.04] px-2.5 py-2 text-xs text-[var(--t-text)]/80 whitespace-pre-wrap">{{ $this->getTemplatePreviewText() }}</div>
                    @if(($whatsappTemplatePreview['variables_count'] ?? 0) > 0)
                      <div class="space-y-1">
                        @for($i = 1; $i <= $whatsappTemplatePreview['variables_count']; $i++)
                          <div class="flex items-center gap-2">
                            <span class="text-[10px] text-emerald-400/70 font-mono w-6 flex-shrink-0 text-right">&#123;&#123;{{ $i }}&#125;&#125;</span>
                            <input type="text" wire:model.live="whatsappTemplateVariables.{{ $i }}"
                                   class="flex-1 px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-emerald-500/50 placeholder-[var(--t-text-muted)]/50"
                                   placeholder="Wert..." />
                          </div>
                        @endfor
                      </div>
                    @endif
                    <div class="flex justify-end">
                      <button wire:click="sendWhatsAppTemplate" wire:loading.attr="disabled" wire:target="sendWhatsAppTemplate"
                              class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs transition flex-shrink-0 bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 shadow-sm disabled:opacity-40">
                        <svg class="w-4 h-4" wire:loading.remove wire:target="sendWhatsAppTemplate" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
                        <svg class="w-4 h-4 animate-spin" wire:loading wire:target="sendWhatsAppTemplate" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                      </button>
                    </div>
                  </div>
                @endif
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
