<?php

namespace Platform\Core\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;

/**
 * Datawarehouse API Controller für Teams
 * 
 * Stellt Team-Daten für das Datawarehouse bereit.
 */
class TeamDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Teams
     */
    public function index(Request $request)
    {
        $query = Team::query();

        // Filter: Team-ID mit optionaler Kind-Team-Inklusion
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            $includeChildrenValue = $request->input('include_child_teams');
            $includeChildren = $request->has('include_child_teams') 
                ? ($includeChildrenValue === '1' || $includeChildrenValue === 'true' || $includeChildrenValue === true || $includeChildrenValue === 1)
                : true; // Default: true (wenn nicht gesetzt)
            
            if ($includeChildren) {
                $team = Team::find($teamId);
                if ($team) {
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    $query->whereIn('id', $teamIds);
                } else {
                    $query->whereRaw('1 = 0'); // Team nicht gefunden
                }
            } else {
                $query->where('id', $teamId);
            }
        }

        // Filter: Parent-Team-ID
        if ($request->has('parent_team_id')) {
            $query->where('parent_team_id', $request->parent_team_id);
        }

        if ($request->boolean('is_root')) {
            $query->whereNull('parent_team_id');
        }

        if ($request->boolean('personal_team')) {
            $query->where('personal_team', true);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        
        $allowedSortColumns = ['id', 'name', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 100), 1000);
        $teams = $query->paginate($perPage);

        // Formatting
        $formatted = $teams->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'user_id' => $team->user_id,
                'parent_team_id' => $team->parent_team_id,
                'parent_team_name' => $team->parentTeam?->name ?? null, // Denormalisiert
                'personal_team' => $team->personal_team,
                'is_root_team' => $team->isRootTeam(),
                'created_at' => $team->created_at->toIso8601String(),
                'updated_at' => $team->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $teams->setCollection($formatted),
            'Teams erfolgreich geladen'
        );
    }
}

