<?php

namespace Platform\Core\Datawarehouse;

/**
 * Rückgabe von DatawarehouseSourceProviderInterface::fetch().
 *
 * Plain-PHP-DTO ohne Eloquent-Abhängigkeit, siehe PullContext.
 */
class PullResult
{
    /**
     * @param array<int, array<string, mixed>> $rows       Zeilen als assoziative Arrays (Spaltenname => Wert), passend zu columns()
     * @param string|null                      $nextCursor Cursor für die nächste Seite; null, wenn keine weiteren Daten folgen
     * @param bool                              $hasMore    true, wenn nach dieser Seite weitere Daten existieren
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?string $nextCursor = null,
        public readonly bool $hasMore = false,
    ) {
    }

    public static function empty(): self
    {
        return new self(rows: [], nextCursor: null, hasMore: false);
    }
}
