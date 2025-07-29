<?php
namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class TeamBillableUsage extends Model
{
    // Tabellenname (optional, wenn Namenskonvention nicht zutrifft)
    protected $table = 'team_billable_usages';

    /**
     * Massenweise befüllbare Felder
     */
    protected $fillable = [
        'team_id',
        'billable_model',
        'billable_type',
        'label',
        'usage_date',
        'count',
        'cost_per_unit',
        'total_cost',
        'pricing_snapshot',
    ];

    /**
     * Type Casting für Felder
     */
    protected $casts = [
        'usage_date' => 'date',
        'pricing_snapshot' => 'array',
        'cost_per_unit' => 'float',
        'total_cost' => 'float',
        'count' => 'integer',
    ];

    // Beziehungen (optional, falls du Team direkt verknüpfen willst)
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope: Nur Einträge für einen bestimmten Zeitraum holen
     */
    public function scopeForPeriod($query, $start, $end)
    {
        return $query->whereBetween('usage_date', [$start, $end]);
    }

    /**
     * Scope: Für ein bestimmtes billable model
     */
    public function scopeForBillableModel($query, $model)
    {
        return $query->where('billable_model', $model);
    }
}