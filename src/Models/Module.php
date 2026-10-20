<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    private const ACCESS_MEMO = 'platform.module-access-memo';

    protected $fillable = [
        'key',
        'title',
        'description',
        'url',
        'config',
        'scope_type',
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

    /**
     * Prüft ob dieses Modul root-scoped ist (parent scope_type).
     * Root-scoped Module verwenden immer die Root-Team-ID, unabhängig vom aktuellen Team.
     *
     * @return bool
     */
    public function isRootScoped(): bool
    {
        return $this->scope_type === 'parent';
    }

    /**
     * Prüft ob dieses Modul team-spezifisch ist (single scope_type).
     * Team-spezifische Module verwenden die aktuelle Team-ID.
     *
     * @return bool
     */
    public function isTeamScoped(): bool
    {
        return $this->scope_type === 'single';
    }

    /**
     * Prüft ob ein User Zugriff auf dieses Modul hat.
     * Berücksichtigt Root-Scoped vs. Team-Scoped Module.
     *
     * @param User $user Der User, dessen Berechtigung geprüft wird
     * @param Team $baseTeam Das Basis-Team des Users (currentTeamRelation)
     * @return bool True wenn der User Zugriff hat, sonst false
     */
    public function hasAccess(User $user, Team $baseTeam): bool
    {
        return in_array($this->key, self::accessibleKeys($user, $baseTeam), true);
    }

    /**
     * Keys aller Module, die der User im Basis-Team nutzen darf — gebündelt
     * (~5 Queries) statt je Modul 2–4 Queries; gemerkt für den laufenden Request.
     * Semantik wie bisher je Modul:
     *  - Team-Scope: root-scoped Module (scope_type=parent) gegen das Root-Team,
     *    sonst gegen das Basis-Team.
     *  - Legacy: User-Freigabe (modulables, team_id = Scope-Team, enabled) ODER
     *    Team-Freigabe (modulables des Scope-Teams, enabled).
     *  - Unter authz.enforce_modules entscheidet der Graph, sobald der User im
     *    Scope-Team überhaupt einen Modul-Grant hat (sonst Legacy, kein Lockout).
     *
     * @return string[]
     */
    public static function accessibleKeys(User $user, Team $baseTeam): array
    {
        // Nur im HTTP-Request merken: Queue-Worker/Konsole teilen ein Request-Objekt über viele Jobs.
        $useMemo = ! app()->runningInConsole();
        $memo = $useMemo ? request()->attributes->get(self::ACCESS_MEMO, []) : [];
        $memoKey = $user->getKey() . ':' . $baseTeam->getKey();
        if (isset($memo[$memoKey])) {
            return $memo[$memoKey];
        }

        $rootTeam = $baseTeam->getRootTeam();
        $teamIds = array_values(array_unique([(int) $baseTeam->id, (int) $rootTeam->id]));

        $userEnabled = [];
        foreach (\Illuminate\Support\Facades\DB::table('modulables')
            ->where('modulable_type', $user->getMorphClass())
            ->where('modulable_id', $user->getKey())
            ->whereIn('team_id', $teamIds)
            ->where('enabled', true)
            ->get(['module_id', 'team_id']) as $row) {
            $userEnabled[$row->module_id . ':' . $row->team_id] = true;
        }

        $teamEnabled = [];
        foreach (\Illuminate\Support\Facades\DB::table('modulables')
            ->where('modulable_type', $baseTeam->getMorphClass())
            ->whereIn('modulable_id', $teamIds)
            ->where('enabled', true)
            ->get(['module_id', 'modulable_id']) as $row) {
            $teamEnabled[$row->module_id . ':' . $row->modulable_id] = true;
        }

        $enforce = (bool) config('authz.enforce_modules');
        $resolver = $enforce ? app(\Platform\Core\Authz\AuthzResolver::class) : null;
        $grantKeys = [];

        $keys = [];
        foreach (static::query()->get(['id', 'key', 'scope_type']) as $module) {
            $scopeTeamId = $module->isRootScoped() ? (int) $rootTeam->id : (int) $baseTeam->id;

            if ($resolver) {
                if (! array_key_exists($scopeTeamId, $grantKeys)) {
                    $grantKeys[$scopeTeamId] = $resolver->moduleGrantKeys($user, $scopeTeamId);
                }
                if ($grantKeys[$scopeTeamId] !== null) {
                    if (in_array($module->key, $grantKeys[$scopeTeamId], true) || in_array('*', $grantKeys[$scopeTeamId], true)) {
                        $keys[] = $module->key;
                    }
                    continue;
                }
            }

            if (isset($userEnabled[$module->id . ':' . $scopeTeamId]) || isset($teamEnabled[$module->id . ':' . $scopeTeamId])) {
                $keys[] = $module->key;
            }
        }

        if ($useMemo) {
            $memo[$memoKey] = $keys;
            request()->attributes->set(self::ACCESS_MEMO, $memo);
        }

        return $keys;
    }

    /**
     * Das Memo hängt am laufenden Request (request()->attributes) — nie request-übergreifend.
     * Wer im SELBEN Request Freigaben ändert und danach erneut prüft, ruft dies auf.
     */
    public static function flushAccessMemo(): void
    {
        request()->attributes->remove(self::ACCESS_MEMO);
    }
}