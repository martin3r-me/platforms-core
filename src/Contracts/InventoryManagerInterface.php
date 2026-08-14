<?php

namespace Platform\Core\Contracts;

/**
 * Contract fuer die Bestandsfuehrung (das Bestands-Hauptbuch).
 *
 * Commerce ist die Heimat des Bestands (Levels, Bewegungen, Reservierungen,
 * Chargen). Andere Module (z.B. Einkauf/Wareneingang, Verkauf/Auslieferung)
 * buchen NICHT direkt gegen die Commerce-Models, sondern gegen dieses Interface.
 * Jede Buchung haengt ihren Herkunfts-Beleg als polymorphe Referenz an
 * ($referenceType/$referenceId) – daraus entsteht die Rueckverfolgung.
 *
 * Rueckgaben sind bewusst primitiv (Arrays/Float), damit Core nicht von den
 * Commerce-Models abhaengt (analog zu CatalogArticleResolverInterface).
 *
 * @see \Platform\Commerce\Services\CoreInventoryManager fuer die Implementierung
 */
interface InventoryManagerInterface
{
    /**
     * Bucht einen Zugang (z.B. Wareneingang) auf ein Lager und schreibt eine
     * Inbound-Bewegung ins Hauptbuch.
     *
     * @return array{article_id: int, warehouse_id: int, quantity: float, reserved_quantity: float, available: float}
     */
    public function addStock(
        int $articleId,
        int $warehouseId,
        float $quantity,
        int $teamId,
        ?int $userId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array;

    /**
     * Bucht einen Abgang (z.B. Auslieferung) von einem Lager und schreibt eine
     * Outbound-Bewegung. Wirft, wenn nicht genug verfuegbarer Bestand da ist.
     *
     * @return array{article_id: int, warehouse_id: int, quantity: float, reserved_quantity: float, available: float}
     */
    public function removeStock(
        int $articleId,
        int $warehouseId,
        float $quantity,
        int $teamId,
        ?int $userId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array;

    /**
     * Verschiebt Bestand von einem Lager in ein anderes (Transfer-Bewegung).
     *
     * @return array{from: array{article_id: int, warehouse_id: int, quantity: float, reserved_quantity: float, available: float}, to: array{article_id: int, warehouse_id: int, quantity: float, reserved_quantity: float, available: float}}
     */
    public function transferStock(
        int $articleId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $quantity,
        int $teamId,
        ?int $userId = null,
        ?string $reason = null,
    ): array;

    /**
     * Reserviert verfuegbaren Bestand. Liefert die Reservierung inkl. ID zurueck,
     * mit der spaeter releaseReservation() aufgerufen werden kann.
     *
     * @return array{reservation_id: int, article_id: int, warehouse_id: int, quantity: float, expires_at: ?string}
     */
    public function reserveStock(
        int $articleId,
        int $warehouseId,
        float $quantity,
        int $teamId,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array;

    /**
     * Loest eine Reservierung wieder auf (gibt reservierten Bestand frei) und
     * schreibt eine ReservationRelease-Bewegung.
     */
    public function releaseReservation(int $reservationId): void;

    /**
     * Verfuegbarer Bestand (quantity - reserved_quantity), optional pro Lager,
     * sonst ueber alle Lager des Teams summiert.
     */
    public function getAvailableStock(int $articleId, int $teamId, ?int $warehouseId = null): float;
}
