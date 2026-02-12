<?php

namespace Platform\Core\Events;

use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsWhatsAppThread;
use Platform\Core\Models\CommsWhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommsWhatsAppInboundReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CommsChannel $channel,
        public CommsWhatsAppThread $thread,
        public CommsWhatsAppMessage $message,
        public bool $isNewThread,
    ) {}
}
