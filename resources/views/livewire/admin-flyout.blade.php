<div x-data="{ adminFlyoutOpen: false }"
     @open-admin-flyout.window="adminFlyoutOpen = true"
     class="relative">

    @if($isAdmin)
        <button x-ref="trigger" @click="adminFlyoutOpen = !adminFlyoutOpen"
            class="inline-flex items-center gap-1 px-2 py-1 h-7 rounded-md border transition text-xs
            text-[var(--ui-secondary)] bg-transparent border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]"
            title="Administration">
            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
            <span class="hidden lg:inline">Admin</span>
        </button>

        <template x-teleport="body">
            <div x-show="adminFlyoutOpen" x-cloak x-transition
                @click.outside="adminFlyoutOpen = false"
                x-effect="if(adminFlyoutOpen) { $nextTick(() => { const r = $refs.trigger.getBoundingClientRect(); $el.style.top = (r.bottom + 8) + 'px'; $el.style.right = (window.innerWidth - r.right) + 'px'; }) }"
                class="fixed z-[99] w-64 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 shadow-lg max-h-[80vh] overflow-y-auto">
                <div class="p-2">
                    <h3 class="text-[0.625rem] font-semibold text-[var(--ui-muted)] mb-1 px-2 uppercase tracking-wider">Administration</h3>

                    {{-- Admin modules --}}
                    <div class="space-y-0.5">
                        @foreach($adminModules as $module)
                            <a href="{{ $module['url'] }}"
                                @click="adminFlyoutOpen = false"
                                class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[var(--ui-muted-5)]">
                                <div class="flex-shrink-0">
                                    @if(!empty($module['icon']))
                                        <x-dynamic-component :component="$module['icon']" class="w-4 h-4 text-[var(--ui-primary)]" />
                                    @else
                                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--ui-primary)]')
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 text-left">
                                    <div class="font-medium text-[var(--ui-secondary)] text-xs truncate">{{ $module['title'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Separator --}}
                    <div class="border-t border-[var(--ui-border)]/60 my-2"></div>

                    {{-- Static admin links --}}
                    <div class="space-y-0.5">
                        <button type="button"
                            @click="$dispatch('open-modal-team'); adminFlyoutOpen = false"
                            class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[var(--ui-muted-5)]">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-primary)]')
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="font-medium text-[var(--ui-secondary)] text-xs">Team-Einstellungen</div>
                            </div>
                        </button>

                        <button type="button"
                            @click="$dispatch('open-modal-modules', { tab: 'matrix' }); adminFlyoutOpen = false"
                            class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[var(--ui-muted-5)]">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-table-cells', 'w-4 h-4 text-[var(--ui-primary)]')
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="font-medium text-[var(--ui-secondary)] text-xs">Modul-Matrix</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
