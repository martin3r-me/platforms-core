<?php

namespace Platform\Core\Health\Traits;

/**
 * Eloquent-Trait fuer Snapshot-Modelle. Merged das Standard-Set an
 * fillable + casts in das Model, sodass das Modul nur seine eigenen
 * Felder ergaenzen muss.
 *
 * Eloquent ruft automatisch initialize{TraitName}() beim Booten,
 * deswegen mergen wir dort.
 *
 * Verwendung:
 *
 *   class HelpdeskBoardSnapshot extends Model
 *   {
 *       use HasHealthSnapshotData;
 *
 *       protected $table = 'helpdesk_board_snapshots';
 *
 *       protected $fillable = [
 *           'uuid',
 *           'helpdesk_board_id',
 *           // modul-spezifische Felder
 *       ];
 *
 *       protected $casts = [
 *           // modul-spezifische casts
 *       ];
 *   }
 *
 * Nach initializeHasHealthSnapshotData() sind die Standard-Felder
 * (taken_at, taken_on, health_score, ...) automatisch dabei.
 */
trait HasHealthSnapshotData
{
    public function initializeHasHealthSnapshotData(): void
    {
        $this->mergeFillable([
            'uuid',
            'taken_at',
            'taken_on',
            'trigger',
            'team_id',
            'health_score',
            'health_color',
            'worst_axis',
            'axis_scores',
            'confidence_score',
            'confidence_reason',
            'frozen_context',
            'prev_snapshot_id',
            'delta_health_score',
            'last_movement_at',
        ]);

        $this->mergeCasts([
            'taken_at' => 'datetime',
            'taken_on' => 'date',
            'last_movement_at' => 'datetime',
            'axis_scores' => 'array',
            'frozen_context' => 'array',
        ]);
    }
}
