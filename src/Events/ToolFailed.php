<?php

namespace Platform\Core\Events;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Tool-Ausführung ist fehlgeschlagen
 */
class ToolFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $toolName,
        public array $arguments,
        public ToolContext $context,
        public string $errorMessage,
        public string $errorCode,
        public ?\Throwable $exception = null,
        public float $duration,
        public int $memoryUsage,
        public ?string $traceId = null,
        public int $retries = 0,
        public bool $willRetry = false,
        public ?string $errorType = null
    ) {}
}

