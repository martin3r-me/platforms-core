<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Grant im Autorisierungs-Graphen.
 *
 * scope_type = 'module' → scope_key trägt den Modul-Key (oder '*')  → Toolbelt/Nav
 * scope_type = 'team'   → scope_id trägt die Team-Wurzel (Bootstrap) → Content
 * scope_type = 'entity' → scope_id trägt eine OrganizationEntity     → Content (+ Subtree via Closure)
 */
class AuthzGrant extends Model
{
    protected $table = 'authz_grant';

    protected $guarded = [];

    protected $casts = [
        'subject_id' => 'integer',
        'scope_id'   => 'integer',
        'team_id'    => 'integer',
        'valid_from' => 'datetime',
        'valid_to'   => 'datetime',
    ];
}
