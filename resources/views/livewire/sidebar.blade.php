{{-- resources/views/vendor/ui/sidebar.blade.php --}}
<div x-data="sidebarState()" x-init="init()" class="d-flex">
    @if(!empty($items))
        <aside 
            x-cloak
            :class="collapsed ? 'w-16' : 'w-80'" 
            class="relative flex-shrink-0 h-full bg-white border-right-1 border-right-solid border-muted transition-all duration-300 d-flex flex-col"
        >
            <!-- Toggle -->
            <div class="sticky top-0 z-10 bg-white border-bottom-1 border-muted">
                <button 
                    @click="toggle()" 
                    class="w-full p-3 d-flex items-center justify-center bg-primary-10 transition"
                    title="Sidebar umschalten"
                >
                    <x-heroicon-o-chevron-double-left x-show="!collapsed" class="w-6 h-6 text-primary" />
                    <x-heroicon-o-chevron-double-right x-show="collapsed" class="w-6 h-6 text-primary" />
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto p-2 d-flex flex-col gap-2">
                @foreach($items as $group)
                    <div>
                        <h4 x-show="!collapsed" class="p-3 text-sm italic text-secondary uppercase transition-opacity duration-200">
                            {{ $group['group'] ?? 'Navigation' }}
                        </h4>

                        @foreach($group['items'] as $item)
                            @php
                                $routeName = $item['route'] ?? null;
                                $params = $item['params'] ?? [];

                                // Fallback: Route nur, wenn der Name existiert und die Route auch registriert ist
                                $url = '#';
                                $path = '#';
                                if ($routeName && Route::has($routeName)) {
                                    // params kann numerisch oder assoziativ sein
                                    $url = route($routeName, $params);
                                    $path = parse_url($url, PHP_URL_PATH) ?? '#';
                                }
                            @endphp

                            <a href="{{ $url }}"
                               :class="(window.location.pathname === '{{ $path }}'
                                    ? 'bg-primary text-on-primary shadow-md'
                                    : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
                                ) + ' ' + (collapsed ? 'justify-center' : 'gap-3')"
                               class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
                               wire:navigate
                            >
                                @if(!empty($item['icon']))
                                    <x-dynamic-component 
                                        :component="$item['icon']"
                                        :class="window.location.pathname === '{{ $path }}' ? 'text-on-primary' : 'text-primary'"
                                        class="w-6 h-6 flex-shrink-0"
                                    />
                                @endif

                                <span x-show="!collapsed" class="truncate transition-opacity duration-200">
                                    {{ $item['label'] }}
                                </span>

                                @if(!empty($item['badge']))
                                    <span x-show="!collapsed" class="ml-auto">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>
    @endif
</div>

<script>
function sidebarState() {
    return {
        collapsed: false,
        init() {
            this.collapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        },
        toggle() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebar-collapsed', this.collapsed);
        }
    }
}
</script>