<?php

namespace Platform\Core\SemanticLayer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * php artisan layer:activate --scope=global --semver=1.0.0
 * php artisan layer:activate --scope=team --team-id=9 --semver=0.1.0 --status=pilot
 *
 * Setzt die angegebene Version als aktiv (current_version_id) und den
 * Layer-Status optional auf pilot/production (default: pilot).
 */
class LayerActivateCommand extends Command
{
    protected $signature = 'layer:activate
                            {--scope=global}
                            {--team-id=}
                            {--semver= : Zu aktivierende Version}
                            {--status=pilot : "pilot" oder "production"}';

    protected $description = 'Aktiviert eine Layer-Version (setzt current_version_id + Layer-Status).';

    public function handle(SemanticLayerResolver $resolver): int
    {
        $scope = (string) $this->option('scope');
        $teamId = $this->option('team-id');
        $semver = (string) $this->option('semver');
        $status = (string) $this->option('status');

        if (!in_array($status, [SemanticLayer::STATUS_PILOT, SemanticLayer::STATUS_PRODUCTION], true)) {
            $this->error("--status muss 'pilot' oder 'production' sein");
            return self::FAILURE;
        }

        $layer = SemanticLayer::where('scope_type', $scope)
            ->when($scope === SemanticLayer::SCOPE_TEAM, fn ($q) => $q->where('scope_id', $teamId))
            ->first();

        if (!$layer) {
            $this->error("Kein Layer für scope={$scope}" . ($teamId ? " team={$teamId}" : '') . " gefunden.");
            return self::FAILURE;
        }

        $version = $layer->versions()->where('semver', $semver)->first();
        if (!$version) {
            $this->error("Version {$semver} nicht gefunden.");
            return self::FAILURE;
        }

        DB::transaction(function () use ($layer, $version, $status) {
            $previous = [
                'current_version_id' => $layer->current_version_id,
                'status' => $layer->status,
            ];
            $layer->current_version_id = $version->id;
            $layer->status = $status;
            $layer->save();

            SemanticLayerAudit::record(
                layerId: $layer->id,
                action: 'activated',
                versionId: $version->id,
                diff: null,
                userId: auth()->id(),
                context: [
                    'previous' => $previous,
                    'semver' => $version->semver,
                    'status' => $status,
                ],
            );
        });

        $resolver->forgetCache();

        $this->info("Aktiviert: {$scope}" . ($teamId ? "/{$teamId}" : '') . " → v{$semver} ({$status})");
        return self::SUCCESS;
    }
}
