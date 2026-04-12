{{-- ═══ Comms: WhatsApp Template Picker (reusable partial) ═══ --}}
{{-- Expects: $whatsappTemplates, $whatsappSelectedTemplateId, $whatsappTemplatePreview, $whatsappTemplateVariables --}}
<div class="space-y-1.5">
  {{-- Template Select (Searchable Dropdown) --}}
  <div x-data="{
    open: false,
    search: '',
    options: @js(collect($whatsappTemplates)->map(fn($t) => ['id' => $t['id'], 'label' => $t['label'] ?? $t['name'] ?? ''])->values()->all()),
    get selectedLabel() {
      const v = String($wire.whatsappSelectedTemplateId || '');
      if (!v) return 'Template wählen...';
      const opt = this.options.find(o => String(o.id) === v);
      return opt ? opt.label : 'Template wählen...';
    },
    get filtered() {
      if (!this.search) return this.options;
      const s = this.search.toLowerCase();
      return this.options.filter(o => o.label.toLowerCase().includes(s));
    },
    select(id) { $wire.set('whatsappSelectedTemplateId', id); this.open = false; this.search = ''; }
  }" @click.outside="open = false; search = ''" @keydown.escape.window="open = false; search = ''" class="relative">
    <button @click="open = !open" type="button"
            class="w-full flex items-center justify-between px-2.5 py-1.5 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] hover:bg-white/8 focus:outline-none focus:ring-1 focus:ring-amber-500/40 transition cursor-pointer"
            :class="$wire.whatsappSelectedTemplateId ? 'border-amber-500/30' : ''">
      <span x-text="selectedLabel" class="truncate" :class="!$wire.whatsappSelectedTemplateId && 'text-[var(--t-text-muted)]/60'"></span>
      <svg class="w-3 h-3 text-[var(--t-text-muted)]/50 transition-transform duration-150" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-1"
         class="absolute z-50 mt-1 w-full rounded-lg bg-[var(--t-glass-surface)] backdrop-blur-xl border border-[var(--t-border-bright)] shadow-xl shadow-black/30 max-h-56 overflow-hidden" style="display: none;">
      <div class="p-1.5 border-b border-[var(--t-border)]/40">
        <input type="text" x-model="search" x-ref="tplSearch" @click.stop placeholder="Suchen..."
               x-init="$watch('open', v => { if(v) setTimeout(() => $refs.tplSearch.focus(), 50) })"
               class="w-full px-2 py-1 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] placeholder-[var(--t-text-muted)]/40 focus:outline-none focus:ring-1 focus:ring-amber-500/40" />
      </div>
      <div class="overflow-auto max-h-40 py-1">
        <button @click="select('')" type="button" class="w-full text-left px-2.5 py-1.5 text-[11px] transition text-[var(--t-text-muted)] hover:bg-white/8">
          — Kein Template —
        </button>
        <template x-for="opt in filtered" :key="opt.id">
          <button @click="select(opt.id)" type="button"
                  class="w-full text-left px-2.5 py-1.5 text-[11px] transition"
                  :class="String($wire.whatsappSelectedTemplateId) === String(opt.id) ? 'bg-amber-500/15 text-amber-300 font-medium' : 'text-[var(--t-text)] hover:bg-white/8'">
            <span x-text="opt.label"></span>
          </button>
        </template>
        <div x-show="filtered.length === 0 && search" class="px-2.5 py-2 text-[10px] text-[var(--t-text-muted)]/50 italic">Keine Ergebnisse</div>
      </div>
    </div>
  </div>

  {{-- Template Preview + Variables --}}
  @if(!empty($whatsappTemplatePreview))
    <div class="rounded-lg border border-emerald-500/20 bg-emerald-500/[0.04] p-2.5 space-y-1.5">
      <div class="flex items-center justify-between">
        <span class="text-[10px] font-semibold text-emerald-400">{{ $whatsappTemplatePreview['name'] ?? '' }}</span>
        @if(!empty($whatsappTemplatePreview['language']))
          <span class="text-[8px] text-[var(--t-text-muted)] px-1 py-0.5 rounded bg-white/5 border border-[var(--t-border)]/30">{{ $whatsappTemplatePreview['language'] }}</span>
        @endif
      </div>
      <div class="rounded-md bg-white/[0.04] px-2.5 py-1.5 text-[11px] text-[var(--t-text)]/80 whitespace-pre-wrap leading-relaxed">{{ $this->getTemplatePreviewText() }}</div>
      @if(($whatsappTemplatePreview['variables_count'] ?? 0) > 0)
        <div class="space-y-1">
          @for($i = 1; $i <= $whatsappTemplatePreview['variables_count']; $i++)
            <div class="flex items-center gap-1.5">
              <span class="text-[9px] text-emerald-400/70 font-mono w-6 flex-shrink-0 text-right">&#123;&#123;{{ $i }}&#125;&#125;</span>
              <input type="text" wire:model.live="whatsappTemplateVariables.{{ $i }}"
                     class="flex-1 px-2 py-1 text-[11px] rounded-md bg-white/5 border border-[var(--t-border)]/30 text-[var(--t-text)] focus:outline-none focus:ring-1 focus:ring-emerald-500/50 placeholder-[var(--t-text-muted)]/50 transition"
                     placeholder="Variable {{ $i }}..." />
            </div>
          @endfor
        </div>
      @endif
      <div class="flex justify-end pt-0.5">
        <button wire:click="sendNewWhatsAppTemplate" wire:loading.attr="disabled" wire:target="sendNewWhatsAppTemplate"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-[10px] font-semibold bg-[var(--t-accent)] text-white hover:bg-[var(--t-accent)]/80 transition disabled:opacity-40 shadow-sm">
          <svg class="w-3 h-3" wire:loading.remove wire:target="sendNewWhatsAppTemplate" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M3.105 2.289a.75.75 0 00-.826.95l1.414 4.925A1.5 1.5 0 005.135 9.25h6.115a.75.75 0 010 1.5H5.135a1.5 1.5 0 00-1.442 1.086l-1.414 4.926a.75.75 0 00.826.95 28.896 28.896 0 0015.293-7.154.75.75 0 000-1.115A28.897 28.897 0 003.105 2.289z"/></svg>
          <span wire:loading.remove wire:target="sendNewWhatsAppTemplate">Template senden</span>
          <span wire:loading wire:target="sendNewWhatsAppTemplate">Sende...</span>
        </button>
      </div>
    </div>
  @endif
</div>
