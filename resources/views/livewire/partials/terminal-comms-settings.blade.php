{{-- ═══ Comms: Settings View ═══ --}}
<div class="flex-1 min-h-0 flex flex-col">

  {{-- Header --}}
  <div class="px-4 py-3 border-b border-[var(--t-border)]/40 flex items-center justify-between flex-shrink-0">
    <div class="flex items-center gap-2">
      <button wire:click="$set('commsView', 'timeline')" class="text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition">
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
      </button>
      <div>
        <span class="text-xs font-semibold text-[var(--t-text)]">Comms Einstellungen</span>
        <span class="text-[10px] text-[var(--t-text-muted)] ml-2">Root-Team: {{ $rootTeamName ?: '—' }}</span>
      </div>
    </div>
  </div>

  <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-6">

    {{-- ── Postmark Connection ── --}}
    <div class="rounded-lg border border-[var(--t-border)]/30 bg-white/[0.02] p-4 space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
          <span class="text-xs font-semibold text-[var(--t-text)]">Postmark</span>
        </div>
        <span class="text-[10px] text-[var(--t-text-muted)]">{{ $postmarkConfigured ? 'konfiguriert' : 'nicht konfiguriert' }}</span>
      </div>

      @if($postmarkMessage)
        <div class="text-[10px] text-[var(--t-text-muted)]">{{ $postmarkMessage }}</div>
      @endif

      <div class="space-y-2">
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Server Token</label>
          <input type="password" wire:model.defer="postmark.server_token"
                 placeholder="{{ $postmarkConfigured ? '•••••• (neu setzen)' : 'postmark server token' }}"
                 @if(!$this->canManageProviderConnections()) disabled @endif
                 class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Inbound User</label>
            <input type="text" wire:model.defer="postmark.inbound_user" placeholder="optional"
                   @if(!$this->canManageProviderConnections()) disabled @endif
                   class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40" />
          </div>
          <div>
            <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Inbound Pass</label>
            <input type="password" wire:model.defer="postmark.inbound_pass" placeholder="optional"
                   @if(!$this->canManageProviderConnections()) disabled @endif
                   class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40" />
          </div>
        </div>
        <div>
          <label class="block text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider mb-1">Signing Secret</label>
          <input type="password" wire:model.defer="postmark.signing_secret" placeholder="optional"
                 @if(!$this->canManageProviderConnections()) disabled @endif
                 class="w-full px-3 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40" />
        </div>
        <div class="flex justify-end">
          <button wire:click="savePostmarkConnection" wire:loading.attr="disabled"
                  @if(!$this->canManageProviderConnections()) disabled @endif
                  class="px-3 py-1.5 rounded-md text-xs font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40">
            Speichern
          </button>
        </div>
      </div>

      {{-- Domains --}}
      <div class="border-t border-[var(--t-border)]/30 pt-3 mt-3 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-semibold text-[var(--t-text)] uppercase tracking-wider">Domains</span>
        </div>

        @if($postmarkDomainMessage)
          <div class="text-[10px] text-[var(--t-text-muted)]">{{ $postmarkDomainMessage }}</div>
        @endif

        @if(!$postmarkConfigured)
          <div class="text-[10px] text-[var(--t-text-muted)]">Bitte zuerst Postmark speichern.</div>
        @else
          <div class="space-y-1">
            @forelse($postmarkDomains as $d)
              <div class="flex items-center justify-between gap-2 rounded-md bg-white/[0.03] border border-[var(--t-border)]/30 px-2.5 py-1.5">
                <div class="flex items-center gap-1.5 min-w-0">
                  <span class="text-xs font-semibold text-[var(--t-text)] truncate">{{ $d['domain'] }}</span>
                  @if($d['is_primary'])
                    <span class="px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-[var(--t-accent)]/10 text-[var(--t-accent)] border border-[var(--t-accent)]/20">Primary</span>
                  @endif
                  @if($d['is_verified'])
                    <span class="px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Verified</span>
                  @endif
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                  @if(!$d['is_primary'])
                    <button wire:click="setPostmarkPrimaryDomain({{ intval($d['id']) }})"
                            @if(!$this->canManageProviderConnections()) disabled @endif
                            class="text-[10px] px-1.5 py-0.5 rounded border border-[var(--t-border)]/30 text-[var(--t-text-muted)] hover:text-[var(--t-text)] transition disabled:opacity-40">
                      Primary
                    </button>
                  @endif
                  <button wire:click="removePostmarkDomain({{ intval($d['id']) }})"
                          @if(!$this->canManageProviderConnections()) disabled @endif
                          class="text-[10px] px-1.5 py-0.5 rounded border border-[var(--t-border)]/30 text-[var(--t-text-muted)] hover:text-red-400 transition disabled:opacity-40">
                    Löschen
                  </button>
                </div>
              </div>
            @empty
              <div class="text-[10px] text-[var(--t-text-muted)]">Keine Domains.</div>
            @endforelse
          </div>

          <div class="flex items-center gap-2 mt-2">
            <input type="text" wire:model.defer="postmarkNewDomain.domain" placeholder="z.B. company.de"
                   @if(!$this->canManageProviderConnections()) disabled @endif
                   class="flex-1 px-2.5 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40" />
            <label class="flex items-center gap-1 text-[10px] text-[var(--t-text-muted)]">
              <input type="checkbox" wire:model.defer="postmarkNewDomain.is_primary"
                     @if(!$this->canManageProviderConnections()) disabled @endif
                     class="rounded border-[var(--t-border)] bg-white/5" />
              Primary
            </label>
            <button wire:click="addPostmarkDomain"
                    @if(!$this->canManageProviderConnections()) disabled @endif
                    class="px-2.5 py-1.5 rounded-md text-[10px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40">
              Hinzufügen
            </button>
          </div>
        @endif
      </div>

      @if(!$this->canManageProviderConnections())
        <div class="text-[10px] text-[var(--t-text-muted)]/60 mt-2">Nur Owner/Admin des Root-Teams kann Postmark konfigurieren.</div>
      @endif
    </div>

    {{-- ── Channels ── --}}
    <div class="rounded-lg border border-[var(--t-border)]/30 bg-white/[0.02] p-4 space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-[var(--t-text)]">Kanäle</span>
        @if($channelsMessage)
          <span class="text-[10px] text-[var(--t-text-muted)]">{{ $channelsMessage }}</span>
        @endif
      </div>

      {{-- New Channel Form --}}
      <div class="rounded-md border border-[var(--t-border)]/20 bg-white/[0.02] p-3 space-y-2">
        <span class="text-[10px] font-semibold text-[var(--t-text)] uppercase tracking-wider">Neuer Kanal</span>
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Typ</label>
            <select wire:model.live="newChannel.type"
                    class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
              <option value="email">E-Mail</option>
              <option value="whatsapp">WhatsApp</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Provider</label>
            @if($newChannel['type'] === 'email')
              <select disabled class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] opacity-60">
                <option>Postmark</option>
              </select>
            @else
              <select disabled class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] opacity-60">
                <option>Meta / WhatsApp</option>
              </select>
            @endif
          </div>
          <div>
            <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Sichtbarkeit</label>
            <select wire:model.defer="newChannel.visibility"
                    class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]">
              <option value="private">privat</option>
              <option value="team">teamweit</option>
            </select>
          </div>
        </div>

        @if($newChannel['type'] === 'email')
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Local-Part</label>
              <input type="text" wire:model.defer="newChannel.sender_local_part" placeholder="z.B. sales"
                     class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
            </div>
            <div>
              <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Domain</label>
              <select wire:model.defer="newChannel.sender_domain"
                      @if(empty($postmarkDomains)) disabled @endif
                      class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40">
                <option value="">(Domain)</option>
                @foreach($postmarkDomains as $d)
                  <option value="{{ $d['domain'] }}">{{ $d['domain'] }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Name</label>
              <input type="text" wire:model.defer="newChannel.name" placeholder="optional"
                     class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
            </div>
          </div>
          <div class="flex justify-end">
            <button wire:click="createChannel" wire:loading.attr="disabled"
                    @if(empty($postmarkDomains)) disabled @endif
                    class="px-3 py-1.5 rounded-md text-[10px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40">
              E-Mail Kanal anlegen
            </button>
          </div>
        @else
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">WhatsApp Account</label>
              <select wire:model.defer="newChannel.whatsapp_account_id"
                      @if(empty($availableWhatsAppAccounts)) disabled @endif
                      class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)] disabled:opacity-40">
                <option value="">(Account)</option>
                @foreach($availableWhatsAppAccounts as $wa)
                  <option value="{{ $wa['id'] }}">{{ $wa['label'] }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-[10px] text-[var(--t-text-muted)] mb-0.5">Name</label>
              <input type="text" wire:model.defer="newChannel.name" placeholder="optional"
                     class="w-full px-2 py-1.5 text-xs rounded-md bg-white/5 border border-[var(--t-border)]/40 text-[var(--t-text)] placeholder-[var(--t-text-muted)] focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]" />
            </div>
          </div>
          <div class="flex justify-end">
            <button wire:click="createChannel" wire:loading.attr="disabled"
                    @if(empty($availableWhatsAppAccounts)) disabled @endif
                    class="px-3 py-1.5 rounded-md text-[10px] font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition disabled:opacity-40">
              WhatsApp Kanal anlegen
            </button>
          </div>
        @endif
      </div>

      {{-- Existing Channels --}}
      <div class="space-y-1">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-semibold text-[var(--t-text-muted)] uppercase tracking-wider">Vorhandene Kanäle</span>
          <span class="text-[9px] text-[var(--t-text-muted)]">{{ count($commsSettingsChannels) }} total</span>
        </div>
        @forelse($commsSettingsChannels as $c)
          <div class="flex items-center justify-between gap-2 rounded-md bg-white/[0.03] border border-[var(--t-border)]/30 px-2.5 py-1.5">
            <div class="flex items-center gap-1.5 min-w-0">
              @if($c['type'] === 'whatsapp')
                <span class="px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">WA</span>
              @else
                <span class="px-1.5 py-0.5 text-[9px] font-medium rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">Mail</span>
              @endif
              <span class="text-xs font-semibold text-[var(--t-text)] truncate">{{ $c['sender_identifier'] }}</span>
              <span class="text-[9px] text-[var(--t-text-muted)]">{{ $c['visibility'] === 'team' ? 'teamweit' : 'privat' }}</span>
              @if(!$c['is_active'])
                <span class="text-[9px] text-amber-400">inaktiv</span>
              @endif
            </div>
            <button wire:click="removeChannel({{ intval($c['id']) }})"
                    class="text-[10px] px-1.5 py-0.5 rounded border border-[var(--t-border)]/30 text-[var(--t-text-muted)] hover:text-red-400 hover:border-red-400/30 transition flex-shrink-0">
              Löschen
            </button>
          </div>
        @empty
          <div class="text-[10px] text-[var(--t-text-muted)] py-2">Noch keine Kanäle.</div>
        @endforelse
      </div>
    </div>

  </div>
</div>
