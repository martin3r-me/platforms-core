<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit-Command: Findet verwaiste Datensaetze (Orphans) deren team_id
 * auf ein nicht mehr existierendes Team verweist.
 *
 * Nuetzlich nach manuellen Loeschungen oder fehlenden Cascades.
 *
 * Usage:
 *   php artisan platform:audit-team-orphans          # Report only
 *   php artisan platform:audit-team-orphans --fix     # Report + Delete orphans
 */
class AuditTeamOrphansCommand extends Command
{
    protected $signature = 'platform:audit-team-orphans
                            {--fix : Verwaiste Datensaetze loeschen (ohne Flag: nur Report)}
                            {--table=* : Nur bestimmte Tabellen pruefen (optional)}';

    protected $description = 'Scannt alle Tabellen mit team_id FK und findet Rows deren Team nicht mehr existiert.';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $onlyTables = $this->option('table');

        if ($fix) {
            $this->warn('ACHTUNG: --fix Modus aktiv. Verwaiste Datensaetze werden geloescht!');
            if (!$this->confirm('Fortfahren?')) {
                $this->info('Abgebrochen.');
                return self::SUCCESS;
            }
        }

        $this->info('Suche Tabellen mit team_id Spalte...');

        $tables = $this->findTablesWithTeamId();

        if (!empty($onlyTables)) {
            $tables = array_intersect($tables, $onlyTables);
        }

        if (empty($tables)) {
            $this->info('Keine Tabellen mit team_id gefunden.');
            return self::SUCCESS;
        }

        $this->info(count($tables) . ' Tabelle(n) gefunden.');
        $this->newLine();

        $totalOrphans = 0;
        $results = [];

        foreach ($tables as $table) {
            $orphanCount = $this->countOrphans($table);

            if ($orphanCount > 0) {
                $results[] = [$table, $orphanCount];
                $totalOrphans += $orphanCount;

                if ($fix) {
                    $deleted = $this->deleteOrphans($table);
                    $this->line("  <fg=red>{$table}</>: {$orphanCount} Orphan(s) -> {$deleted} geloescht");
                } else {
                    $this->line("  <fg=yellow>{$table}</>: {$orphanCount} Orphan(s)");
                }
            }
        }

        $this->newLine();

        if ($totalOrphans === 0) {
            $this->info('Keine verwaisten Datensaetze gefunden.');
        } else {
            $this->table(['Tabelle', 'Orphans'], $results);
            $this->newLine();

            if ($fix) {
                $this->info("Insgesamt {$totalOrphans} verwaiste Datensaetze geloescht.");
            } else {
                $this->warn("Insgesamt {$totalOrphans} verwaiste Datensaetze gefunden.");
                $this->info('Nutze --fix um die Datensaetze zu loeschen.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Findet alle Tabellen die eine team_id Spalte haben (ausser teams selbst).
     */
    private function findTablesWithTeamId(): array
    {
        $allTables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->toArray();

        $tablesWithTeamId = [];

        foreach ($allTables as $table) {
            if ($table === 'teams') {
                continue;
            }

            if (Schema::hasColumn($table, 'team_id')) {
                $tablesWithTeamId[] = $table;
            }
        }

        sort($tablesWithTeamId);

        return $tablesWithTeamId;
    }

    /**
     * Zaehlt verwaiste Rows in einer Tabelle.
     */
    private function countOrphans(string $table): int
    {
        return DB::table($table)
            ->whereNotNull('team_id')
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams');
            })
            ->count();
    }

    /**
     * Loescht verwaiste Rows in einer Tabelle.
     */
    private function deleteOrphans(string $table): int
    {
        return DB::table($table)
            ->whereNotNull('team_id')
            ->whereNotIn('team_id', function ($query) {
                $query->select('id')->from('teams');
            })
            ->delete();
    }
}
