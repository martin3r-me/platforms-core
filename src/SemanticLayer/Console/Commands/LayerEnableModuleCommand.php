<?php

namespace Platform\Core\SemanticLayer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * php artisan layer:enable-module --scope=global --module=okr
 * php artisan layer:enable-module --scope=team --team-id=9 --module=canvas --disable
 *
 * Schaltet ein Modul für einen Layer frei (oder wieder ab mit --disable).
 * Feature-Flag-Mechanik des Cold-Starts: ohne diesen Eintrag wirkt der Layer nicht.
 */
class LayerEnableModuleCommand extends Command
{
    protected $signature = 'layer:enable-module
                            {--scope=global}
                            {--team-id=}
                            {--module= : Modul-Key, z.B. "okr" oder "canvas"}
                            {--disable : Modul wieder deaktivieren}';

    protected $description = 'Aktiviert (oder deaktiviert) ein Modul auf einem SemanticLayer (Cold-Start Feature-Flag).';

    public function handle(SemanticLayerResolver $resolver): int
    {
        $scope = (string) $this->option('scope');
        $teamId = $this->option('team-id');
        $module = (string) ($this->option('module') ?? '');
        $disable = (bool) $this->option('disable');

        if ($module === '') {
            $this->error('--module ist erforderlich');
            return self::FAILURE;
        }

        $layer = SemanticLayer::where('scope_type', $scope)
            ->when($scope === SemanticLayer::SCOPE_TEAM, fn ($q) => $q->where('scope_id', $teamId))
            ->first();

        if (!$layer) {
            $this->error("Kein Layer für scope={$scope}" . ($teamId ? " team={$teamId}" : '') . " gefunden.");
            return self::FAILURE;
        }

        DB::transaction(function () use ($layer, $module, $disable) {
            $modules = $layer->enabled_modules ?? [];
            $before = $modules;

            if ($disable) {
                $modules = array_values(array_filter($modules, fn ($m) => $m !== $module));
                $action = 'disabled_module';
            } else {
                if (!in_array($module, $modules, true)) {
                    $modules[] = $module;
                }
                $action = 'enabled_module';
            }

            $layer->enabled_modules = array_values(array_unique($modules));
            $layer->save();

            SemanticLayerAudit::record(
                layerId: $layer->id,
                action: $action,
                versionId: $layer->current_version_id,
                diff: [
                    ['field' => 'enabled_modules', 'op' => 'changed', 'from' => $before, 'to' => $layer->enabled_modules],
                ],
                userId: auth()->id(),
                context: ['module' => $module],
            );
        });

        $resolver->forgetCache();

        $action = $disable ? 'deaktiviert' : 'aktiviert';
        $this->info("Modul '{$module}' für {$scope}" . ($teamId ? "/{$teamId}" : '') . " {$action}.");
        $this->line('Enabled: ' . (implode(', ', $layer->fresh()->enabled_modules ?? []) ?: '—'));
        return self::SUCCESS;
    }
}
