<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Services\ContextDateTimes\ContextDateTimeSynchronizer;

/**
 * Backfill: spiegelt bestehende Datums-Spalten der Whitelist-Models nachträglich
 * in core_context_date_times. Idempotent & beliebig re-runbar – ein zweiter Lauf
 * meldet ausschließlich "unchanged".
 *
 * Usage:
 *   php artisan core:context-date-times:backfill --model=PlannerTask --dry-run
 *   php artisan core:context-date-times:backfill --model=PlannerTask
 *   php artisan core:context-date-times:backfill --model="Platform\\Planner\\Models\\PlannerTask"
 *   php artisan core:context-date-times:backfill                 # alle Whitelist-Models
 */
class BackfillContextDateTimesCommand extends Command
{
    protected $signature = 'core:context-date-times:backfill
                            {--model= : Model (Kurzname wie PlannerTask oder FQCN); ohne Angabe alle Whitelist-Models}
                            {--dry-run : Nur berichten, nichts schreiben}
                            {--chunk=200 : Chunk-Größe beim Durchlaufen}
                            {--with-trashed : Auch soft-deleted Quell-Records einbeziehen}';

    protected $description = 'Backfill bestehender Datums-Spalten in core_context_date_times (idempotent).';

    public function handle(ContextDateTimeSynchronizer $synchronizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $withTrashed = (bool) $this->option('with-trashed');

        if ($dryRun) {
            $this->info('DRY-RUN: es wird nichts geschrieben.');
        }

        $whitelist = (array) config('core.context_date_times.sync', []);

        $classes = $this->resolveClasses($whitelist);
        if ($classes === null) {
            return self::FAILURE;
        }

        $grand = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0, 'skipped' => 0];

        foreach ($classes as $class) {
            $map = (array) ($whitelist[$class] ?? []);
            if (empty($map)) {
                $this->warn("[$class] keine Datums-Spalten gemappt – übersprungen.");
                continue;
            }

            $this->line("→ {$class} (".implode(', ', array_keys($map)).')');

            $totals = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0, 'skipped' => 0];

            $query = $class::query();
            if ($withTrashed && in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }

            $query->chunkById($chunk, function ($records) use ($synchronizer, $map, $dryRun, &$totals): void {
                foreach ($records as $record) {
                    /** @var Model $record */
                    $result = $synchronizer->sync($record, $map, persist: ! $dryRun);
                    foreach ($result as $key => $value) {
                        $totals[$key] += $value;
                    }
                }
            });

            foreach ($totals as $key => $value) {
                $grand[$key] += $value;
            }

            $this->reportTotals($totals);
        }

        $this->newLine();
        $this->info('Gesamt:');
        $this->reportTotals($grand);

        return self::SUCCESS;
    }

    /**
     * Ermittelt die zu verarbeitenden Model-Klassen aus --model bzw. der Whitelist.
     * Gibt null zurück, wenn die Auflösung fehlschlägt (Fehler wurde ausgegeben).
     *
     * @param  array<string, mixed>  $whitelist
     * @return list<string>|null
     */
    protected function resolveClasses(array $whitelist): ?array
    {
        $available = array_keys($whitelist);

        if (empty($available)) {
            $this->error('Keine Models in config(core.context_date_times.sync) konfiguriert.');

            return null;
        }

        $option = $this->option('model');

        if (empty($option)) {
            // Nur installierte Klassen; nicht-installierte (z.B. Modul fehlt) still überspringen.
            $classes = array_values(array_filter($available, 'class_exists'));
            if (empty($classes)) {
                $this->warn('Keine der konfigurierten Model-Klassen ist installiert.');
            }

            return $classes;
        }

        $match = $this->matchClass($option, $available);
        if ($match === null) {
            $this->error("Model \"{$option}\" ist nicht in der Whitelist. Verfügbar: ".implode(', ', $available));

            return null;
        }

        if (! class_exists($match)) {
            $this->error("Model-Klasse {$match} existiert nicht (Modul nicht installiert?).");

            return null;
        }

        return [$match];
    }

    /**
     * Matcht die --model-Option gegen die Whitelist: exakter FQCN oder
     * (case-insensitiver) Klassen-Kurzname.
     *
     * @param  list<string>  $available
     */
    protected function matchClass(string $option, array $available): ?string
    {
        $needle = ltrim($option, '\\');

        foreach ($available as $class) {
            if (strcasecmp($class, $needle) === 0) {
                return $class;
            }
        }

        foreach ($available as $class) {
            if (strcasecmp(class_basename($class), $needle) === 0) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param  array{created:int, updated:int, deleted:int, unchanged:int, skipped:int}  $totals
     */
    protected function reportTotals(array $totals): void
    {
        $this->line(sprintf(
            '   created=%d  updated=%d  deleted=%d  unchanged=%d  skipped=%d',
            $totals['created'],
            $totals['updated'],
            $totals['deleted'],
            $totals['unchanged'],
            $totals['skipped'],
        ));
    }
}
