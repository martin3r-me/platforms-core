<?php

namespace Platform\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TerminalReactionToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $channelId,
        public int $messageId,
        public string $emoji,
        public int $userId,
        public bool $added,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("terminal.channel.{$this->channelId}")];
    }

    public function broadcastAs(): string
    {
        return 'reaction.toggled';
    }
}
