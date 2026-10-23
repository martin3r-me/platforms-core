<?php

namespace Platform\Core\Terminal\Contracts;

use Platform\Core\Terminal\TerminalContext;

/**
 * Optional für TerminalApps: kurze Kennzahl für den Reiter im Kontext-Panel
 * („3", „12h"). null = nichts dran → Reiter wird blass dargestellt.
 * Apps ohne dieses Interface haben keinen Zähler und bleiben normal.
 * Muss billig sein (eine Abfrage) — wird bei jedem Kontext-/Reiterwechsel gerechnet.
 */
interface HasBadge
{
    public function badge(TerminalContext $context): ?string;
}
