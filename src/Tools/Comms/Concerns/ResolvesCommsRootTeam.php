<?php

namespace Platform\Core\Tools\Comms\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Team;

trait ResolvesCommsRootTeam
{
    /**
     * Resolve the root team for comms data (channels/threads are stored on root team level).
     *
     * @return array{team:Team|null,team_id:int|null,error:ToolResult|null}
     */
    protected function resolveRootTeam(array $arguments, ToolContext $context): array
    {
        $teamId = (int) ($arguments['team_id'] ?? $context->team?->id ?? $context->user->currentTeam?->id ?? 0);
        if ($teamId <= 0) {
            return [
                'team' => null,
                'team_id' => null,
                'error' => ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.'),
            ];
        }

        // Ensure the user is member of this team
        if (method_exists($context->user, 'teams') && !$context->user->teams()->whereKey($teamId)->exists()) {
            return [
                'team' => null,
                'team_id' => null,
                'error' => ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.'),
            ];
        }

        /** @var Team|null $team */
        $team = Team::query()->whereKey($teamId)->first();
        if (!$team) {
            return [
                'team' => null,
                'team_id' => null,
                'error' => ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.'),
            ];
        }

        $rootTeam = $team->getRootTeam();

        return [
            'team' => $rootTeam,
            'team_id' => (int) $rootTeam->id,
            'error' => null,
        ];
    }

    protected function isRootTeamAdmin(ToolContext $context, Team $rootTeam): bool
    {
        return $rootTeam->users()
            ->where('user_id', $context->user->id)
            ->wherePivotIn('role', [TeamRole::OWNER->value, TeamRole::ADMIN->value])
            ->exists();
    }
}

