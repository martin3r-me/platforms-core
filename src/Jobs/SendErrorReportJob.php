<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendErrorReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 2;
    public $timeout = 10;
    public $backoff = 5;

    public function __construct(
        private string $endpoint,
        private array $payload,
    ) {}

    public function handle(): void
    {
        try {
            $response = Http::timeout(5)->post($this->endpoint, $this->payload);

            Log::debug('[ErrorReporter] Sent', [
                'key' => $this->payload['package_key'] ?? 'unknown',
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ErrorReporter] Send failed', [
                'package' => $this->payload['package_key'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
