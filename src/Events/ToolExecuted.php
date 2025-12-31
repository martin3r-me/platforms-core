<?php

namespace Platform\Core\Events;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Tool wurde erfolgreich ausgeführt
 */
class ToolExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $toolName,
        public array $arguments,
        public ToolContext $context,
        public ToolResult $result,
        public float $duration,
        public int $memoryUsage,
        public ?string $traceId = null
    ) {}
}

