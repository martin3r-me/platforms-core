<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamCoreAiModel extends Model
{
    protected $table = 'team_core_ai_models';

    protected $fillable = [
        'scope_team_id',
        'core_ai_model_id',
        'is_enabled',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function scopeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'scope_team_id');
    }

    public function coreAiModel(): BelongsTo
    {
        return $this->belongsTo(CoreAiModel::class, 'core_ai_model_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
