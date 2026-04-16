<?php

namespace Platform\Core\SemanticLayer\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\SemanticLayer\Models\SemanticLayer;

/**
 * php artisan layer:list
 *
 * Zeigt alle SemanticLayer mit Status, aktiver Version und enabled_modules.
 */
class LayerListCommand extends Command
{
    protected $signature = 'layer:list';

    protected $description = 'Listet alle SemanticLayer (global + team-Scopes) mit ihrem Status.';

    public function handle(): int
    {
        $layers = SemanticLayer::with(['currentVersion', 'versions' => function ($q) {
            $q->orderByDesc('created_at');
        }])->get();

        if ($layers->isEmpty()) {
            $this->info('Keine SemanticLayer vorhanden.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($layers as $layer) {
            $rows[] = [
                'id' => $layer->id,
                'scope' => $layer->scope_type . ($layer->scope_id ? ":{$layer->scope_id}" : ''),
                'status' => $layer->status,
                'active_semver' => $layer->currentVersion?->semver ?? '—',
                'versions' => $layer->versions->count(),
                'enabled_modules' => implode(', ', $layer->enabled_modules ?? []) ?: '—',
            ];
        }

        $this->table(
            ['ID', 'Scope', 'Status', 'Active SemVer', '#Versions', 'Enabled Modules'],
            $rows,
        );

        return self::SUCCESS;
    }
}
