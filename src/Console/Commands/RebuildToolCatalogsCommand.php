<?php

namespace Platform\Core\Console\Commands;

use Platform\Core\Models\Team;
use Platform\Core\Services\ToolCatalogService;
use Platform\Core\Services\ToolInsightsService;
use Illuminate\Console\Command;

class RebuildToolCatalogsCommand extends Command
{
    protected $signature = 'catalog:rebuild {--team= : Rebuild only for a specific team ID}';

    protected $description = 'Rebuild tool catalogs for all teams (or a specific team)';

    public function handle(ToolCatalogService $service, ToolInsightsService $insightsService): int
    {
        $teamId = $this->option('team');

        if ($teamId) {
            $team = Team::find($teamId);
            if (!$team) {
                $this->error("Team {$teamId} not found.");
                return self::FAILURE;
            }

            $service->buildForTeam($team);
            $this->info("Catalog rebuilt for team {$team->name} (ID: {$team->id}).");
        } else {
            $count = $service->rebuildAll();
            $this->info("Rebuilt {$count} tool catalog(s).");
        }

        $insightsService->rebuild();
        $this->info('Tool insights (co-occurrence + examples) rebuilt.');

        return self::SUCCESS;
    }
}
