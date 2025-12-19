<?php

namespace Platform\Core\Contracts;

/**
 * Loose Coupling:
 * Core kennt nur dieses Contract. Ein Modul (z.B. OKR) kann eine echte Implementierung binden.
 * Default ist ein No-Op.
 */
interface CounterKeyResultSyncer
{
    /**
     * Synchronisiert alle verknüpften Counter-Werte in KeyResults.
     *
     * @return int Anzahl aktualisierter KeyResults (oder Sync-Aktionen)
     */
    public function syncAll(bool $dryRun = false): int;
}


