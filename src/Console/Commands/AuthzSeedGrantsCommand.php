<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Spiegelt den heutigen Zustand (team_user.role) in den Grant-Store.
 *
 * Damit reproduziert der Graph im Shadow-Mode die bestehende Team-Rolle
 * exakt. Idempotent: löscht zuerst alle Grants mit source='seed:team_user'
 * und schreibt sie neu.
 *
 * Rollen-Mapping (Hypothese — Abweichungen zeigt authz_shadow_log):
 *   owner  → owner     admin → owner
 *   member → write     viewer → read     (null → write)
 */
class AuthzSeedGrantsCommand extends Command
{
    protected $signature = 'authz:seed-grants {--fresh : seed-Grants vorher komplett entfernen}';

    protected $description = 'Spiegelt team_user.role in den Autorisierungs-Graphen (authz_grant).';

    private const ROLE_TO_CAPABILITY = [
        'owner'  => 'owner',
        'admin'  => 'owner',
        'member' => 'write',
        'viewer' => 'read',
    ];

    public function handle(): int
    {
        $source = 'seed:team_user';

        DB::table('authz_grant')->where('source', $source)->delete();
        DB::table('authz_grant')->where('source', 'seed:team_user_module')->delete();

        $now = now();
        $contentRows = [];
        $moduleRows = [];
        $count = 0;

        DB::table('team_user')->orderBy('id')->each(function ($row) use (&$contentRows, &$moduleRows, &$count, $now, $source) {
            $capability = self::ROLE_TO_CAPABILITY[$row->role] ?? 'write';

            // (a) Content: Grant auf die Team-Wurzel (Bootstrap-Scope).
            $contentRows[] = [
                'subject_type' => 'user',
                'subject_id'   => $row->user_id,
                'capability'   => $capability,
                'scope_type'   => 'team',
                'scope_id'     => $row->team_id,
                'scope_key'    => null,
                'source'       => $source,
                'valid_from'   => null,
                'valid_to'     => null,
                'team_id'      => $row->team_id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            // (b) Toolbelt: pauschaler Modul-Zugang (alle Module verfügbar).
            $moduleRows[] = [
                'subject_type' => 'user',
                'subject_id'   => $row->user_id,
                'capability'   => 'use',
                'scope_type'   => 'module',
                'scope_id'     => null,
                'scope_key'    => '*',
                'source'       => 'seed:team_user_module',
                'valid_from'   => null,
                'valid_to'     => null,
                'team_id'      => $row->team_id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            $count++;

            if (count($contentRows) >= 500) {
                DB::table('authz_grant')->insert($contentRows);
                DB::table('authz_grant')->insert($moduleRows);
                $contentRows = [];
                $moduleRows = [];
            }
        });

        if ($contentRows !== []) {
            DB::table('authz_grant')->insert($contentRows);
            DB::table('authz_grant')->insert($moduleRows);
        }

        $this->info("Seeded {$count} Mitgliedschaften → ".($count * 2)." Grants (Content + Modul).");

        return self::SUCCESS;
    }
}
