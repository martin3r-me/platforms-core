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
 *
 * hashKey ist die semantisch stabile Version des Facts fuer den State-Dedup.
 * Wenn null, wird text als Hash-Grundlage genommen. Facts mit zeit-driftigen
 * Formulierungen (z.B. "vor 5 Tagen") setzen hashKey explizit auf eine
 * stabile Signatur (z.B. "created_at=2026-07-02"), damit der Dedup nicht
 * durch reine Zeit-Verstreichung ausgehebelt wird.
 */
final class Fact
{
    public function __construct(
        public readonly FactPriority $priority,
        public readonly string $text,
        public readonly ?string $sourceCode = null,
        public readonly FactNature $nature = FactNature::STATE,
        public readonly ?string $hashKey = null,
    ) {}

    /**
     * Signatur fuer den State-Dedup. Zieht hashKey vor, faellt sonst auf text
     * zurueck. Damit hasht das Subject semantisch statt textuell.
     */
    public function hashSignature(): string
    {
        return $this->hashKey ?? $this->text;
    }
}
