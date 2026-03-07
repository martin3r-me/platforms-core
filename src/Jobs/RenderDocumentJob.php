<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Document;
use Platform\Core\Services\Documents\DocumentService;

class RenderDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 2;

    public function __construct(
        private int $documentId,
        private ?int $userId = null,
        private array $rendererOptions = [],
    ) {}

    public function handle(): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            Log::warning('[RenderDocumentJob] Document not found', [
                'document_id' => $this->documentId,
            ]);
            return;
        }

        try {
            $service = app(DocumentService::class);
            $service->renderAndStore($document, $this->userId, $this->rendererOptions);

            Log::info('[RenderDocumentJob] Document rendered successfully', [
                'document_id' => $this->documentId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[RenderDocumentJob] Failed to render document', [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[RenderDocumentJob] Job failed permanently', [
            'document_id' => $this->documentId,
            'error' => $e->getMessage(),
        ]);

        Document::where('id', $this->documentId)->update([
            'status' => 'failed',
        ]);
    }
}
