<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    /**
     * Die Tabelle für das Modell (falls abweichend vom Namensschema).
     *
     * @var string
     */
    protected $table = 'team_invitations';

    /**
     * Die zuweisbaren Felder.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'email',
        'token',
        'role',
        'accepted_at',
    ];

    /**
     * Typ-Konvertierungen für bestimmte Felder.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /**
     * Beziehung zum Team.
     *
     * @return BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}