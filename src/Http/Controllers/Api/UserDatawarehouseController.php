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
            
            \Log::debug('UserDatawarehouseController: Filtering users', [
                'team_id' => $teamId,
                'include_child_teams' => $includeChildrenValue,
                'includeChildren' => $includeChildren,
            ]);
            
            if ($includeChildren) {
                $team = \Platform\Core\Models\Team::find($teamId);
                if ($team) {
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    \Log::debug('UserDatawarehouseController: Team IDs including children', [
                        'parent_team_id' => $teamId,
                        'all_team_ids' => $teamIds,
                        'count' => count($teamIds),
                    ]);
                    
                    // Verwende distinct() um Duplikate zu vermeiden (falls User in mehreren Teams sind)
                    $query->whereHas('teams', function($q) use ($teamIds) {
                        $q->whereIn('teams.id', $teamIds);
                    })->distinct();
                } else {
                    \Log::warning('UserDatawarehouseController: Team not found', ['team_id' => $teamId]);
                    $query->whereRaw('1 = 0'); // Team nicht gefunden
                }
            } else {
                \Log::debug('UserDatawarehouseController: Filtering users for single team', ['team_id' => $teamId]);
                $query->whereHas('teams', function($q) use ($teamId) {
                    $q->where('teams.id', $teamId);
                })->distinct();
            }
        } else {
            \Log::debug('UserDatawarehouseController: No team_id filter - returning all users');
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

        // User-IDs für Session-Abfrage sammeln
        $userIds = $users->pluck('id')->toArray();
        
        // Last Activity aus Sessions-Tabelle holen
        $lastActivities = [];
        if (!empty($userIds)) {
            $sessions = \Illuminate\Support\Facades\DB::table('sessions')
                ->whereIn('user_id', $userIds)
                ->whereNotNull('user_id')
                ->select('user_id', \Illuminate\Support\Facades\DB::raw('MAX(last_activity) as last_activity'))
                ->groupBy('user_id')
                ->get();
            
            foreach ($sessions as $session) {
                $lastActivities[$session->user_id] = \Carbon\Carbon::createFromTimestamp($session->last_activity);
            }
        }

        // Formatting
        $formatted = $users->map(function ($user) use ($lastActivities) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'lastname' => $user->lastname ?? null,
                'email' => $user->email,
                'username' => $user->username ?? null,
                'avatar' => $user->avatar ?? null,
                'last_active' => isset($lastActivities[$user->id]) 
                    ? $lastActivities[$user->id]->toIso8601String() 
                    : null,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $users->setCollection($formatted),
            'Users erfolgreich geladen'
        );
    }

    /**
     * Health Check Endpoint
     * Gibt einen Beispiel-Datensatz zurück für Tests
     */
    public function health(Request $request)
    {
        try {
            $example = User::orderBy('created_at', 'desc')
                ->first();

            if (!$example) {
                return $this->success([
                    'status' => 'ok',
                    'message' => 'API ist erreichbar, aber keine Users vorhanden',
                    'example' => null,
                    'timestamp' => now()->toIso8601String(),
                ], 'Health Check');
            }

            // Last Activity aus Sessions-Tabelle holen
            $lastActivity = null;
            $session = \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $example->id)
                ->whereNotNull('user_id')
                ->orderBy('last_activity', 'desc')
                ->first();
            
            if ($session) {
                $lastActivity = \Carbon\Carbon::createFromTimestamp($session->last_activity)->toIso8601String();
            }

            $exampleData = [
                'id' => $example->id,
                'name' => $example->name,
                'lastname' => $example->lastname ?? null,
                'email' => $example->email,
                'username' => $example->username ?? null,
                'avatar' => $example->avatar ?? null,
                'last_active' => $lastActivity,
                'created_at' => $example->created_at->toIso8601String(),
                'updated_at' => $example->updated_at->toIso8601String(),
            ];

            return $this->success([
                'status' => 'ok',
                'message' => 'API ist erreichbar',
                'example' => $exampleData,
                'timestamp' => now()->toIso8601String(),
            ], 'Health Check');

        } catch (\Exception $e) {
            return $this->error('Health Check fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }
}

