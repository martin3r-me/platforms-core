<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Services\RecurrenceExpander;

/**
 * Expandiert die RRULE einer einzelnen {@see CoreContextDateTime} in die
 * Occurrences-Shadow-Table.
 *
 * Wird von {@see \Platform\Core\Observers\CoreContextDateTimeObserver} bei
 * Create/Update eines wiederkehrenden Zeitpunkts dispatched sowie von
 * {@see \Platform\Core\Console\Commands\RefreshExpansionHorizonCommand} beim
 * täglichen Rolling-Refresh des 90-Tage-Fensters.
 */
class ExpandContextDateTimeOccurrences implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $coreContextDateTimeId)
    {
    }

    public function handle(RecurrenceExpander $expander): void
    {
        $dateTime = CoreContextDateTime::find($this->coreContextDateTimeId);

        if ($dateTime === null || ! $dateTime->isRecurring()) {
            return;
        }

        $expander->expand($dateTime);
    }
}
