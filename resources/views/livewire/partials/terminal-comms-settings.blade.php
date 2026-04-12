{{-- ═══ Comms: Settings View ═══ --}}
<div class="flex-1 min-h-0 flex flex-col">

  {{-- Header --}}
  <div class="px-4 py-2.5 border-b border-[var(--t-border)]/40 flex items-center gap-3 flex-shrink-0 bg-white/[0.02]">
    <button wire:click="commsBackToTimeline" class="w-6 h-6 rounded-md flex items-center justify-center text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/10 transition">
      <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
    </button>
    <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
    <div>
      <span class="text-xs font-semibold text-[var(--t-text)]">Einstellungen</span>
      @if($rootTeamName)
        <span class="text-[10px] text-[var(--t-text-muted)]/60 ml-1.5">{{ $rootTeamName }}</span>
      @endif
    </div>
  </div>

  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-5">

    {{-- ── Postmark Connection ── --}}
    <div class="rounded-xl border border-[var(--t-border)]/25 bg-white/[0.02] overflow-hidden">
      <div class="px-4 py-2.5 border-b border-[var(--t-border)]/20 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-lg bg-blue-500/15 flex items-center justify-center">
            <svg class="w-3.5 h-3.5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
          </div>
          <span class="text-[11px] font-semibold text-[var(--t-text)]">Postmark</span>
        </div>
        @if($postmarkConfigured)
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Verbunden
          </span>
        @else
          <span class="text-[9px] text-[var(--t-text-muted)]/50">Nicht konfiguriert</span>
        @endif
      </div>

      <div class="p-4 space-y-3">
        @if($postmarkMessage)
          <div class="text-[10px] text-emerald-400 font-medium">{{ $postmarkMessage }}</div>
        @endif

        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Server Token</label>
          <input type="password" wire:model.defer="postmark.server_token"
                 placeholder="{{ $postmarkConfigured ? '****** (neu setzen)' : 'postmark server token' }}"
                 @if(!$this->canManageProviderConnections()) disabled @endif
                 class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 disabled:opacity-30 transition" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Inbound User</label>
            <input type="text" wire:model.defer="postmark.inbound_user" placeholder="optional"
                   @if(!$this->canManageProviderConnections()) disabled @endif
                   class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 disabled:opacity-30 transition" />
          </div>
          <div>
            <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Inbound Pass</label>
            <input type="password" wire:model.defer="postmark.inbound_pass" placeholder="optional"
                   @if(!$this->canManageProviderConnections()) disabled @endif
                   class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 disabled:opacity-30 transition" />
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Signing Secret</label>
          <input type="password" wire:model.defer="postmark.signing_secret" placeholder="optional"
                 @if(!$this->canManageProviderConnections()) disabled @endif
                 class="w-full px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 disabled:opacity-30 transition" />
        </div>
        <div class="flex justify-end">
          <button wire:click="savePostmarkConnection" wire:loading.attr="disabled"
                  @if(!$this->canManageProviderConnections()) disabled @endif
                  class="px-4 py-2 rounded-lg text-xs font-semibold bg-blue-500 text-white hover:bg-blue-600 transition disabled:opacity-30 shadow-sm shadow-blue-500/20">
            Speichern
          </button>
        </div>

        {{-- Domains --}}
        <div class="border-t border-[var(--t-border)]/20 pt-3 space-y-2">
          <span class="text-[10px] font-semibold text-[var(--t-text)] uppercase tracking-wider">Domains</span>

          @if($postmarkDomainMessage)
            <div class="text-[10px] text-emerald-400 font-medium">{{ $postmarkDomainMessage }}</div>
          @endif

          @if(!$postmarkConfigured)
            <div class="text-[10px] text-[var(--t-text-muted)]/50">Bitte zuerst Postmark speichern.</div>
          @else
            <div class="space-y-1">
              @forelse($postmarkDomains as $d)
                <div class="flex items-center justify-between gap-2 rounded-lg bg-white/[0.03] border border-[var(--t-border)]/20 px-3 py-2">
                  <div class="flex items-center gap-1.5 min-w-0">
                    <span class="text-xs font-semibold text-[var(--t-text)] truncate">{{ $d['domain'] }}</span>
                    @if($d['is_primary'])
                      <span class="px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider rounded bg-[var(--t-accent)]/10 text-[var(--t-accent)] border border-[var(--t-accent)]/20">Primary</span>
                    @endif
                    @if($d['is_verified'])
                      <span class="px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Verified</span>
                    @endif
                  </div>
                  <div class="flex items-center gap-1 flex-shrink-0">
                    @if(!$d['is_primary'])
                      <button wire:click="setPostmarkPrimaryDomain({{ intval($d['id']) }})"
                              @if(!$this->canManageProviderConnections()) disabled @endif
                              class="text-[9px] px-2 py-1 rounded-md border border-[var(--t-border)]/25 text-[var(--t-text-muted)] hover:text-[var(--t-text)] hover:bg-white/5 transition disabled:opacity-30">
                        Primary
                      </button>
                    @endif
                    <button wire:click="removePostmarkDomain({{ intval($d['id']) }})"
                            @if(!$this->canManageProviderConnections()) disabled @endif
                            class="text-[9px] px-2 py-1 rounded-md border border-[var(--t-border)]/25 text-[var(--t-text-muted)] hover:text-red-400 hover:border-red-400/30 transition disabled:opacity-30">
                      Löschen
                    </button>
                  </div>
                </div>
              @empty
                <div class="text-[10px] text-[var(--t-text-muted)]/50">Keine Domains.</div>
              @endforelse
            </div>

            <div class="flex items-center gap-2 mt-2">
              <input type="text" wire:model.defer="postmarkNewDomain.domain" placeholder="z.B. company.de"
                     @if(!$this->canManageProviderConnections()) disabled @endif
                     class="flex-1 px-3 py-2 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 disabled:opacity-30 transition" />
              <label class="flex items-center gap-1 text-[10px] text-[var(--t-text-muted)] flex-shrink-0">
                <input type="checkbox" wire:model.defer="postmarkNewDomain.is_primary"
                       @if(!$this->canManageProviderConnections()) disabled @endif
                       class="rounded border-[var(--t-border)] bg-white/5" />
                Primary
              </label>
              <button wire:click="addPostmarkDomain"
                      @if(!$this->canManageProviderConnections()) disabled @endif
                      class="px-3 py-2 rounded-lg text-[10px] font-semibold bg-blue-500 text-white hover:bg-blue-600 transition disabled:opacity-30 flex-shrink-0">
                Hinzufügen
              </button>
            </div>
          @endif
        </div>

        @if(!$this->canManageProviderConnections())
          <div class="text-[10px] text-[var(--t-text-muted)]/40 flex items-center gap-1.5">
            <svg class="w-3 h-3 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            Nur Owner/Admin des Root-Teams kann Postmark konfigurieren.
          </div>
        @endif
      </div>
    </div>

    {{-- ── Channels ── --}}
    <div class="rounded-xl border border-[var(--t-border)]/25 bg-white/[0.02] overflow-hidden">
      <div class="px-4 py-2.5 border-b border-[var(--t-border)]/20 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-[var(--t-text-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
          <span class="text-[11px] font-semibold text-[var(--t-text)]">Kanäle</span>
        </div>
        <span class="text-[9px] text-[var(--t-text-muted)]/50">{{ count($commsSettingsChannels) }} gesamt</span>
      </div>

      <div class="p-4 space-y-3">
        @if($channelsMessage)
          <div class="text-[10px] text-emerald-400 font-medium">{{ $channelsMessage }}</div>
        @endif

        {{-- New Channel Form --}}
        <div class="rounded-lg border border-dashed border-[var(--t-border)]/30 bg-white/[0.01] p-3 space-y-2.5">
          <span class="text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider">Neuer Kanal</span>
          {{-- Type & Visibility as badge toggles --}}
          <div class="space-y-2.5">
            <div>
              <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-1">Typ</label>
              <div class="flex gap-1.5" x-data="{ type: $wire.entangle('newChannel.type') }">
                <button @click="type = 'email'" type="button"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-semibold transition border"
                        :class="type === 'email' ? 'bg-blue-500/15 text-blue-400 border-blue-500/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/8'">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                  E-Mail
                </button>
                <button @click="type = 'whatsapp'" type="button"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-semibold transition border"
                        :class="type === 'whatsapp' ? 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/8'">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                  WhatsApp
                </button>
                <div class="flex-1 flex items-center justify-center px-2.5 py-1.5 text-[10px] rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text-muted)]/50" x-text="type === 'email' ? 'Postmark' : 'Meta'"></div>
              </div>
            </div>
            <div>
              <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-1">Sichtbarkeit</label>
              <div class="flex gap-1.5" x-data="{ vis: $wire.entangle('newChannel.visibility') }">
                <button @click="vis = 'private'" type="button"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-semibold transition border"
                        :class="vis === 'private' ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] border-[var(--t-accent)]/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/8'">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                  Privat
                </button>
                <button @click="vis = 'team'" type="button"
                        class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-semibold transition border"
                        :class="vis === 'team' ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] border-[var(--t-accent)]/30' : 'bg-white/5 text-[var(--t-text-muted)] border-[var(--t-border)]/30 hover:bg-white/8'">
                  <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                  Teamweit
                </button>
              </div>
            </div>
          </div>

          @if($newChannel['type'] === 'email')
            <div class="grid grid-cols-3 gap-2">
              <div>
                <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-0.5">Local-Part</label>
                <input type="text" wire:model.defer="newChannel.sender_local_part" placeholder="z.B. sales"
                       class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 transition" />
              </div>
              <div>
                <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-0.5">Domain</label>
                @if(empty($postmarkDomains))
                  <div class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text-muted)]/30">(Keine Domains)</div>
                @else
                  <div x-data="{
                    open: false,
                    options: @js(collect($postmarkDomains)->pluck('domain')->values()->all()),
                    get selectedLabel() {
                      return $wire.newChannel?.sender_domain || '(Domain)';
                    },
                    select(d) { $wire.set('newChannel.sender_domain', d); this.open = false; }
                  }" @click.outside="open = false" class="relative">
                    <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] hover:bg-white/8 hover:border-[var(--t-border)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 transition cursor-pointer">
                      <span x-text="selectedLabel" class="truncate" :class="!$wire.newChannel?.sender_domain && 'text-[var(--t-text-muted)]/50'"></span>
                      <svg class="w-3 h-3 text-[var(--t-text-muted)]/50 transition-transform duration-150" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg bg-[var(--t-glass-surface)] backdrop-blur-xl border border-[var(--t-border-bright)] shadow-xl shadow-black/30 max-h-36 overflow-auto py-1" style="display: none;">
                      <template x-for="d in options" :key="d">
                        <button @click="select(d)" type="button"
                                class="w-full text-left px-2.5 py-1.5 text-xs transition"
                                :class="$wire.newChannel?.sender_domain === d ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] font-medium' : 'text-[var(--t-text)] hover:bg-white/8'">
                          <span x-text="d"></span>
                        </button>
                      </template>
                    </div>
                  </div>
                @endif
              </div>
              <div>
                <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-0.5">Name</label>
                <input type="text" wire:model.defer="newChannel.name" placeholder="optional"
                       class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-blue-500/50 transition" />
              </div>
            </div>
            <div class="flex justify-end">
              <button wire:click="createChannel" wire:loading.attr="disabled"
                      @if(empty($postmarkDomains)) disabled @endif
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-semibold bg-blue-500 text-white hover:bg-blue-600 transition disabled:opacity-30 shadow-sm shadow-blue-500/20">
                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                E-Mail Kanal
              </button>
            </div>
          @else
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-0.5">WhatsApp Account</label>
                @if(empty($availableWhatsAppAccounts))
                  <div class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text-muted)]/30">(Keine Accounts)</div>
                @else
                  <div x-data="{
                    open: false,
                    options: @js(collect($availableWhatsAppAccounts)->map(fn($a) => ['id' => $a['id'], 'label' => $a['label']])->values()->all()),
                    get selectedLabel() {
                      const v = String($wire.newChannel?.whatsapp_account_id || '');
                      if (!v) return '(Account)';
                      const opt = this.options.find(o => String(o.id) === v);
                      return opt ? opt.label : '(Account)';
                    },
                    select(id) { $wire.set('newChannel.whatsapp_account_id', id); this.open = false; }
                  }" @click.outside="open = false" class="relative">
                    <button @click="open = !open" type="button"
                            class="w-full flex items-center justify-between px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] hover:bg-white/8 hover:border-[var(--t-border)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]/50 transition cursor-pointer">
                      <span x-text="selectedLabel" class="truncate" :class="!$wire.newChannel?.whatsapp_account_id && 'text-[var(--t-text-muted)]/50'"></span>
                      <svg class="w-3 h-3 text-[var(--t-text-muted)]/50 transition-transform duration-150" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-transition class="absolute z-50 mt-1 w-full rounded-lg bg-[var(--t-glass-surface)] backdrop-blur-xl border border-[var(--t-border-bright)] shadow-xl shadow-black/30 max-h-36 overflow-auto py-1" style="display: none;">
                      <template x-for="opt in options" :key="opt.id">
                        <button @click="select(opt.id)" type="button"
                                class="w-full text-left px-2.5 py-1.5 text-xs transition"
                                :class="String($wire.newChannel?.whatsapp_account_id) === String(opt.id) ? 'bg-[var(--t-accent)]/15 text-[var(--t-accent)] font-medium' : 'text-[var(--t-text)] hover:bg-white/8'">
                          <span x-text="opt.label"></span>
                        </button>
                      </template>
                    </div>
                  </div>
                @endif
              </div>
              <div>
                <label class="block text-[9px] text-[var(--t-text-muted)]/60 mb-0.5">Name</label>
                <input type="text" wire:model.defer="newChannel.name" placeholder="optional"
                       class="w-full px-2.5 py-1.5 text-xs rounded-lg bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-emerald-500/50 transition" />
              </div>
            </div>
            <div class="flex justify-end">
              <button wire:click="createChannel" wire:loading.attr="disabled"
                      @if(empty($availableWhatsAppAccounts)) disabled @endif
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition disabled:opacity-30 shadow-sm shadow-emerald-600/20">
                <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                WhatsApp Kanal
              </button>
            </div>
          @endif
        </div>

        {{-- Existing Channels --}}
        <div class="space-y-1">
          <span class="text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider">Vorhandene Kanäle</span>
          @forelse($commsSettingsChannels as $c)
            <div class="flex items-center justify-between gap-2 rounded-lg bg-white/[0.03] border border-[var(--t-border)]/20 px-3 py-2 group">
              <div class="flex items-center gap-2 min-w-0">
                @if($c['type'] === 'whatsapp')
                  <div class="w-5 h-5 rounded bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                  </div>
                @else
                  <div class="w-5 h-5 rounded bg-blue-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-2.5 h-2.5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                  </div>
                @endif
                <div class="min-w-0">
                  <div class="text-xs font-semibold text-[var(--t-text)] truncate">{{ $c['sender_identifier'] }}</div>
                  <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-[9px] text-[var(--t-text-muted)]/50">{{ $c['visibility'] === 'team' ? 'Teamweit' : 'Privat' }}</span>
                    @if(!$c['is_active'])
                      <span class="text-[9px] text-amber-400">Inaktiv</span>
                    @endif
                  </div>
                </div>
              </div>
              <button wire:click="removeChannel({{ intval($c['id']) }})"
                      class="text-[9px] px-2 py-1 rounded-md border border-transparent text-[var(--t-text-muted)]/40 opacity-0 group-hover:opacity-100 hover:text-red-400 hover:border-red-400/30 hover:bg-red-400/5 transition flex-shrink-0">
                Löschen
              </button>
            </div>
          @empty
            <div class="text-[10px] text-[var(--t-text-muted)]/50 py-3 text-center">Noch keine Kanäle angelegt.</div>
          @endforelse
        </div>
      </div>
    </div>

  </div>
</div>
