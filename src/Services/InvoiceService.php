<?php

namespace Platform\Core\Services;

use Platform\Core\Models\Team;
use Platform\Core\Models\Invoice;
use Platform\Core\Models\InvoiceItem;
use Platform\Core\Models\TeamBillableUsage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceService
{
    /**
     * Erzeugt eine neue Monatsrechnung für ein Team.
     *
     * @param Team   $team
     * @param string $periodStart (Format: Y-m-d)
     * @param string $periodEnd   (Format: Y-m-d)
     * @param float  $taxPercent  (z. B. 0.19 für 19%)
     * @return Invoice
     */
    public function createInvoiceForTeam(Team $team, string $periodStart, string $periodEnd, float $taxPercent = 0.19): Invoice
    {
        return DB::transaction(function () use ($team, $periodStart, $periodEnd, $taxPercent) {
            // Alle Usages für Team und Zeitraum holen
            $usages = TeamBillableUsage::where('team_id', $team->id)
                ->whereBetween('usage_date', [$periodStart, $periodEnd])
                ->get();

            // Gruppieren nach billable_model, billable_type, label
            $items = $usages->groupBy(function ($usage) {
                return $usage->billable_model.'|'.$usage->billable_type.'|'.$usage->label;
            });

            $invoiceTotalNet = 0;
            $invoice = Invoice::create([
                'team_id'      => $team->id,
                'number'       => null, // Optional: Nummer vergeben
                'period_start' => $periodStart,
                'period_end'   => $periodEnd,
                'total_net'    => 0, // wird unten gesetzt
                'tax_percent'  => $taxPercent,
                'total_tax'    => 0, // wird unten gesetzt
                'total_gross'  => 0, // wird unten gesetzt
                'status'       => 'open',
                'meta'         => [],
            ]);

            foreach ($items as $groupKey => $usageGroup) {
                // Summieren für diese Gruppe
                $count = $usageGroup->sum('count');
                $unitPrice = $usageGroup->avg('cost_per_unit'); // oder ein anderes Pricing-Modell
                $total = $usageGroup->sum('total_cost');
                $invoiceTotalNet += $total;

                // Tagesdetails in Array packen
                $daily = $usageGroup->map(fn($u) => [
                    'date' => $u->usage_date->format('Y-m-d'),
                    'count' => $u->count,
                    'cost_per_unit' => $u->cost_per_unit,
                    'total_cost' => $u->total_cost,
                ])->values()->all();

                // Key zerlegen
                [$billableModel, $billableType, $label] = explode('|', $groupKey);

                // Invoice Item anlegen
                InvoiceItem::create([
                    'invoice_id'     => $invoice->id,
                    'label'          => $label,
                    'billable_type'  => $billableType,
                    'billable_model' => $billableModel,
                    'count'          => $count,
                    'unit_price'     => $unitPrice,
                    'total'          => $total,
                    'details'        => ['daily' => $daily],
                ]);
            }

            // Steuer & Brutto berechnen
            $invoice->update([
                'total_net'   => $invoiceTotalNet,
                'total_tax'   => round($invoiceTotalNet * $taxPercent, 2),
                'total_gross' => round($invoiceTotalNet * (1 + $taxPercent), 2),
            ]);

            // Optional: Usages als "verrechnet" markieren oder löschen
            // TeamBillableUsage::where('team_id', $team->id)
            //     ->whereBetween('usage_date', [$periodStart, $periodEnd])
            //     ->delete();

            return $invoice->fresh(['items']);
        });
    }
}