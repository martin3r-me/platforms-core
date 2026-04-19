<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolCatalog extends Model
{
    protected $fillable = [
        'team_id',
        'catalog',
        'tool_count',
        'period_days',
        'built_at',
    ];

    protected $casts = [
        'catalog' => 'array',
        'tool_count' => 'integer',
        'period_days' => 'integer',
        'built_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
