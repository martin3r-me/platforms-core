<?php

namespace Platform\Core\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Core\Models\CoreContextDateTime;
use Platform\Core\Observers\ContextDateTimeObserver;

/**
 * Macht ein Model zur Quelle kontextgebundener Zeitpunkte.
 *
 * Das Trait tut zweierlei:
 *   1. Es stellt die polymorphe Relation {@see contextDateTimes()} zu
 *      {@see CoreContextDateTime} bereit (context_type/context_id).
 *   2. Es hängt den {@see ContextDateTimeObserver} ans Model (bootHasContextDateTimes()),
 *      der bei jedem Save/Delete die konfigurierten Datums-Spalten in
 *      core_context_date_times spiegelt (Dual-Write).
 *
 * Welche Spalten gespiegelt werden, kommt standardmäßig aus der zentralen
 * Whitelist config('core.context_date_times.sync.<ModelClass>'). Ein Model kann
 * das überschreiben, indem es {@see contextDateTimeFieldMap()} eigenständig
 * implementiert.
 *
 * Verwendung:
 *
 *   class PlannerTask extends Model
 *   {
 *       use HasContextDateTimes;
 *   }
 *
 * Der Rest (Mapping) steht dann in config/core.php.
 */
trait HasContextDateTimes
{
    public static function bootHasContextDateTimes(): void
    {
        static::observe(ContextDateTimeObserver::class);
    }

    /**
     * Alle kontextgebundenen Zeitpunkte dieses Models.
     */
    public function contextDateTimes(): MorphMany
    {
        return $this->morphMany(CoreContextDateTime::class, 'context');
    }

    /**
     * Field-Map "Spalte => ContextDateTimeKind|string|array{kind,label?}".
     *
     * Default: die zentrale Whitelist aus config/core.php. Überschreib diese
     * Methode im Model, um das Mapping model-lokal zu definieren.
     *
     * @return array<string, mixed>
     */
    public function contextDateTimeFieldMap(): array
    {
        return (array) config('core.context_date_times.sync.'.static::class, []);
    }
}
