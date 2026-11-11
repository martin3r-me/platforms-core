<?php

namespace Platform\Core\Verbalization\Contracts;

use Platform\Core\Verbalization\GuardRails;
use Platform\Core\Verbalization\Recipe\CollectionRecipe;
use Platform\Core\Verbalization\StyleProfile;
use Platform\Core\Verbalization\Subject;
use Platform\Core\Verbalization\VerbalizationResult;

/**
 * ReportEngine — der Facade-Contract, über den PRODUZENTEN-Module (planner,
 * organization, …) das Berichtswesen ansprechen, ohne die konkrete Engine
 * (Verbalizer/RecipeResolver) zu kennen.
 *
 * Bewusst in Core: jedes Modul hängt nur an diesem Interface, NIE am
 * reporting-Modul. Gebunden wird es von reporting; ist das Modul nicht
 * installiert, ist der Contract ungebunden — Produzenten prüfen per
 * app()->bound(ReportEngine::class) und verzichten dann.
 *
 * Deckt genau die Engine-Oberfläche ab, die Module heute direkt aufrufen:
 * ein Recipe auflösen + ein Subject verbalisieren. Primitive/Ports (Subject,
 * StyleProfile, CollectionRecipe, TemplateRegistry) bleiben in Core.
 */
interface ReportEngine
{
    /** Löst ein Collection-Recipe (Konfiguration) auf, oder null. */
    public function resolveRecipe(
        string $key,
        ?int $teamId = null,
        ?string $subjectType = null,
    ): ?CollectionRecipe;

    /** Verbalisiert ein (domänenblind gesammeltes) Subject zu Prosa. */
    public function verbalize(
        Subject $subject,
        ?StyleProfile $style = null,
        ?GuardRails $rails = null,
        ?string $providerKey = null,
        ?string $modelOverride = null,
        ?CollectionRecipe $recipe = null,
    ): VerbalizationResult;
}
