<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Die EINE Naht nach innen: andere Module liefern ein Item in die persönliche
 * Inbox eines Users, ohne das Triage-Schema direkt anzufassen.
 *
 * Core besitzt diesen Port dauerhaft (Ports & Adapters) und bindet den Kern-Writer
 * (ItemWriter) als Implementierung. Produzenten rufen die Facade
 * \Platform\Core\Inbox\Inbox::deliver() — kein class_exists-Guard mehr nötig.
 *
 * Bewusst NEUTRAL: Rückgabe ist die Item-ID (int), nicht das Kern-Model. So hängt
 * kein Produzent am konkreten InboxItem — die ID reicht für Deep-Links/Logs.
 *
 * Payload (identisch zur bisherigen Inbox::deliver([...])):
 *  - user_id (int, erforderlich)      Empfänger
 *  - team_id (int, erforderlich)      Team-Scope
 *  - subject (string, erforderlich)
 *  - body (string, optional)          Volltext
 *  - channel (string, optional)       Standard: 'system'
 *  - source (Model, optional)         Ursprungsobjekt → source_type/source_id;
 *                                     alternativ source_type + source_id explizit
 *  - sender_label (string, optional)  Standard: 'System'
 *  - sender_kind (string, optional)   Standard: 'system'
 *  - importance (float, optional)     0..1
 *  - received_at (mixed, optional)    Standard: now()
 *  - dedupe (bool, optional)          true → kein Duplikat je (user, source)
 *
 * @see \Platform\Core\Inbox\Inbox für die statische Kurzform Inbox::deliver([...])
 */
interface Delivery
{
    /**
     * Liefert ein Item in die Inbox des Users.
     *
     * @param  array<string,mixed>  $payload
     * @return int|null  ID des erzeugten Items; null, wenn Pflichtfelder fehlen
     *                   oder keine Implementierung gebunden ist.
     */
    public function deliver(array $payload): ?int;
}
