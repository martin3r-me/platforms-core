<?php

namespace Platform\Core\Services;

use Platform\Core\Models\TeamBillableUsage;
use Platform\Core\Models\Team;
use Platform\Core\PlatformCore;
use Carbon\Carbon;

class UsageTrackingService
{
    /**
     * Trackt für alle Teams und alle aktiven Billables aus allen Modulen die Nutzung am angegebenen Tag.
     *
     * @param string|null $date Format: 'Y-m-d' (Standard: heute)
     * @return void
     */
    public function trackAllUsages(?string $date = null)
    {
        $date = $date ?: Carbon::today()->toDateString();
        $teams = Team::all();

        // Hole alle Module (über Registry!)
        $modules = PlatformCore::getModules();

        foreach ($teams as $team) {
            foreach ($modules as $module) {
                $billables = $module['billables'] ?? [];
                foreach ($billables as $billable) {
                    if (empty($billable['active'])) {
                        continue; // Billable ist inaktiv
                    }

                    // Prüfung: Team ausgeschlossen?
                    if (!empty($billable['exempt_team_ids']) && in_array($team->id, $billable['exempt_team_ids'])) {
                        continue;
                    }

                    // Model-Klasse holen (nur bei 'per_item')
                    $modelClass = $billable['model'] ?? null;

                    $count = 0;
                    if ($billable['type'] === 'per_item' && $modelClass) {
                        $count = (new $modelClass)
                            ->where('team_id', $team->id)
                            ->count(); // ggf. filter für „aktive“ Items!
                    }

                    // Pricing für den Tag bestimmen
                    $price = $this->getCurrentPricing($billable['pricing'] ?? [], $date);

                    // Freikontingent berücksichtigen
                    $freeQuota = $billable['free_quota'] ?? 0;
                    $chargeableCount = max(0, $count - $freeQuota);

                    // Gesamtkosten berechnen
                    $totalCost = $chargeableCount * $price;
                    if (isset($billable['min_cost']) && $billable['min_cost'] !== null) {
                        $totalCost = max($totalCost, $billable['min_cost']);
                    }
                    if (isset($billable['max_cost']) && $billable['max_cost'] !== null) {
                        $totalCost = min($totalCost, $billable['max_cost']);
                    }
                    if (!empty($billable['discount_percent'])) {
                        $totalCost *= (1 - ($billable['discount_percent'] / 100));
                    }

                    // Flat Fee (falls type = flat_fee)
                    if ($billable['type'] === 'flat_fee') {
                        $totalCost = $price;
                        $chargeableCount = 1;
                    }

                    // Usage speichern (upsert)
                    TeamBillableUsage::updateOrCreate(
                        [
                            'team_id' => $team->id,
                            'billable_model' => $modelClass ?? $billable['type'],
                            'usage_date' => $date,
                        ],
                        [
                            'billable_type' => $billable['type'],
                            'label' => $billable['label'],
                            'count' => $chargeableCount,
                            'cost_per_unit' => $price,
                            'total_cost' => $totalCost,
                            'pricing_snapshot' => $billable,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Ermittelt den gültigen Preis für ein Pricing-Array zum gegebenen Datum.
     * @param array $pricingArr
     * @param string $date (Y-m-d)
     * @return float|null
     */
    public function getCurrentPricing(array $pricingArr, string $date): ?float
    {
        foreach ($pricingArr as $price) {
            if (
                $date >= $price['start_date'] &&
                (empty($price['end_date']) || $date <= $price['end_date'])
            ) {
                return $price['cost_per_day'];
            }
        }
        return null; // Oder Standardwert
    }
}