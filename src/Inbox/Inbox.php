<?php

namespace Platform\Core\Inbox;

use Platform\Core\Inbox\Contracts\Delivery;

/**
 * Bequeme statische Kurzform für die Delivery-Naht — die künftige, dauerhafte
 * Adresse, an die Produzenten (helpdesk, planner, whisper, user-connectors)
 * liefern:
 *
 *   \Platform\Core\Inbox\Inbox::deliver([
 *       'user_id'      => $ticket->assignee_id,
 *       'team_id'      => $ticket->team_id,
 *       'subject'      => 'Ticket dir zugewiesen: ' . $ticket->title,
 *       'source'       => $ticket,
 *       'sender_label' => 'Helpdesk',
 *   ]);
 *
 * Kein class_exists-Guard mehr nötig: der Delivery-Port ist via bindIf immer
 * gebunden (Null-Default, wenn kein Triage-Kern da ist) → läuft überall durch.
 */
class Inbox
{
    /**
     * @param  array<string,mixed>  $payload  siehe Delivery::deliver()
     * @return int|null  ID des erzeugten Items (oder null)
     */
    public static function deliver(array $payload): ?int
    {
        if (! app()->bound(Delivery::class)) {
            return null;
        }

        return app(Delivery::class)->deliver($payload);
    }
}
