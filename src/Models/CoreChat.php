<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreChat extends Model
{
    protected $table = 'core_chats';

    protected $fillable = [
        'user_id',
        'team_id',
        'title',
        'total_tokens_in',
        'total_tokens_out',
        'status',
    ];

    protected $casts = [
        'total_tokens_in' => 'integer',
        'total_tokens_out' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CoreChatMessage::class, 'chat_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
