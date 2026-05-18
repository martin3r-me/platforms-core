<?php

namespace Platform\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Platform\Core\Models\Team;

/**
 * Event: Team wird geloescht (INNERHALB der DB-Transaction).
 *
 * Synchron dispatched VOR der eigentlichen Loeschung.
 * Listener koennen hier App-Level Cleanup durchfuehren (Storage, externe APIs etc.).
 * Bei Exceptions rollt die gesamte Transaction zurueck.
 *
 * KEIN SerializesModels - muss synchron in Transaction laufen.
 */
class TeamDeleting
{
    use Dispatchable;

    public function __construct(
        public Team $team,
        public int $deletedByUserId
    ) {}
}
