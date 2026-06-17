<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Services\EmbeddingService;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        private int $teamId,
        private string $entityType,
        private string $entityId,
        private string $text,
        private ?string $providerName = null,
        private ?array $metadata = null,
    ) {}

    public function handle(): void
    {
        try {
            app(EmbeddingService::class)->embedAndStore(
                teamId: $this->teamId,
                entityType: $this->entityType,
                entityId: $this->entityId,
                text: $this->text,
                providerName: $this->providerName,
                metadata: $this->metadata,
            );

            Log::info('[GenerateEmbeddingJob] Embedded and stored', [
                'team_id' => $this->teamId,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'provider' => $this->providerName,
            ]);
        } catch (\Throwable $e) {
            Log::error('[GenerateEmbeddingJob] Failed to embed', [
                'team_id' => $this->teamId,
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'provider' => $this->providerName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[GenerateEmbeddingJob] Job failed permanently', [
            'team_id' => $this->teamId,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'error' => $e->getMessage(),
        ]);
    }
}
