<?php

namespace Platform\Core\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\CoreContextDateTime;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;

/**
 * Expandiert die `recurrence_rrule` einer {@see CoreContextDateTime} in konkrete
 * Zeilen der Occurrences-Shadow-Table ({@see \Platform\Core\Models\CoreContextDateTimeOccurrence}).
 *
 * Timezone-korrekt: die RRULE wird gegen `timezone` (IANA-Zone, z.B. Europe/Berlin)
 * ausgewertet – nicht gegen UTC. So bleibt eine wöchentliche "09:00 Europe/Berlin"-
 * Regel auch über die DST-Umstellung (März/Oktober) hinweg bei 09:00 Ortszeit;
 * erst das Ergebnis wird für die Speicherung nach UTC konvertiert.
 *
 * Re-computable: ein erneuter Aufruf ersetzt alle nicht-manuellen (`is_exception
 * = false`) Occurrences im Fenster [$from, $to] komplett – manuelle Ausnahmen
 * (EXDATE/RDATE-Override) bleiben unberührt.
 */
class RecurrenceExpander
{
    /**
     * Rolling-Horizont: wie viele Tage ab $from expandiert werden, wenn kein
     * explizites $to übergeben wird.
     */
    public const DEFAULT_HORIZON_DAYS = 90;

    /**
     * Expandiert die RRULE von $dateTime in Occurrences-Rows im Fenster [$from, $to].
     *
     * Nicht-wiederkehrende Zeitpunkte werden übersprungen (deren einzige
     * Occurrence pflegt der {@see \Platform\Core\Services\ContextDateTimes\ContextDateTimeSynchronizer}).
     *
     * @return int  Anzahl der (neu) erzeugten Occurrences.
     */
    public function expand(CoreContextDateTime $dateTime, ?Carbon $from = null, ?Carbon $to = null): int
    {
        if (! $dateTime->isRecurring()) {
            return 0;
        }

        $from ??= Carbon::now('UTC')->startOfDay();
        $to ??= $from->copy()->addDays(self::DEFAULT_HORIZON_DAYS);

        $timezone = $dateTime->timezone ?: 'Europe/Berlin';

        $rows = $this->buildOccurrenceRows($dateTime, $timezone, $from, $to);

        if ($rows === null) {
            // Kaputte/nicht parsbare RRULE: nichts anfassen, statt den bestehenden
            // Occurrence-Schatten zu leeren.
            return 0;
        }

        DB::transaction(function () use ($dateTime, $rows): void {
            $dateTime->occurrences()->where('is_exception', false)->delete();

            foreach ($rows as $row) {
                $dateTime->occurrences()->create([
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'is_exception' => false,
                ]);
            }
        });

        return count($rows);
    }

    /**
     * @return list<array{starts_at: Carbon, ends_at: ?Carbon}>|null  null = RRULE nicht parsbar.
     */
    protected function buildOccurrenceRows(CoreContextDateTime $dateTime, string $timezone, Carbon $from, Carbon $to): ?array
    {
        $localStart = $dateTime->starts_at->copy()->setTimezone($timezone);
        $localEnd = $dateTime->ends_at?->copy()->setTimezone($timezone);
        $hasEnd = $dateTime->ends_at !== null;

        try {
            $rule = new Rule($dateTime->recurrence_rrule, $localStart, $localEnd, $timezone);
        } catch (\Throwable $e) {
            Log::warning('[RecurrenceExpander] RRULE nicht parsbar', [
                'core_context_date_time_id' => $dateTime->id,
                'recurrence_rrule' => $dateTime->recurrence_rrule,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $constraint = new BetweenConstraint($from, $to, true);
        $transformer = new ArrayTransformer;

        try {
            $occurrences = $transformer->transform($rule, $constraint);
        } catch (\Throwable $e) {
            Log::warning('[RecurrenceExpander] RRULE-Expansion fehlgeschlagen', [
                'core_context_date_time_id' => $dateTime->id,
                'recurrence_rrule' => $dateTime->recurrence_rrule,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $rows = [];
        foreach ($occurrences as $occurrence) {
            $start = Carbon::instance($occurrence->getStart())->utc();
            $end = $hasEnd ? Carbon::instance($occurrence->getEnd())->utc() : null;

            $rows[] = ['starts_at' => $start, 'ends_at' => $end];
        }

        return $rows;
    }
}
