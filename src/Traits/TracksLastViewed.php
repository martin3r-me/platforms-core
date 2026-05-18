<?php

namespace Platform\Core\Traits;

use Platform\Core\Scopes\StalenessScope;

/**
 * TracksLastViewed - Staleness-System fuer Eloquent Models.
 *
 * Aehnlich wie SoftDeletes: registriert einen Global Scope, der stale Records
 * automatisch aus Queries ausblendet.
 *
 * Usage:
 *   use TracksLastViewed;
 *   protected int $stalenessThresholdDays = 180; // Optional, Default: 90
 *
 * Macros:
 *   Model::withStale()->get()    - Alle Records inkl. stale
 *   Model::onlyStale()->get()    - Nur stale Records
 *
 * Tracking:
 *   $model->recordView()         - Setzt last_viewed_at = now() (via saveQuietly)
 *   $model->isStale()            - Prueft ob Record stale ist
 *   $model->wasNeverViewed()     - Prueft ob last_viewed_at NULL ist
 */
trait TracksLastViewed
{
    /**
     * Boot: Global Scope registrieren.
     */
    public static function bootTracksLastViewed(): void
    {
        static::addGlobalScope(new StalenessScope());

        // Neue Records starten mit last_viewed_at = now(),
        // damit die Staleness-Uhr sofort tickt.
        static::creating(function ($model) {
            if ($model->last_viewed_at === null) {
                $model->last_viewed_at = now();
            }
        });
    }

    /**
     * Initialize: datetime Cast fuer last_viewed_at registrieren.
     */
    public function initializeTracksLastViewed(): void
    {
        $this->casts['last_viewed_at'] = 'datetime';
    }

    /**
     * Setzt last_viewed_at auf jetzt (ohne Audit-Noise via saveQuietly).
     */
    public function recordView(): void
    {
        $this->last_viewed_at = now();
        $this->saveQuietly();
    }

    /**
     * Gibt den Staleness-Threshold in Tagen zurueck.
     * Models koennen dies ueber die Property $stalenessThresholdDays ueberschreiben.
     */
    public function getStalenessThresholdDays(): int
    {
        return $this->stalenessThresholdDays ?? 90;
    }

    /**
     * Prueft ob dieser Record stale ist.
     */
    public function isStale(): bool
    {
        if ($this->last_viewed_at === null) {
            return false; // Noch nie getrackt = nicht stale
        }

        return $this->last_viewed_at->lt(
            now()->subDays($this->getStalenessThresholdDays())
        );
    }

    /**
     * Prueft ob last_viewed_at noch nie gesetzt wurde.
     */
    public function wasNeverViewed(): bool
    {
        return $this->last_viewed_at === null;
    }

    /**
     * Gibt den qualifizierten Spaltennamen fuer last_viewed_at zurueck.
     */
    public function getQualifiedLastViewedAtColumn(): string
    {
        return $this->qualifyColumn('last_viewed_at');
    }
}
