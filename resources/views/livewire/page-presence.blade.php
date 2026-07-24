<div>
    @if(count($pagePresenceUsers) > 0)
        <div class="flex items-center -space-x-1.5" title="{{ collect($pagePresenceUsers)->pluck('name')->implode(', ') }}">
            @foreach(array_slice($pagePresenceUsers, 0, 4) as $pu)
                <x-nx-avatar :name="$pu['name'] ?? null" :src="$pu['avatar'] ?? null" size="sm" status="online" ring />
            @endforeach

            @if(count($pagePresenceUsers) > 4)
                <div class="relative w-6 h-6 rounded-[6px] bg-[color:var(--nx-accent-soft)] ring-2 ring-[color:var(--nx-surface)] flex items-center justify-center text-[10px] font-medium text-[color:var(--nx-text)]">
                    +{{ count($pagePresenceUsers) - 4 }}
                </div>
            @endif
        </div>
    @endif
</div>
