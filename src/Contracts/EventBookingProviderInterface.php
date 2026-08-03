<?php

namespace Platform\Core\Contracts;

interface EventBookingProviderInterface
{
    /**
     * Liefert Buchungen für die Locations-Auslastungsansicht.
     *
     * Rückgabe-Shape:
     *   [date (Y-m-d) => [location_id => [{ title, optionsrang, datum, beginn, ende, pers, location_id }, ...]]]
     *
     * @param  int         $teamId
     * @param  array<int>  $locationIds  Whitelist; leer = alle Locations des Teams
     * @param  string      $periodStart  Y-m-d
     * @param  string      $periodEnd    Y-m-d
     * @return array<string, array<int, array<int, object>>>
     */
    public function bookingsForLocations(int $teamId, array $locationIds, string $periodStart, string $periodEnd): array;
}
