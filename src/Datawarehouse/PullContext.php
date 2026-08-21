<?php

namespace Platform\Core\Datawarehouse;

/**
 * Übergabe-Kontext für DatawarehouseSourceProviderInterface::fetch().
 *
 * Plain-PHP-DTO ohne Eloquent-Abhängigkeit, damit Core nicht von
 * platform-datawarehouse abhängen muss. Muss inhaltlich mit dem
 * PullProvider-Contract in platform-datawarehouse abgestimmt bleiben
 * (siehe PHPDoc auf DatawarehouseSourceProviderInterface).
 */
class PullContext
{
    /**
     * @param string      $sourceKey  Stabile Quell-ID, z.B. 'hatch.intake_sessions'
     * @param int|null    $teamId     Team-Scope, wenn der Provider teamScoped() === true liefert
     * @param string|null $cursor     Opaker Fortsetzungs-Cursor der vorherigen Seite; null = erste Seite
     * @param int         $limit      Max. Anzahl Zeilen für diese Seite
     * @param string|null $since      ISO-Zeitstempel; nur Zeilen mit Änderungen ab diesem Zeitpunkt (inkrementeller Pull)
     */
    public function __construct(
        public readonly string $sourceKey,
        public readonly ?int $teamId = null,
        public readonly ?string $cursor = null,
        public readonly int $limit = 500,
        public readonly ?string $since = null,
    ) {
    }
}
