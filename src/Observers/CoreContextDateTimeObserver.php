<?php

namespace Platform\Core\Observers;

use Platform\Core\Jobs\ExpandContextDateTimeOccurrences;
use Platform\Core\Models\CoreContextDateTime;

/**
 * Stößt die RRULE-Expansion an, sobald eine wiederkehrende
 * {@see CoreContextDateTime} neu angelegt oder in einem expansionsrelevanten
 * Feld verändert wird. Nicht-wiederkehrende Zeitpunkte pflegt der
 * {@see \Platform\Core\Services\ContextDateTimes\ContextDateTimeSynchronizer}
 * bereits selbst (genau eine Occurrence) – hier also bewusst kein Eingriff.
 */
class CoreContextDateTimeObserver
{
    /**
     * Felder, deren Änderung eine Neu-Expansion erfordert.
     */
    protected const EXPANSION_RELEVANT_FIELDS = [
        'recurrence_rrule',
        'starts_at',
        'ends_at',
        'timezone',
    ];

    public function saved(CoreContextDateTime $dateTime): void
    {
        if (! $dateTime->isRecurring()) {
            return;
        }

        if (! $dateTime->wasRecentlyCreated && ! $dateTime->wasChanged(self::EXPANSION_RELEVANT_FIELDS)) {
            return;
        }

        ExpandContextDateTimeOccurrences::dispatch($dateTime->id);
    }
}
