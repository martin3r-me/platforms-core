<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Eine pluggbare Verknüpfungs-Regel: „kommt ein Item rein, das zu einem anderen
 * gehört → verlinke sie." Jedes Quell-Modul registriert seine Regeln (Aufnahme↔
 * Meeting per Zeit-Overlap, künftig Mail↔Ticket, …); der Kern kennt keine konkrete.
 *
 * Das ist die Trigger→Aktion-Fabrik für Verknüpfungen: der LinkRuleRunner läuft
 * auf ItemDelivered (Core-gelieferte Items) und wird vom Audio-Ingest inline
 * aufgerufen. Regeln MÜSSEN idempotent sein — derselbe Item kann mehrfach durch.
 */
interface LinkRule
{
    /** Verknüpft $itemId mit verwandten Items, falls die Regel greift. Idempotent. */
    public function apply(int $itemId): void;
}
