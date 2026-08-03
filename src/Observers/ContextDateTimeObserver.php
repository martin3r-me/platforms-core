<?php

namespace Platform\Core\Observers;

use Illuminate\Database\Eloquent\Model;
use Platform\Core\Services\ContextDateTimes\ContextDateTimeSynchronizer;

/**
 * Generischer Observer für den Context-Date-Times-Dual-Write.
 *
 * Wird an Whitelist-Models gehängt (per Trait {@see \Platform\Core\Traits\HasContextDateTimes}
 * oder – für Models ohne Trait – automatisch vom CoreServiceProvider anhand
 * config('core.context_date_times.sync')). Bei jedem Schreibvorgang spiegelt er die
 * konfigurierten Datums-Spalten in core_context_date_times; beim Löschen räumt er auf.
 */
class ContextDateTimeObserver
{
    public function __construct(
        protected ContextDateTimeSynchronizer $synchronizer,
    ) {
    }

    public function saved(Model $model): void
    {
        $this->synchronizer->sync($model, $this->mapFor($model));
    }

    public function restored(Model $model): void
    {
        $this->synchronizer->sync($model, $this->mapFor($model));
    }

    public function deleted(Model $model): void
    {
        // Bei SoftDeletes feuert deleted() beim Soft-Delete → Mirror soft-löschen.
        // forceDeleted() (unten) übernimmt das harte Löschen separat.
        $this->synchronizer->purge($model, $this->mapFor($model), force: false);
    }

    public function forceDeleted(Model $model): void
    {
        $this->synchronizer->purge($model, $this->mapFor($model), force: true);
    }

    /**
     * Field-Map für dieses Model: bevorzugt die Model-eigene Definition
     * (Trait-Methode), sonst die zentrale Whitelist aus config('core').
     *
     * @return array<string, mixed>
     */
    protected function mapFor(Model $model): array
    {
        if (method_exists($model, 'contextDateTimeFieldMap')) {
            return (array) $model->contextDateTimeFieldMap();
        }

        return (array) config('core.context_date_times.sync.'.get_class($model), []);
    }
}
