<?php

namespace Platform\Core\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Core\Services\InvoiceService;
use Carbon\Carbon;

class CreateMonthlyInvoices extends Command
{
    protected $signature = 'invoice:monthly {--date=}';
    protected $description = 'Erzeugt Monatsrechnungen für alle Teams für den angegebenen oder aktuellen Monat';

    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
    }

    public function handle()
    {
        // Rechnungsmonat bestimmen
        $date = $this->option('date') ?? Carbon::now()->subMonth()->toDateString(); // Standard: Vormonat
        $start = Carbon::parse($date)->startOfMonth()->toDateString();
        $end = Carbon::parse($date)->endOfMonth()->toDateString();

        $tax = env('PLATFORM_MWST', 0.19);

        $this->info("Erzeuge Rechnungen für Zeitraum {$start} bis {$end} …");

        foreach (Team::all() as $team) {
            $invoice = $this->invoiceService->createInvoiceForTeam($team, $start, $end, $tax);

            $this->info("→ Rechnung #{$invoice->id} für Team '{$team->name}' ({$team->id}) mit Gesamtbetrag {$invoice->total_gross} € erzeugt.");
        }

        $this->info('Alle Rechnungen erstellt!');
    }
}