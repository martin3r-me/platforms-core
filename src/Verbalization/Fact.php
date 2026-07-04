<?php

namespace Platform\Core\Verbalization;

use Platform\Core\Verbalization\Enums\FactNature;
use Platform\Core\Verbalization\Enums\FactPriority;

/**
 * Atomare Tatsachenaussage mit Priorisierung und Natur.
 *
 * priority steuert die Dramaturgie der Erzaehlvorlage:
 *  - CORE        → erster Satz / identitaetsstiftend
 *  - QUALIFYING  → zweiter Rang
 *  - CONTEXT     → Beiwerk, darf weggelassen werden
 *
 * nature steuert die inhaltliche Ebene (State / Movement / Derivation) und
 * ermoeglicht Recipes, per include_natures pro Kanal zu filtern. Default STATE.
 *
 * sourceCode ist rein Debug/Traceback ("woher stammt dieser Fakt?"),
 * landet nicht in der Prosa.
 */
final class Fact
{
    public function __construct(
        public readonly FactPriority $priority,
        public readonly string $text,
        public readonly ?string $sourceCode = null,
        public readonly FactNature $nature = FactNature::STATE,
    ) {}
}
