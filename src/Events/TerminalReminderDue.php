<?php

namespace Platform\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TerminalReminderDue implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public int $messageId,
        public int $channelId,
        public string $snippet,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("terminal.user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'reminder.due';
    }
}
