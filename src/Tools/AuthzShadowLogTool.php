<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\StandardRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liest den authz_shadow_log — die im Shadow-Mode protokollierten Abweichungen
 * zwischen Graph-Resolver und den bestehenden Policies (Content-Achse).
 *
 * Nur Team-Owner/Admin. Read-only.
 */
class AuthzShadowLogTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.authz.shadow_log.GET';
    }

    public function getDescription(): string
    {
        return 'GET /authz/shadow-log - Aggregierte Autorisierungs-Abweichungen (Graph vs. bestehende Policies) aus dem Shadow-Mode. Zeigt, wo der Graph anders entscheiden würde. Nur Team-Admins. Optional: resource_type, ability, limit.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'resource_type' => ['type' => 'string',  'description' => 'Optional: nur dieser Model-Typ (z.B. Platform\\Planner\\Models\\PlannerProject).'],
                'ability'       => ['type' => 'string',  'description' => 'Optional: nur diese Gate-Ability (view/update/delete/...).'],
                'limit'         => ['type' => 'integer', 'description' => 'Optional: max. Gruppen (Default 50).'],
            ],
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
                return ToolResult::error('ACCESS_DENIED', 'Nur Team-Owner/Admins dürfen den Shadow-Log lesen.');
            }

            if (! Schema::hasTable('authz_shadow_log')) {
                return ToolResult::error('KERNEL_NOT_READY', 'authz_shadow_log fehlt (Kernel nicht migriert).');
            }

            $limit = max(1, min(200, (int) ($arguments['limit'] ?? 50)));

            $base = DB::table('authz_shadow_log')->where('team_id', $teamId);
            if (! empty($arguments['resource_type'])) {
                $base->where('resource_type', (string) $arguments['resource_type']);
            }
            if (! empty($arguments['ability'])) {
                $base->where('ability', (string) $arguments['ability']);
            }

            $total = (clone $base)->count();

            // Richtungs-Summary: der Kern der Aussage.
            $graphStricter = (clone $base)->where('legacy_result', true)->where('graph_result', false)->count();
            $graphLooser   = (clone $base)->where('legacy_result', false)->where('graph_result', true)->count();

            $groups = (clone $base)
                ->selectRaw('ability, capability, resource_type, legacy_result, graph_result, COUNT(*) as cnt')
                ->groupBy('ability', 'capability', 'resource_type', 'legacy_result', 'graph_result')
                ->orderByDesc('cnt')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => [
                    'ability'        => $r->ability,
                    'capability'     => $r->capability,
                    'resource_type'  => $r->resource_type,
                    'legacy_allowed' => (bool) $r->legacy_result,
                    'graph_allowed'  => (bool) $r->graph_result,
                    'direction'      => $r->legacy_result && ! $r->graph_result ? 'graph_strenger'
                        : (! $r->legacy_result && $r->graph_result ? 'graph_lockerer (⚠ gefährlich)' : 'sonstige'),
                    'count'          => (int) $r->cnt,
                ]);

            return ToolResult::success([
                'team_id'          => (int) $teamId,
                'total_divergences' => $total,
                'summary'          => [
                    'graph_strenger'  => $graphStricter,   // Graph verweigert, was Legacy erlaubt (unkritisch, oft Owner/Pivot-Regeln)
                    'graph_lockerer'  => $graphLooser,     // Graph erlaubt, was Legacy verweigert (SICHERHEITSKRITISCH)
                ],
                'top_groups'       => $groups->all(),
                'hint'             => 'graph_lockerer > 0 zuerst prüfen — der Graph würde dort MEHR erlauben. graph_strenger sind meist ressourcenlokale Regeln (Owner/Status), die als resource_link/Entity-Grant nachgebaut werden müssen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['core', 'authz', 'shadow', 'audit'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'read',
            'idempotent'    => true,
        ];
    }
}
