<?php

namespace Platform\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Team wurde geloescht (NACH der DB-Transaction).
 *
 * Dispatched nach erfolgreichem Transaction-Commit.
 * Kann async verarbeitet werden (Queues, Notifications etc.).
 * Non-critical - Fehler in Listenern beeinflussen die Loeschung nicht.
 *
 * Enthaelt nur primitive Werte (kein Team-Model, da bereits geloescht).
 */
class TeamDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $teamId,
        public string $teamName,
        public int $deletedByUserId
    ) {}
}
