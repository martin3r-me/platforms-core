<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * Spiegelt den heutigen Zustand (team_user + modulables) in den Grant-Store.
 *
 * - CONTENT: pro Mitgliedschaft ein Grant auf die Team-Wurzel (aus team_user.role).
 * - MODULE:  pro User die TATSÄCHLICH erlaubten Module (via Module::hasAccess),
 *            als authz_grant(scope=module, capability=use). Ersetzt das frühere
 *            grobe `use '*'` durch den echten per-User-Alt-Stand.
 *
 * Subjekt der Modul-Grants: das Person-Entity (linked_user_id), falls vorhanden
 * — damit der "Module"-Tab sie zeigt; sonst der User selbst (Fallback, greift
 * trotzdem, weil der Resolver User- UND Entity-Subjekt prüft).
 *
 * Idempotent: löscht die eigenen seed-Quellen und schreibt neu. Manuell/per UI
 * gesetzte Grants (source "ui:" bzw. "mcp:") bleiben unberührt und nicht doppelt.
 *
 * Rollen-Mapping (Content):  owner→owner  admin→owner  member→write  viewer→read  (null→write)
 */
class AuthzSeedGrantsCommand extends Command
{
    protected $signature = 'authz:seed-grants';

    protected $description = 'Spiegelt team_user.role + den echten per-User-Modul-Stand (hasAccess) in authz_grant.';

    private const ROLE_TO_CAPABILITY = [
        'owner'  => 'owner',
        'admin'  => 'owner',
        'member' => 'write',
        'viewer' => 'read',
    ];

    private const CONTENT_SOURCE = 'seed:team_user';
    private const MODULE_SOURCE  = 'seed:module';

    // Pseudo-Module, die immer erlaubt sind — kein Grant nötig.
    private const ALWAYS_ALLOWED = ['core', 'tools', 'communication'];

    public function handle(): int
    {
        // Idempotent: eigene Seeds entfernen (Content + alter Wildcard + Modul-Seed).
        DB::table('authz_grant')
            ->whereIn('source', [self::CONTENT_SOURCE, 'seed:team_user_module', self::MODULE_SOURCE])
            ->delete();

        $now = now();
        $hasEntities = DB::getSchemaBuilder()->hasTable('organization_entities');
        $modules = Module::all();

        $memberships = 0;
        $moduleGrants = 0;
        $contentRows = [];

        DB::table('team_user')->orderBy('id')->each(function ($row) use (
            &$memberships, &$moduleGrants, &$contentRows, $now, $hasEntities, $modules
        ) {
            $user = User::find($row->user_id);
            $team = Team::find($row->team_id);
            if (! $user || ! $team) {
                return;
            }
            $memberships++;

            // Subjekt bestimmen: Person-Entity falls vorhanden, sonst User.
            $subjectType = 'user';
            $subjectId = (int) $row->user_id;
            if ($hasEntities) {
                $entityId = DB::table('organization_entities')
                    ->where('linked_user_id', $row->user_id)
                    ->where('team_id', $row->team_id)
                    ->value('id');
                if ($entityId) {
                    $subjectType = 'entity';
                    $subjectId = (int) $entityId;
                }
            }

            // (a) Content-Grant auf die Team-Wurzel (aus Rolle) — Subjekt = User.
            $contentRows[] = [
                'subject_type' => 'user',
                'subject_id'   => (int) $row->user_id,
                'capability'   => self::ROLE_TO_CAPABILITY[$row->role] ?? 'write',
                'scope_type'   => 'team',
                'scope_id'     => (int) $row->team_id,
                'scope_key'    => null,
                'source'       => self::CONTENT_SOURCE,
                'valid_from'   => null,
                'valid_to'     => null,
                'team_id'      => (int) $row->team_id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            // (b) Modul-Grants: reale erlaubte Module via hasAccess.
            foreach ($modules as $module) {
                if (in_array($module->key, self::ALWAYS_ALLOWED, true)) {
                    continue;
                }
                try {
                    if (! $module->hasAccess($user, $team)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                // Nicht doppeln, wenn (z.B. per UI/MCP) schon ein Grant existiert.
                $exists = DB::table('authz_grant')
                    ->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->where('scope_type', 'module')
                    ->where('scope_key', $module->key)
                    ->where('capability', 'use')
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('authz_grant')->insert([
                    'subject_type' => $subjectType,
                    'subject_id'   => $subjectId,
                    'capability'   => 'use',
                    'scope_type'   => 'module',
                    'scope_id'     => null,
                    'scope_key'    => $module->key,
                    'source'       => self::MODULE_SOURCE,
                    'valid_from'   => null,
                    'valid_to'     => null,
                    'team_id'      => (int) $row->team_id,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
                $moduleGrants++;
            }
        });

        foreach (array_chunk($contentRows, 500) as $chunk) {
            DB::table('authz_grant')->insert($chunk);
        }

        $this->info(sprintf(
            'Seeded %d Mitgliedschaften: Content-Grants (Team-Wurzel) + %d Modul-Grants (echter Alt-Stand via hasAccess).',
            $memberships,
            $moduleGrants
        ));

        return self::SUCCESS;
    }
}
