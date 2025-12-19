<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamCounterEvent extends Model
{
    protected $table = 'team_counter_events';

    protected $fillable = [
        'team_counter_definition_id',
        'team_id',
        'user_id',
        'delta',
        'occurred_on',
        'occurred_at',
    ];

    protected $casts = [
        'delta' => 'integer',
        'occurred_on' => 'date',
        'occurred_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TeamCounterDefinition::class, 'team_counter_definition_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


