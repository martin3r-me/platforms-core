<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Core\Models\Module;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * Migriert den Alt-Stand EINES Teams in den Autorisierungs-Graphen.
 *
 * Bewusst team-scoped (--team=): es geht nur um das eine Team (z.B. BHG.DIGITAL);
 * andere Teams werden nicht angefasst.
 *
 * Bewusst entity-gated: nur User MIT Person-Entity (linked_user_id) werden
 * migriert. Wer keinen Bezug in die Organisation hat, wird STILL ignoriert
 * (kein Fallback) — der Org-Graph ist die alleinige Autorität.
 *
 * Pro migriertem User:
 *  - CONTENT: Grant auf die Team-Wurzel (aus team_user.role), Subjekt = Person-Entity.
 *  - MODULE:  die TATSÄCHLICH erlaubten Module (via Module::hasAccess) als
 *             authz_grant(scope=module, capability=use), Subjekt = Person-Entity.
 *
 * Idempotent (pro Team): löscht die eigenen seed-Quellen dieses Teams und
 * schreibt neu. UI/MCP-Grants (source "ui:" bzw. "mcp:") bleiben unberührt.
 *
 * Rollen-Mapping (Content): owner→owner  admin→owner  member→write  viewer→read  (null→write)
 */
class AuthzSeedGrantsCommand extends Command
{
    protected $signature = 'authz:seed-grants {--team= : Team-ID, die migriert werden soll (Pflicht)}';

    protected $description = 'Migriert den echten Alt-Stand (Rolle + hasAccess-Module) EINES Teams in authz_grant — nur org-verknüpfte User.';

    private const ROLE_TO_CAPABILITY = [
        'owner'  => 'owner',
        'admin'  => 'owner',
        'member' => 'write',
        'viewer' => 'read',
    ];

    private const CONTENT_SOURCE = 'seed:team_user';
    private const MODULE_SOURCE  = 'seed:module';

    private const ALWAYS_ALLOWED = ['core', 'tools', 'communication'];

    public function handle(): int
    {
        $teamId = (int) $this->option('team');
        if ($teamId <= 0) {
            $this->error('Bitte Team angeben: php artisan authz:seed-grants --team=<id>');
            return self::FAILURE;
        }

        $team = Team::find($teamId);
        if (! $team) {
            $this->error("Team {$teamId} nicht gefunden.");
            return self::FAILURE;
        }

        if (! DB::getSchemaBuilder()->hasTable('organization_entities')) {
            $this->error('organization ist nicht installiert/migriert — ohne Org-Graph keine Migration.');
            return self::FAILURE;
        }

        // KRITISCH: Beim Seeden muss Module::hasAccess den ALTEN modulables-Stand
        // lesen, NICHT den Graphen. Sonst vergiftet sich der Seed selbst — nach
        // dem ersten eingefügten Grant würde hasAccess für die Folgemodule den
        // noch unvollständigen Graphen befragen und sie fälschlich verweigern.
        // Enforcement daher prozesslokal für die Dauer des Seeds abschalten.
        config(['authz.enforce_modules' => false]);

        // Idempotent, aber team-scoped: nur die Seeds DIESES Teams entfernen.
        DB::table('authz_grant')
            ->where('team_id', $teamId)
            ->whereIn('source', [self::CONTENT_SOURCE, 'seed:team_user_module', self::MODULE_SOURCE])
            ->delete();

        $now = now();
        $modules = Module::all();

        $migrated = 0;
        $moduleGrants = 0;
        $skipped = [];
        $contentRows = [];

        DB::table('team_user')->where('team_id', $teamId)->orderBy('id')->each(function ($row) use (
            &$migrated, &$moduleGrants, &$skipped, &$contentRows, $now, $teamId, $modules
        ) {
            $user = User::find($row->user_id);
            if (! $user) {
                return;
            }

            // Entity-Gate: nur User MIT Person-Entity in diesem Team.
            $entityId = DB::table('organization_entities')
                ->where('linked_user_id', $row->user_id)
                ->where('team_id', $teamId)
                ->value('id');

            if (! $entityId) {
                $skipped[] = $user->name ?? ('User '.$row->user_id);
                return; // still ignorieren — kein Fallback
            }
            $entityId = (int) $entityId;

            $team = Team::find($teamId);
            $migrated++;

            // (a) Content-Grant auf die Team-Wurzel (aus Rolle), Subjekt = Person-Entity.
            $contentRows[] = [
                'subject_type' => 'entity',
                'subject_id'   => $entityId,
                'capability'   => self::ROLE_TO_CAPABILITY[$row->role] ?? 'write',
                'scope_type'   => 'team',
                'scope_id'     => $teamId,
                'scope_key'    => null,
                'source'       => self::CONTENT_SOURCE,
                'valid_from'   => null,
                'valid_to'     => null,
                'team_id'      => $teamId,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            // (b) Modul-Grants: reale erlaubte Module via hasAccess, Subjekt = Person-Entity.
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

                $exists = DB::table('authz_grant')
                    ->where('subject_type', 'entity')
                    ->where('subject_id', $entityId)
                    ->where('scope_type', 'module')
                    ->where('scope_key', $module->key)
                    ->where('capability', 'use')
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('authz_grant')->insert([
                    'subject_type' => 'entity',
                    'subject_id'   => $entityId,
                    'capability'   => 'use',
                    'scope_type'   => 'module',
                    'scope_id'     => null,
                    'scope_key'    => $module->key,
                    'source'       => self::MODULE_SOURCE,
                    'valid_from'   => null,
                    'valid_to'     => null,
                    'team_id'      => $teamId,
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
            'Team %d (%s): %d User migriert, %d Modul-Grants. %d ohne Person-Entity ignoriert.',
            $teamId,
            $team->name ?? '?',
            $migrated,
            $moduleGrants,
            count($skipped)
        ));
        if ($skipped !== []) {
            $this->line('Ignoriert (kein Org-Bezug): '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }
}
