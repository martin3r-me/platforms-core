<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function fullname(): Attribute
    {
        return Attribute::get(fn () => trim($this->name . ' ' . ($this->lastname ?? '')));
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    // Beispiel: Active Team (optional)
    public function currentTeam()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function modules()
    {
        return $this->morphToMany(Module::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard'])
            ->withTimestamps();
    }
}