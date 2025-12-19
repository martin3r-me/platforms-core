<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Contracts\CounterKeyResultSyncer;

class SyncCounterKeyResultsCommand extends Command
{
    protected $signature = 'core:sync-counter-key-results {--dry-run : Keine Writes, nur zählen/loggen}';

    protected $description = 'Synchronisiert Team Counter (Core) in verknüpfte OKR KeyResults (loose coupling).';

    public function handle(CounterKeyResultSyncer $syncer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Counter→KeyResult Sync gestartet' . ($dryRun ? ' (DRY RUN)' : '') . '...');

        $updated = $syncer->syncAll($dryRun);

        $this->info("Fertig. Aktualisiert: {$updated}");

        return self::SUCCESS;
    }
}


