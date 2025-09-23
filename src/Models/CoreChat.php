<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreChat extends Model
{
    protected $table = 'core_chats';

    protected $fillable = [
        'user_id','team_id','title','total_tokens_in','total_tokens_out','status'
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(CoreChatMessage::class, 'core_chat_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CoreChatEvent::class, 'core_chat_id');
    }
}
