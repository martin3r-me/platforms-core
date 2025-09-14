<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    /**
     * Die Tabelle, falls abweichend vom Standard.
     *
     * @var string
     */
    protected $table = 'teams';

    /**
     * Massenzuweisbare Felder.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'personal_team',
        'mollie_customer_id',
        'mollie_payment_method_id',
        'payment_method_last_4',
        'payment_method_brand',
        'payment_method_expires_at',
    ];

    /**
     * Typkonvertierungen.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
        'payment_method_expires_at' => 'datetime',
    ];

    /**
     * Mitglieder dieses Teams.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Einladungen zu diesem Team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function modules()
    {
        return $this->morphToMany(Module::class, 'modulable')
            ->withPivot(['role', 'enabled', 'guard'])
            ->withTimestamps();
    }
}