<?php

namespace Platform\Core\Services\Audience;

use Platform\Core\Contracts\AudienceResolverInterface;
use Platform\Core\Models\Team;

/** Ziel = ein Team; optional inklusive verschachtelter Sub-Teams. */
class TeamAudienceResolver implements AudienceResolverInterface
{
    public function type(): string
    {
        return 'team';
    }

    public function resolve(int $targetId, array $options = [], ?int $teamId = null): array
    {
        $team = Team::find($targetId);
        if (!$team) {
            return [];
        }

        $teamIds = [$team->id];
        if (($options['include_subteams'] ?? false) === true) {
            $teamIds = array_merge($teamIds, $this->descendantTeamIds($team));
        }

        return Team::query()->whereIn('id', $teamIds)->get()
            ->flatMap(fn (Team $t) => $t->users()->pluck('users.id')->all())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int,int> */
    private function descendantTeamIds(Team $team): array
    {
        $ids = [];
        foreach ($team->childTeams as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->descendantTeamIds($child));
        }

        return $ids;
    }

    public function label(int $targetId, ?int $teamId = null): ?string
    {
        return Team::query()->whereKey($targetId)->value('name');
    }
}
