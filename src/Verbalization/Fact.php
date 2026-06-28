<?php

namespace Platform\Core\Verbalization;

use Platform\Core\Verbalization\Enums\FactPriority;

/**
 * Atomare Tatsachenaussage mit Priorisierung.
 *
 * priority steuert die Dramaturgie der Erzaehlvorlage:
 *  - CORE        → erster Satz / identitaetsstiftend
 *  - QUALIFYING  → zweiter Rang
 *  - CONTEXT     → Beiwerk, darf weggelassen werden
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
    ) {}
}
