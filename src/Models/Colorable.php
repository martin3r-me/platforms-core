<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Colorable extends Model
{
    protected $table = 'colorables';

    public $timestamps = true;

    protected $fillable = [
        'color',
        'colorable_type',
        'colorable_id',
        'user_id',
        'team_id',
    ];

    protected $casts = [
        'colorable_id' => 'integer',
        'user_id' => 'integer',
        'team_id' => 'integer',
    ];
}
