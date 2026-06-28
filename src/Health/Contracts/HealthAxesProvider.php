<?php

namespace Platform\Core\Health\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Optionaler Vertrag fuer Modul-Provider, die einen Container (Project,
 * Helpdesk-Board, Dev-Package, ...) auf Health-Achsen abbilden.
 *
 * Module muessen das Interface NICHT zwingend implementieren — sie koennen
 * ihre Berechnungs-Logik auch direkt in ihrem Snapshot-Service halten und
 * den HealthCompositor zur Komposition rufen. Das Interface ist eine
 * Konvention fuer den Fall, dass cross-modulare Aggregat-Sichten
 * (z.B. zentraler Ops-Room) ueber alle Provider iterieren wollen.
 */
interface HealthAxesProvider
{
    /**
     * Eindeutiger Container-Type-Slug, z.B. 'planner_project',
     * 'helpdesk_board', 'dev_package'. Sollte mit den dimension-link
     * morph-aliases uebereinstimmen.
     */
    public function containerType(): string;

    /**
     * Anzeige-Labels pro Achse, z.B. ['backlog' => 'Backlog', 'sla' => 'SLA-Einhaltung'].
     */
    public function axisLabels(): array;

    /**
     * Gewichte pro Achse, sum = 100. Verwendet vom HealthCompositor fuer
     * den gewichteten Score.
     */
    public function axisWeights(): array;

    /**
     * Berechnet die Achsen-Scores fuer einen konkreten Container.
     * Rueckgabe-Format:
     *   [
     *     'axes'        => ['backlog' => 70, 'sla' => 40, ...],
     *     'confidence'  => ['score' => 75, 'reason' => 'missing:sla_definition'],
     *   ]
     * Vorhandene Achsen werden im 'axes'-Array geliefert, fehlende einfach weggelassen.
     */
    public function compute(Model $container): array;
}
