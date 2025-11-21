<?php

namespace Platform\Core\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\User;
use Illuminate\Http\Request;

/**
 * Datawarehouse API Controller für Users
 * 
 * Stellt User-Daten für das Datawarehouse bereit.
 */
class UserDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Users
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filter: Team-ID mit optionaler Kind-Team-Inklusion
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            $includeChildrenValue = $request->input('include_child_teams');
            $includeChildren = $request->has('include_child_teams') 
                ? ($includeChildrenValue === '1' || $includeChildrenValue === 'true' || $includeChildrenValue === true || $includeChildrenValue === 1)
                : true; // Default: true (wenn nicht gesetzt)
            
            if ($includeChildren) {
                $team = \Platform\Core\Models\Team::find($teamId);
                if ($team) {
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    $query->whereHas('teams', function($q) use ($teamIds) {
                        $q->whereIn('teams.id', $teamIds);
                    });
                } else {
                    $query->whereRaw('1 = 0'); // Team nicht gefunden
                }
            } else {
                $query->whereHas('teams', function($q) use ($teamId) {
                    $q->where('teams.id', $teamId);
                });
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        
        $allowedSortColumns = ['id', 'name', 'email', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 100), 1000);
        $users = $query->paginate($perPage);

        // Formatting
        $formatted = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'lastname' => $user->lastname ?? null,
                'email' => $user->email,
                'username' => $user->username ?? null,
                'avatar' => $user->avatar ?? null,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $users->setCollection($formatted),
            'Users erfolgreich geladen'
        );
    }
}

