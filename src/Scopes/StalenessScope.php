<?php

namespace Platform\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Carbon;

/**
 * StalenessScope - Global Scope fuer das TracksLastViewed Trait.
 *
 * Blendet Records aus, deren `last_viewed_at` aelter als der konfigurierte Threshold ist.
 * NULL in `last_viewed_at` bedeutet "noch nie getrackt" und wird NICHT als stale behandelt
 * (sichere Migration: bestehende Daten bleiben sichtbar).
 *
 * Macros:
 * - withStale()    - Alle Records inkl. stale
 * - onlyStale()    - Nur stale Records
 * - withoutStale() - Nur frische Records (Default)
 */
class StalenessScope implements Scope
{
    /**
     * Erweitert: Extensions (Macros) fuer den Builder registrieren.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withStale', function (Builder $builder) {
            return $builder->withoutGlobalScope(StalenessScope::class);
        });

        $builder->macro('onlyStale', function (Builder $builder) {
            $model = $builder->getModel();
            $thresholdDays = method_exists($model, 'getStalenessThresholdDays')
                ? $model->getStalenessThresholdDays()
                : 90;

            $threshold = Carbon::now()->subDays($thresholdDays);

            return $builder->withoutGlobalScope(StalenessScope::class)
                ->whereNotNull($model->getQualifiedLastViewedAtColumn())
                ->where($model->getQualifiedLastViewedAtColumn(), '<', $threshold);
        });

        $builder->macro('withoutStale', function (Builder $builder) {
            // Re-apply the default scope (noop wenn bereits aktiv)
            return $builder;
        });
    }

    /**
     * WHERE `last_viewed_at` >= threshold OR `last_viewed_at` IS NULL
     */
    public function apply(Builder $builder, Model $model): void
    {
        $thresholdDays = method_exists($model, 'getStalenessThresholdDays')
            ? $model->getStalenessThresholdDays()
            : 90;

        $threshold = Carbon::now()->subDays($thresholdDays);
        $column = $model->getQualifiedLastViewedAtColumn();

        $builder->where(function (Builder $query) use ($column, $threshold) {
            $query->where($column, '>=', $threshold)
                  ->orWhereNull($column);
        });
    }
}
