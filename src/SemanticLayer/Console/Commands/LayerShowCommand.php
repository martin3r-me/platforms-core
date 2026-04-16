<?php

namespace Platform\Core\SemanticLayer\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * php artisan layer:show --scope=global
 * php artisan layer:show --scope=team --team-id=9
 * php artisan layer:show --resolved --team-id=9 --module=okr
 *
 * Zeigt den Roh-Layer (Layer + aktive Version) oder — mit --resolved — den
 * final gemergten ResolvedLayer inkl. Rendered-Prompt-Block.
 */
class LayerShowCommand extends Command
{
    protected $signature = 'layer:show
                            {--scope=global}
                            {--team-id=}
                            {--module= : Modul-Key für --resolved}
                            {--resolved : Zeigt den gemergten ResolvedLayer statt des Roh-Layers}
                            {--json : Ausgabe als JSON}';

    protected $description = 'Zeigt einen SemanticLayer — roh oder gemergt (--resolved).';

    public function handle(SemanticLayerResolver $resolver): int
    {
        if ($this->option('resolved')) {
            return $this->showResolved($resolver);
        }
        return $this->showRaw();
    }

    private function showRaw(): int
    {
        $scope = (string) $this->option('scope');
        $teamId = $this->option('team-id');

        $layer = SemanticLayer::where('scope_type', $scope)
            ->when($scope === SemanticLayer::SCOPE_TEAM, fn ($q) => $q->where('scope_id', $teamId))
            ->with('currentVersion')
            ->first();

        if (!$layer) {
            $this->error("Kein Layer für scope={$scope}" . ($teamId ? " team={$teamId}" : '') . " gefunden.");
            return self::FAILURE;
        }

        $data = [
            'id' => $layer->id,
            'scope_type' => $layer->scope_type,
            'scope_id' => $layer->scope_id,
            'status' => $layer->status,
            'enabled_modules' => $layer->enabled_modules ?? [],
            'current_version' => $layer->currentVersion ? [
                'id' => $layer->currentVersion->id,
                'semver' => $layer->currentVersion->semver,
                'version_type' => $layer->currentVersion->version_type,
                'token_count' => $layer->currentVersion->token_count,
                'perspektive' => $layer->currentVersion->perspektive,
                'ton' => $layer->currentVersion->ton,
                'heuristiken' => $layer->currentVersion->heuristiken,
                'negativ_raum' => $layer->currentVersion->negativ_raum,
            ] : null,
        ];

        $this->output($data);
        return self::SUCCESS;
    }

    private function showResolved(SemanticLayerResolver $resolver): int
    {
        $teamId = $this->option('team-id');
        $module = $this->option('module');

        $team = $teamId ? Team::find($teamId) : null;
        if ($teamId && !$team) {
            $this->error("Team #{$teamId} nicht gefunden");
            return self::FAILURE;
        }

        $resolved = $resolver->resolveFor($team, $module);
        if ($resolved->isEmpty()) {
            $this->warn('ResolvedLayer ist LEER (kein aktiver Layer, Modul nicht enabled, oder nichts gefunden).');
            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($resolved->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->line('Scope-Chain:   ' . implode(' → ', $resolved->scope_chain));
        $this->line('Version-Chain: ' . implode(' + ', $resolved->version_chain));
        $this->line('Token-Count:   ' . $resolved->token_count);
        $this->newLine();
        $this->line($resolved->rendered_block ?? '');
        return self::SUCCESS;
    }

    private function output(array $data): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return;
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $this->line(str_pad($k . ':', 20) . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->line(str_pad($k . ':', 20) . (string) $v);
            }
        }
    }
}
