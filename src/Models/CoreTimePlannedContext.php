<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreTimePlannedContext extends Model
{
    protected $table = 'core_time_planned_contexts';

    protected $fillable = [
        'planned_id',
        'context_type',
        'context_id',
        'depth',
        'is_primary',
        'is_root',
        'context_label',
    ];

    protected $casts = [
        'depth' => 'integer',
        'is_primary' => 'boolean',
        'is_root' => 'boolean',
    ];

    public function planned(): BelongsTo
    {
        return $this->belongsTo(CoreTimePlanned::class, 'planned_id');
    }
}

