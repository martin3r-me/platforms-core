<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinTodo extends Model
{
    protected $fillable = [
        'checkin_id',
        'title',
        'done'
    ];

    protected $casts = [
        'done' => 'boolean',
    ];

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(Checkin::class);
    }
}
