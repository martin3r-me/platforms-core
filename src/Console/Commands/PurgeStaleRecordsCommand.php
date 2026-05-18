<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purge-Command: Soft-deleted Records die laenger als X Tage stale sind.
 *
 * Scannt automatisch alle Tabellen mit `last_viewed_at` Spalte.
 * Nur Tabellen mit `deleted_at` (SoftDeletes) werden geloescht.
 * Tabellen ohne SoftDeletes werden uebersprungen (nur Report).
 *
 * Usage:
 *   php artisan platform:purge-stale-records              # Dry-run (nur Report)
 *   php artisan platform:purge-stale-records --force       # Tatsaechlich soft-deleten
 *   php artisan platform:purge-stale-records --days=60     # Custom Grace-Period
 */
class PurgeStaleRecordsCommand extends Command
{
    protected $signature = 'platform:purge-stale-records
                            {--days=30 : Anzahl Tage nach Staleness bevor soft-deleted wird}
                            {--force : Tatsaechlich soft-deleten (ohne Flag: nur Report)}
                            {--table=* : Nur bestimmte Tabellen verarbeiten (optional)}';

    protected $description = 'Soft-deleted Records die laenger als X Tage stale sind (nur Tabellen mit SoftDeletes).';

    public function handle(): int
    {
        $graceDays = (int) $this->option('days');
        $force = $this->option('force');
        $onlyTables = $this->option('table');

        if (!$force) {
            $this->info('DRY-RUN Modus - keine Daten werden geaendert. Nutze --force zum Ausfuehren.');
        }

        $this->info("Grace-Period: {$graceDays} Tage nach Staleness");
        $this->newLine();

        $tables = $this->findTablesWithLastViewedAt();

        if (!empty($onlyTables)) {
            $tables = array_intersect($tables, $onlyTables);
        }

        if (empty($tables)) {
            $this->info('Keine Tabellen mit last_viewed_at gefunden.');
            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($graceDays);
        $results = [];
        $totalPurged = 0;
        $totalSkipped = 0;

        foreach ($tables as $table) {
            $hasSoftDeletes = Schema::hasColumn($table, 'deleted_at');

            // Zaehle Records die stale sind UND aelter als Grace-Period
            $staleCount = DB::table($table)
                ->whereNotNull('last_viewed_at')
                ->where('last_viewed_at', '<', $cutoff)
                ->when($hasSoftDeletes, fn ($q) => $q->whereNull('deleted_at'))
                ->count();

            if ($staleCount === 0) {
                continue;
            }

            if (!$hasSoftDeletes) {
                $results[] = [$table, $staleCount, 'UEBERSPRUNGEN (kein SoftDeletes)'];
                $totalSkipped += $staleCount;
                $this->line("  <fg=yellow>{$table}</>: {$staleCount} stale Record(s) - uebersprungen (kein SoftDeletes)");
                continue;
            }

            if ($force) {
                $purged = DB::table($table)
                    ->whereNotNull('last_viewed_at')
                    ->where('last_viewed_at', '<', $cutoff)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => Carbon::now()]);

                $results[] = [$table, $purged, 'SOFT-DELETED'];
                $totalPurged += $purged;
                $this->line("  <fg=red>{$table}</>: {$purged} Record(s) soft-deleted");
            } else {
                $results[] = [$table, $staleCount, 'WUERDE SOFT-DELETEN'];
                $totalPurged += $staleCount;
                $this->line("  <fg=cyan>{$table}</>: {$staleCount} Record(s) wuerden soft-deleted");
            }
        }

        $this->newLine();

        if (empty($results)) {
            $this->info('Keine stale Records gefunden die aelter als die Grace-Period sind.');
        } else {
            $this->table(['Tabelle', 'Anzahl', 'Aktion'], $results);
            $this->newLine();

            if ($force) {
                $this->info("Insgesamt {$totalPurged} Record(s) soft-deleted, {$totalSkipped} uebersprungen.");
            } else {
                $this->warn("Insgesamt {$totalPurged} Record(s) wuerden soft-deleted, {$totalSkipped} uebersprungen.");
                $this->info('Nutze --force zum Ausfuehren.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Findet alle Tabellen die eine last_viewed_at Spalte haben.
     */
    private function findTablesWithLastViewedAt(): array
    {
        $allTables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->toArray();

        $tables = [];

        foreach ($allTables as $table) {
            if (Schema::hasColumn($table, 'last_viewed_at')) {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }
}
