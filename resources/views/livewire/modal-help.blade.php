<div>
<x-ui-modal size="2xl" model="modalShow">
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-2 min-w-0">
                @svg('heroicon-o-question-mark-circle', 'w-5 h-5 text-[var(--ui-primary)] flex-shrink-0')
                <h2 class="text-xl font-semibold text-[var(--ui-secondary)] m-0 truncate">Hilfe</h2>
            </div>
            @if(!empty($breadcrumb))
                <div class="hidden sm:flex items-center gap-1 text-sm text-[var(--ui-muted)]">
                    @foreach($breadcrumb as $crumb)
                        @if(!$loop->first)
                            @svg('heroicon-o-chevron-right', 'w-3 h-3')
                        @endif
                        @if($crumb['path'])
                            <button type="button"
                                wire:click="loadPage('{{ $currentModule }}', '{{ $crumb['path'] }}')"
                                class="hover:text-[var(--ui-primary)] transition-colors bg-transparent border-0 cursor-pointer p-0 text-sm text-[var(--ui-muted)]">
                                {{ $crumb['label'] }}
                            </button>
                        @else
                            <span>{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                    @if($title)
                        @svg('heroicon-o-chevron-right', 'w-3 h-3')
                        <span class="text-[var(--ui-secondary)] font-medium">{{ $title }}</span>
                    @endif
                </div>
            @endif
        </div>
    </x-slot>

    <div class="flex gap-0 -mx-6 -my-4 h-[65vh]">
        {{-- Sidebar --}}
        <div class="w-56 flex-shrink-0 border-r border-[var(--ui-border)]/60 overflow-y-auto bg-[var(--ui-muted-5)]/30 py-4 px-3 hidden sm:block">
            @forelse($tree as $module)
                <div class="mb-1">
                    {{-- Module header --}}
                    <button type="button"
                        wire:click="toggleModule('{{ $module['key'] }}')"
                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-left text-sm font-semibold transition-colors bg-transparent border-0 cursor-pointer
                            {{ $currentModule === $module['key'] ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)] hover:text-[var(--ui-primary)]' }}">
                        @svg(in_array($module['key'], $expandedModules) ? 'heroicon-o-chevron-down' : 'heroicon-o-chevron-right', 'w-3.5 h-3.5 flex-shrink-0 text-[var(--ui-muted)]')
                        @if($module['icon'])
                            <x-dynamic-component :component="$module['icon']" class="w-4 h-4 flex-shrink-0" />
                        @endif
                        <span class="truncate">{{ $module['title'] }}</span>
                    </button>

                    {{-- Module pages --}}
                    @if(in_array($module['key'], $expandedModules))
                        <div class="ml-4 mt-0.5 space-y-0.5">
                            {{-- Index page --}}
                            @if($module['has_index'])
                                <button type="button"
                                    wire:click="loadPage('{{ $module['key'] }}', 'index')"
                                    class="w-full text-left px-2 py-1 rounded text-xs transition-colors bg-transparent border-0 cursor-pointer
                                        {{ $currentModule === $module['key'] && $currentPage === 'index' ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-medium' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                    Überblick
                                </button>
                            @endif

                            {{-- Sections and pages --}}
                            @foreach($module['sections'] as $section)
                                @if($section['type'] === 'page')
                                    <button type="button"
                                        wire:click="loadPage('{{ $module['key'] }}', '{{ $section['path'] }}')"
                                        class="w-full text-left px-2 py-1 rounded text-xs transition-colors bg-transparent border-0 cursor-pointer truncate
                                            {{ $currentModule === $module['key'] && $currentPage === $section['path'] ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-medium' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                        {{ $section['title'] }}
                                    </button>
                                @elseif($section['type'] === 'group')
                                    <div class="mt-1.5">
                                        <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">
                                            {{ $section['title'] }}
                                        </div>
                                        <div class="ml-3 space-y-0.5">
                                            @foreach($section['pages'] as $page)
                                                <button type="button"
                                                    wire:click="loadPage('{{ $module['key'] }}', '{{ $page['path'] }}')"
                                                    class="w-full text-left px-2 py-1 rounded text-xs transition-colors bg-transparent border-0 cursor-pointer truncate
                                                        {{ $currentModule === $module['key'] && $currentPage === $page['path'] ? 'bg-[var(--ui-primary-5)] text-[var(--ui-primary)] font-medium' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                                                    {{ $page['title'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-xs text-[var(--ui-muted)] px-2 py-4">
                    Keine Dokumentation verfügbar.
                </div>
            @endforelse
        </div>

        {{-- Mobile sidebar dropdown --}}
        <div class="sm:hidden px-4 pt-4 pb-2 w-full" x-data="{ sidebarOpen: false }">
            <button type="button" @click="sidebarOpen = !sidebarOpen"
                class="w-full flex items-center justify-between px-3 py-2 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] text-sm text-[var(--ui-secondary)]">
                <span>{{ $title ?: 'Navigation' }}</span>
                @svg('heroicon-o-chevron-down', 'w-4 h-4 text-[var(--ui-muted)]')
            </button>
            <div x-show="sidebarOpen" x-transition @click.outside="sidebarOpen = false"
                class="mt-1 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] shadow-lg p-2 max-h-60 overflow-y-auto">
                @foreach($tree as $module)
                    @if($module['has_index'])
                        <button type="button"
                            wire:click="loadPage('{{ $module['key'] }}', 'index')"
                            @click="sidebarOpen = false"
                            class="w-full text-left px-2 py-1.5 rounded text-sm font-medium bg-transparent border-0 cursor-pointer
                                {{ $currentModule === $module['key'] ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-secondary)]' }}">
                            {{ $module['title'] }}
                        </button>
                    @endif
                    @foreach($module['sections'] as $section)
                        @if($section['type'] === 'page')
                            <button type="button"
                                wire:click="loadPage('{{ $module['key'] }}', '{{ $section['path'] }}')"
                                @click="sidebarOpen = false"
                                class="w-full text-left px-4 py-1 rounded text-xs bg-transparent border-0 cursor-pointer text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                                {{ $section['title'] }}
                            </button>
                        @elseif($section['type'] === 'group')
                            @foreach($section['pages'] as $page)
                                <button type="button"
                                    wire:click="loadPage('{{ $module['key'] }}', '{{ $page['path'] }}')"
                                    @click="sidebarOpen = false"
                                    class="w-full text-left px-4 py-1 rounded text-xs bg-transparent border-0 cursor-pointer text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                                    {{ $page['title'] }}
                                </button>
                            @endforeach
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0 overflow-y-auto px-6 sm:px-8 py-6">
            <div class="help-content max-w-none
                [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-[var(--ui-secondary)] [&_h1]:mb-4 [&_h1]:mt-0
                [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-[var(--ui-secondary)] [&_h2]:mb-3 [&_h2]:mt-6 [&_h2]:pb-2 [&_h2]:border-b [&_h2]:border-[var(--ui-border)]/40
                [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-[var(--ui-secondary)] [&_h3]:mb-2 [&_h3]:mt-4
                [&_h4]:text-base [&_h4]:font-medium [&_h4]:text-[var(--ui-secondary)] [&_h4]:mb-2 [&_h4]:mt-3
                [&_p]:text-sm [&_p]:text-[var(--ui-body-color)] [&_p]:leading-relaxed [&_p]:mb-3
                [&_ul]:text-sm [&_ul]:text-[var(--ui-body-color)] [&_ul]:mb-3 [&_ul]:pl-5 [&_ul]:list-disc [&_ul]:space-y-1
                [&_ol]:text-sm [&_ol]:text-[var(--ui-body-color)] [&_ol]:mb-3 [&_ol]:pl-5 [&_ol]:list-decimal [&_ol]:space-y-1
                [&_li]:leading-relaxed
                [&_code]:text-xs [&_code]:bg-[var(--ui-muted-5)] [&_code]:text-[var(--ui-secondary)] [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:font-mono
                [&_pre]:bg-[var(--ui-muted-5)] [&_pre]:rounded-lg [&_pre]:p-4 [&_pre]:mb-3 [&_pre]:overflow-x-auto [&_pre_code]:bg-transparent [&_pre_code]:p-0
                [&_blockquote]:border-l-4 [&_blockquote]:border-[var(--ui-primary)] [&_blockquote]:pl-4 [&_blockquote]:py-1 [&_blockquote]:mb-3 [&_blockquote]:text-sm [&_blockquote]:text-[var(--ui-muted)] [&_blockquote]:italic
                [&_a]:text-[var(--ui-primary)] [&_a]:underline [&_a]:cursor-pointer hover:[&_a]:opacity-80
                [&_table]:w-full [&_table]:text-sm [&_table]:mb-3 [&_table]:border-collapse
                [&_th]:text-left [&_th]:font-semibold [&_th]:text-[var(--ui-secondary)] [&_th]:px-3 [&_th]:py-2 [&_th]:border-b [&_th]:border-[var(--ui-border)]/60 [&_th]:bg-[var(--ui-muted-5)]
                [&_td]:px-3 [&_td]:py-2 [&_td]:border-b [&_td]:border-[var(--ui-border)]/40
                [&_hr]:my-6 [&_hr]:border-[var(--ui-border)]/40
                [&_img]:rounded-lg [&_img]:max-w-full [&_img]:h-auto [&_img]:my-3
                [&_.help-internal-link]:text-[var(--ui-primary)] [&_.help-internal-link]:no-underline [&_.help-internal-link]:font-medium hover:[&_.help-internal-link]:underline
            ">
                {!! $content !!}
            </div>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex justify-end items-center w-full">
            <x-ui-button variant="secondary-outline" @click="modalShow = false">Schließen</x-ui-button>
        </div>
    </x-slot>
</x-ui-modal>
</div>
