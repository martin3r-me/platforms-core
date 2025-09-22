<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoreCommandRun extends Model
{
    use HasFactory;

    protected $table = 'core_command_runs';

    protected $fillable = [
        'user_id',
        'team_id',
        'command_key',
        'impact',
        'force_execute',
        'slots',
        'result_status',
        'navigate',
        'message',
    ];

    protected $casts = [
        'force_execute' => 'boolean',
        'slots' => 'array',
    ];
}
