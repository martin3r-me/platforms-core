<?php

namespace Platform\Core\Events;

use Platform\Core\Models\CommsChannel;
use Platform\Core\Models\CommsEmailThread;
use Platform\Core\Models\CommsEmailInboundMail;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommsInboundReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CommsChannel $channel,
        public CommsEmailThread $thread,
        public CommsEmailInboundMail $mail,
        public bool $isNewThread,
    ) {}
}
