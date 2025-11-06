<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreTimeEntryContext extends Model
{
    protected $fillable = [
        'time_entry_id',
        'context_type',
        'context_id',
    ];

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(CoreTimeEntry::class, 'time_entry_id');
    }
}


