<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\StandardRole;
use Platform\Core\Authz\AuthzResolver;
use Platform\Core\Authz\Capability;
use Platform\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Erklärt eine Content-Autorisierungs-Entscheidung: darf User X die Capability C
 * auf Objekt Y — und WARUM (Ersteller / welcher Grant / Quelle role|relation).
 * Nur Team-Owner/Admin. Read-only. Zum Verifizieren & Debuggen des Graphen.
 */
class AuthzCheckTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.authz.check.GET';
    }

    public function getDescription(): string
    {
        return 'GET /authz/check - Prüft, ob ein User eine Capability (read/write/manage) auf ein konkretes Objekt hat — MIT Begründung (Ersteller / welcher Grant erreicht es / Quelle role|relation). Nur Team-Admins. ERFORDERLICH: user_id, resource_type (volle Klasse), resource_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'user_id'       => ['type' => 'integer', 'description' => 'ERFORDERLICH: zu prüfender User.'],
                'resource_type' => ['type' => 'string',  'description' => 'ERFORDERLICH: volle Model-Klasse, z.B. Platform\\Planner\\Models\\PlannerProject.'],
                'resource_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID des Objekts.'],
                'capability'    => ['type' => 'string',  'description' => 'Optional: read|write|manage. Default read.'],
            ],
            'required' => ['user_id', 'resource_type', 'resource_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $arguments['team_id'] ?? ($context->team?->id ?? auth()->user()?->currentTeam?->id);
            if (! $teamId) {
                return ToolResult::error('NO_TEAM', 'Kein Team im Kontext.');
            }
            $role = auth()->user()?->teams()->where('teams.id', $teamId)->first()?->pivot?->role;
            if (! in_array($role, StandardRole::getAdminRoles(), true)) {
                return ToolResult::error('ACCESS_DENIED', 'Nur Team-Owner/Admins dürfen Zugriffe prüfen.');
            }

            $user = User::find((int) ($arguments['user_id'] ?? 0));
            if (! $user) {
                return ToolResult::error('USER_NOT_FOUND', 'User nicht gefunden.');
            }
            $resourceType = (string) ($arguments['resource_type'] ?? '');
            $resourceId = (int) ($arguments['resource_id'] ?? 0);
            $cap = in_array($arguments['capability'] ?? 'read', ['read', 'write', 'manage'], true)
                ? (string) $arguments['capability'] : 'read';

            $resolver = app(AuthzResolver::class);
            $owner = $resolver->owns($user, $resourceType, $resourceId);
            $structural = $resolver->may($user, $cap, $resourceType, $resourceId);

            $reasons = [];
            if ($owner) {
                $reasons[] = ['type' => 'owner', 'detail' => 'Ersteller des Objekts'];
            }

            if ($structural) {
                $hasOrg = Schema::hasTable('organization_entities');
                $personEntityIds = $hasOrg
                    ? DB::table('organization_entities')->where('linked_user_id', $user->id)->pluck('id')->map(fn ($id) => (int) $id)->all()
                    : [];

                $q = DB::table('authz_grant as g')
                    ->join('authz_scope_closure as c', 'c.ancestor_id', '=', 'g.scope_id')
                    ->join('authz_resource_link as l', 'l.scope_id', '=', 'c.descendant_id')
                    ->where('g.team_id', $teamId)
                    ->where('c.team_id', $teamId)
                    ->where('g.scope_type', 'entity')
                    ->whereIn('g.capability', Capability::satisfying($cap))
                    ->where('l.resource_type', $resourceType)
                    ->where('l.resource_id', $resourceId)
                    ->where(function ($w) use ($user, $personEntityIds) {
                        $w->where(fn ($x) => $x->where('g.subject_type', 'user')->where('g.subject_id', $user->id));
                        if ($personEntityIds !== []) {
                            $w->orWhere(fn ($x) => $x->where('g.subject_type', 'entity')->whereIn('g.subject_id', $personEntityIds));
                        }
                    });

                if ($hasOrg) {
                    $q->leftJoin('organization_entities as e', 'e.id', '=', 'g.scope_id')
                        ->select('g.capability', 'g.source', 'g.scope_id', 'e.name as via_entity');
                } else {
                    $q->select('g.capability', 'g.source', 'g.scope_id');
                }

                foreach ($q->distinct()->limit(10)->get() as $m) {
                    $reasons[] = [
                        'type'       => $m->source === 'org:relation' ? 'relation'
                            : ($m->source === 'org:role_assignment' ? 'role' : 'grant'),
                        'capability' => $m->capability,
                        'via_entity' => $m->via_entity ?? ('#'.$m->scope_id),
                        'source'     => $m->source,
                    ];
                }
            }

            // Woran hängt die Ressource?
            $linkQ = DB::table('authz_resource_link as l')
                ->where('l.resource_type', $resourceType)
                ->where('l.resource_id', $resourceId);
            if (Schema::hasTable('organization_entities')) {
                $hangsOn = $linkQ->leftJoin('organization_entities as e', 'e.id', '=', 'l.scope_id')
                    ->pluck('e.name', 'l.scope_id')->map(fn ($n, $id) => $n ?? ('#'.$id))->values()->all();
            } else {
                $hangsOn = $linkQ->pluck('l.scope_id')->all();
            }

            return ToolResult::success([
                'user_id'             => $user->id,
                'user_name'           => $user->name,
                'resource'            => $resourceType.'#'.$resourceId,
                'capability_required' => $cap,
                'allowed'             => $owner || $structural,
                'via'                 => $owner ? 'owner' : ($structural ? 'graph' : 'denied'),
                'reasons'             => $reasons,
                'hangs_on_entities'   => $hangsOn,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['core', 'authz', 'check', 'debug'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'read',
            'idempotent'    => true,
        ];
    }
}
