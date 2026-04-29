<?php

namespace Platform\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class PagePresence extends Component
{
    public string $pageKey = '';
    public array $pagePresenceUsers = [];

    public function mount(): void
    {
        $route = request()->route();

        if ($route && $route->getName()) {
            $params = collect($route->parameters())
                ->map(fn ($v) => is_object($v) && method_exists($v, 'getKey') ? $v->getKey() : $v)
                ->values()
                ->implode('.');

            $this->pageKey = $route->getName() . ($params !== '' ? ".{$params}" : '');
        } else {
            $this->pageKey = trim(request()->path(), '/');
        }
    }

    public function getListeners(): array
    {
        $listeners = [];

        try {
            $user = Auth::user();
            $teamId = $user?->currentTeam?->id;

            if ($teamId && $user) {
                $channelKey = substr(md5($this->pageKey), 0, 12);
                $channel = "page.{$teamId}.{$channelKey}";

                $listeners["echo-presence:{$channel},here"] = 'onPageHere';
                $listeners["echo-presence:{$channel},joining"] = 'onPageJoining';
                $listeners["echo-presence:{$channel},leaving"] = 'onPageLeaving';
            }
        } catch (\Throwable $e) {
            // Fail silently
        }

        return $listeners;
    }

    public function onPageHere($users): void
    {
        $myId = Auth::id();
        $this->pagePresenceUsers = collect($users)
            ->filter(fn ($u) => (int) $u['id'] !== $myId)
            ->unique('id')
            ->values()
            ->toArray();
    }

    public function onPageJoining($user): void
    {
        $id = (int) ($user['id'] ?? $user);
        if ($id === Auth::id()) {
            return;
        }

        if (! collect($this->pagePresenceUsers)->contains('id', $id)) {
            $this->pagePresenceUsers[] = $user;
        }
    }

    public function onPageLeaving($user): void
    {
        $id = (int) ($user['id'] ?? $user);
        $this->pagePresenceUsers = array_values(
            array_filter($this->pagePresenceUsers, fn ($u) => (int) $u['id'] !== $id)
        );
    }

    public function render()
    {
        return view('platform::livewire.page-presence');
    }
}
