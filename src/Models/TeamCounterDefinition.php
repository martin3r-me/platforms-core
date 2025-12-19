<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamCounterDefinition extends Model
{
    protected $table = 'team_counter_definitions';

    protected $fillable = [
        'scope_team_id',
        'slug',
        'label',
        'description',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scope_team_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TeamCounterEvent::class, 'team_counter_definition_id');
    }
}


