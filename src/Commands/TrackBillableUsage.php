<?php

namespace Platform\Core\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\UsageTrackingService;

class TrackBillableUsage extends Command
{
    /**
     * Der Konsolenbefehl Name & Beschreibung.
     */
    protected $signature = 'track:billable-usage {--date=}';
    protected $description = 'Trackt tägliche Nutzung aller Billables für alle Teams und speichert sie für die spätere Abrechnung';

    /**
     * @var UsageTrackingService
     */
    protected $usageTrackingService;

    /**
     * Konstruktor mit Service-Injektion
     */
    public function __construct(UsageTrackingService $usageTrackingService)
    {
        parent::__construct();
        $this->usageTrackingService = $usageTrackingService;
    }

    /**
     * Ausführung des Commands.
     */
    public function handle()
    {
        $date = $this->option('date'); // optionales --date=YYYY-MM-DD

        $this->info('Starte Usage-Tracking für '.($date ?: 'heute').' ...');
        $this->usageTrackingService->trackAllUsages($date);
        $this->info('Tracking abgeschlossen.');
    }
}