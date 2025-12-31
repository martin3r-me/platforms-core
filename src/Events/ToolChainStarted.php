<?php

namespace Platform\Core\Events;

use Platform\Core\Contracts\ToolContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Tool-Chain wurde gestartet
 */
class ToolChainStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $mainToolName,
        public array $arguments,
        public ToolContext $context,
        public array $chainPlan,
        public ?string $traceId = null
    ) {}
}

