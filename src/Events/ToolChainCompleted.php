<?php

namespace Platform\Core\Events;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Tool-Chain wurde abgeschlossen
 */
class ToolChainCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $mainToolName,
        public array $arguments,
        public ToolContext $context,
        public ToolResult $result,
        public array $executedTools,
        public float $totalDuration,
        public ?string $traceId = null
    ) {}
}

