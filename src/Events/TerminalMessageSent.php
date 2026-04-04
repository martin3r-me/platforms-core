<?php

namespace Platform\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TerminalMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $channelId,
        public array $message,
        public int $senderId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("terminal.channel.{$this->channelId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
