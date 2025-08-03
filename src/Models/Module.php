<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'url',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function modulables()
    {
        return $this->morphedByMany(User::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard'])
            ->withTimestamps();
        // oder analog für Team etc.
    }
}