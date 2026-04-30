<div>
    @if(count($pagePresenceUsers) > 0)
        <div class="flex items-center -space-x-1.5" title="{{ collect($pagePresenceUsers)->pluck('name')->implode(', ') }}">
            @foreach(array_slice($pagePresenceUsers, 0, 4) as $pu)
                <div class="relative flex-shrink-0">
                    @if(!empty($pu['avatar']))
                        <img src="{{ $pu['avatar'] }}" alt="{{ $pu['name'] ?? '' }}"
                             class="w-6 h-6 rounded-full object-cover ring-2 ring-[var(--ui-surface)]" />
                    @else
                        <div class="w-6 h-6 rounded-full bg-[var(--ui-primary-5)] ring-2 ring-[var(--ui-surface)] flex items-center justify-center text-[10px] font-medium text-[var(--ui-primary)]">
                            {{ strtoupper(substr($pu['name'] ?? '?', 0, 1)) }}
                        </div>
                    @endif
                    <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-1 ring-[var(--ui-surface)]"></span>
                </div>
            @endforeach

            @if(count($pagePresenceUsers) > 4)
                <div class="w-6 h-6 rounded-full bg-[var(--ui-muted-5)] ring-2 ring-[var(--ui-surface)] flex items-center justify-center text-[10px] font-medium text-[var(--ui-secondary)]">
                    +{{ count($pagePresenceUsers) - 4 }}
                </div>
            @endif
        </div>
    @endif
</div>
