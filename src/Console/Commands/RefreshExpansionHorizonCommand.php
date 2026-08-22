<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Services\RecurrenceExpander;

/**
 * Rolling-Refresh des Occurrences-Fensters aller wiederkehrenden
 * CoreContextDateTime-Zeilen: löscht abgelaufene Occurrences und füllt das
 * Fenster wieder auf 90 Tage ab heute auf.
 *
 * Läuft täglich nachts über den Scheduler (siehe CoreServiceProvider), damit
 * das Occurrences-Fenster nie "auströpfelt" – ohne diesen Job würden alte
 * Zeitpunkte irgendwann aus dem für Kalender-Queries relevanten Bereich
 * herauswandern, ohne dass neue nachrücken.
 *
 * Usage:
 *   php artisan core:context-date-times:refresh-expansion-horizon
 *   php artisan core:context-date-times:refresh-expansion-horizon --chunk=200
 */
class RefreshExpansionHorizonCommand extends Command
{
    protected $signature = 'core:context-date-times:refresh-expansion-horizon
                            {--chunk=200 : Chunk-Größe beim Durchlaufen}';

    protected $description = 'Expandiert alle wiederkehrenden CoreContextDateTime-Zeilen neu ins rollende 90-Tage-Fenster.';

    public function handle(RecurrenceExpander $expander): int
    {
        $chunk = max(1, (int) $this->option('chunk'));

        $processed = 0;
        $failed = 0;

        CoreContextDateTime::query()
            ->whereNotNull('recurrence_rrule')
            ->chunkById($chunk, function ($dateTimes) use ($expander, &$processed, &$failed): void {
                foreach ($dateTimes as $dateTime) {
                    try {
                        $expander->expand($dateTime);
                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->error("[{$dateTime->id}] Expansion fehlgeschlagen: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Refresh abgeschlossen: {$processed} expandiert, {$failed} fehlgeschlagen.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
