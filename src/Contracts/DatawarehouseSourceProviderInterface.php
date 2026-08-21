<?php

namespace Platform\Core\Contracts;

use Platform\Core\Datawarehouse\PullContext;
use Platform\Core\Datawarehouse\PullResult;

/**
 * Contract für Module, die ihre Daten als Stream-Quelle für platform-datawarehouse
 * anbieten wollen, ohne dass eines der beiden Module das andere kennt — beide
 * hängen nur von Core ab (analog zu CrmCompanyContactsProviderInterface,
 * CatalogListProviderInterface, EventBookingProviderInterface, …).
 *
 * Beispiel: platform-hatch registriert eine HatchSessionsStreamSource für seine
 * HatchIntakeSession-Daten (key: 'hatch.intake_sessions'), damit
 * platform-datawarehouse darauf KPIs/Dashboards (z.B. Wochenfeedback pro KW pro
 * Einrichtung) aufbauen kann.
 *
 * Module registrieren sich via resolve(DatawarehouseSourceRegistry::class)->register($this)
 * im Boot; platform-datawarehouse liest die Quellen über dieselbe Registry
 * (all() / get($key)) und exponiert sie als PullProvider.
 *
 * WICHTIG: PullContext/PullResult sind bewusst als schlanke Plain-PHP-DTOs (ohne
 * Eloquent-Abhängigkeit) in Core dupliziert. Sie müssen inhaltlich mit dem
 * PullProvider-Contract in platform-datawarehouse abgestimmt/synchron gehalten
 * werden.
 */
interface DatawarehouseSourceProviderInterface
{
    /**
     * Stabile Quell-ID, z.B. 'hatch.intake_sessions'.
     */
    public function key(): string;

    public function label(): string;

    public function description(): ?string;

    /**
     * true = ein Stream pro Team, false = ein globaler Stream.
     */
    public function teamScoped(): bool;

    /**
     * Spalten-Schema für die dynamische Tabelle. Format kompatibel zu
     * DatawarehouseStreamColumn in platform-datawarehouse.
     *
     * Jeder Eintrag mindestens:
     *   column_name  string
     *   data_type    string
     *   is_indexed   bool
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array;

    /**
     * Liefert die nächste Seite an Daten für diese Quelle.
     */
    public function fetch(PullContext $ctx): PullResult;
}
