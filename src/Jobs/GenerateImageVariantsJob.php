<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;

class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 2;

    public function __construct(
        private int $contextFileId
    ) {}

    public function handle(): void
    {
        $contextFile = ContextFile::find($this->contextFileId);

        if (!$contextFile) {
            Log::warning('[GenerateImageVariantsJob] ContextFile not found', [
                'context_file_id' => $this->contextFileId,
            ]);
            return;
        }

        $contextFile->update(['variants_status' => 'processing']);

        try {
            $service = app(ContextFileService::class);
            $service->generateImageVariants($contextFile);

            $contextFile->update(['variants_status' => 'complete']);

            Log::info('[GenerateImageVariantsJob] Variants generated successfully', [
                'context_file_id' => $this->contextFileId,
            ]);
        } catch (\Throwable $e) {
            $contextFile->update(['variants_status' => 'failed']);

            Log::error('[GenerateImageVariantsJob] Failed to generate variants', [
                'context_file_id' => $this->contextFileId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[GenerateImageVariantsJob] Job failed permanently', [
            'context_file_id' => $this->contextFileId,
            'error' => $e->getMessage(),
        ]);

        ContextFile::where('id', $this->contextFileId)->update([
            'variants_status' => 'failed',
        ]);
    }
}
