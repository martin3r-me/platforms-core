<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\DirectUploadService;

class CleanupDirectUploadsCommand extends Command
{
    protected $signature = 'core:context-files:cleanup-uploads';
    protected $description = 'Räumt abgelaufene Direkt-Upload-Links und unverknüpfte Dateien auf (S3 + DB)';

    public function handle(DirectUploadService $service): int
    {
        $this->info('Entfernt: ' . $service->cleanupExpired());

        return self::SUCCESS;
    }
}
