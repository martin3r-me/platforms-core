<?php

namespace Platform\Core\Verbalization\SubjectCollector;

use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\Subject;

/**
 * Mindest-Vertrag fuer Subject-Collectors, die in der SubjectCollectorRegistry
 * registriert werden.
 *
 * Module-Collectors (z.B. PlannerProjectSubjectCollector) implementieren das
 * Interface optional — der FeedService akzeptiert auch nur das Subject
 * via callable. Aber ein Interface macht es deklarativ.
 */
interface SubjectCollectorInterface
{
    /**
     * Welcher Subject-Type wird hier behandelt? (z.B. "planner_project")
     */
    public function handles(): string;

    /**
     * Baut ein Subject aus einer Subject-ID oder einer bereits geladenen Entity-Instanz.
     * Erstparameter ist bewusst mixed — Module-Implementierungen duerfen ihre
     * Models direkt als Shortcut akzeptieren.
     */
    public function collectState(mixed $subject, ?CollectionRecipe $recipe = null): Subject;
}
