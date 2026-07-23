<div x-data="{ adminFlyoutOpen: false }"
     @open-admin-flyout.window="adminFlyoutOpen = true"
     class="relative">

    @if($isAdmin)
        <button x-ref="trigger" @click="adminFlyoutOpen = !adminFlyoutOpen"
            class="inline-flex items-center gap-1.5 px-2 py-1 h-7 rounded-md border transition text-xs
            text-[color:var(--nx-text)] border-[color:var(--nx-line-strong)] hover:bg-[color:var(--nx-hover)]"
            title="Administration">
            @svg('heroicon-o-cog-6-tooth', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
            <span class="hidden lg:inline">Admin</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 text-[color:var(--nx-faint)]">
                <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
            </svg>
        </button>

        <template x-teleport="body">
            <div x-show="adminFlyoutOpen" x-cloak x-transition
                @click.outside="adminFlyoutOpen = false"
                x-effect="if(adminFlyoutOpen) { $nextTick(() => { const r = $refs.trigger.getBoundingClientRect(); $el.style.top = (r.bottom + 8) + 'px'; $el.style.right = (window.innerWidth - r.right) + 'px'; }) }"
                class="fixed z-[99] w-64 bg-[color:var(--nx-surface)] rounded-[8px] border border-[color:var(--nx-line-strong)] shadow-[var(--nx-shadow-pop)] max-h-[80vh] overflow-y-auto">
                <div class="p-2">
                    <h3 class="text-[0.625rem] font-semibold text-[color:var(--nx-faint)] mb-1 px-2">Administration</h3>

                    {{-- Admin modules --}}
                    <div class="space-y-0.5">
                        @foreach($adminModules as $module)
                            <a href="{{ $module['url'] }}"
                                @click="adminFlyoutOpen = false"
                                class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[color:var(--nx-hover)]">
                                <div class="flex-shrink-0">
                                    @if(!empty($module['icon']))
                                        <x-dynamic-component :component="$module['icon']" class="w-4 h-4 text-[color:var(--nx-muted)]" />
                                    @else
                                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[color:var(--nx-muted)]')
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 text-left">
                                    <div class="font-medium text-[color:var(--nx-text)] text-xs truncate">{{ $module['title'] }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    {{-- Separator --}}
                    <div class="border-t border-[color:var(--nx-line)] my-2"></div>

                    {{-- Static admin links --}}
                    <div class="space-y-0.5">
                        <button type="button"
                            @click="$dispatch('open-modal-team'); adminFlyoutOpen = false"
                            class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[color:var(--nx-hover)]">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-user-group', 'w-4 h-4 text-[color:var(--nx-muted)]')
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="font-medium text-[color:var(--nx-text)] text-xs">Team-Einstellungen</div>
                            </div>
                        </button>

                        <a href="{{ route('platform.admin.module-matrix') }}"
                            @click="adminFlyoutOpen = false"
                            class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[color:var(--nx-hover)]">
                            <div class="flex-shrink-0">
                                @svg('heroicon-o-table-cells', 'w-4 h-4 text-[color:var(--nx-muted)]')
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="font-medium text-[color:var(--nx-text)] text-xs">Modul-Matrix</div>
                            </div>
                        </a>

                        @if($isOwner)
                            <a href="{{ route('platform.admin.semantic-layer') }}"
                                @click="adminFlyoutOpen = false"
                                class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs hover:bg-[color:var(--nx-hover)]">
                                <div class="flex-shrink-0">
                                    @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[color:var(--nx-muted)]')
                                </div>
                                <div class="min-w-0 flex-1 text-left">
                                    <div class="font-medium text-[color:var(--nx-text)] text-xs">Semantic Layer</div>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
