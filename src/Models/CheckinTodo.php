<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\Core\Traits\Encryptable;

class CheckinTodo extends Model
{
    use Encryptable;

    protected $fillable = [
        'checkin_id',
        'title',
        'done'
    ];

    protected $casts = [
        'done' => 'boolean',
        'title' => \Platform\Core\Casts\EncryptedString::class,
    ];

    protected array $encryptable = [
        'title' => 'string',
    ];

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(Checkin::class);
    }
}
