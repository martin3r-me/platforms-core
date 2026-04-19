<?php

namespace Platform\Core\Jobs;

use Platform\Core\Services\ToolCatalogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildToolCatalogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ToolCatalogService $service): void
    {
        $count = $service->rebuildAll();

        Log::info('[ToolCatalog] Rebuild complete', ['catalogs_built' => $count]);
    }
}
