<?php

namespace Platform\Core\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\TeamCounterDefinition;
use Platform\Core\Models\TeamCounterEvent;
use Illuminate\Http\Request;

/**
 * Datawarehouse API für Team Counter Definitions
 */
class TeamCounterDatawarehouseController extends ApiController
{
    /**
     * Gibt alle Team Counter Definitions zurück (paginiert)
     * 
     * Filter:
     * - team_id: Nur Definitions für bestimmtes Team (scope_team_id)
     * - is_active: Nur aktive Definitions (default: true)
     */
    public function index(Request $request)
    {
        $query = TeamCounterDefinition::query()
            ->with(['scopeTeam', 'createdBy']);

        // Filter: team_id (scope_team_id)
        if ($request->has('team_id')) {
            $query->where('scope_team_id', $request->team_id);
        }

        // Filter: is_active (default: true)
        $isActive = $request->has('is_active') 
            ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
            : true;
        $query->where('is_active', $isActive);

        // Sortierung
        $query->orderBy('sort_order')->orderBy('label');

        // Pagination
        $perPage = min((int) ($request->per_page ?? 100), 500);
        $definitions = $query->paginate($perPage);

        // Transformiere für Datawarehouse
        $data = $definitions->map(function ($definition) {
            return [
                'id' => $definition->id,
                'scope_team_id' => $definition->scope_team_id,
                'slug' => $definition->slug,
                'label' => $definition->label,
                'description' => $definition->description,
                'is_active' => $definition->is_active,
                'sort_order' => $definition->sort_order,
                'created_by_user_id' => $definition->created_by_user_id,
                'created_at' => $definition->created_at?->toIso8601String(),
                'updated_at' => $definition->updated_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'data' => $data,
            'pagination' => [
                'current_page' => $definitions->currentPage(),
                'per_page' => $definitions->perPage(),
                'total' => $definitions->total(),
                'last_page' => $definitions->lastPage(),
            ],
        ]);
    }

    /**
     * Health-Check Endpunkt
     */
    public function health()
    {
        $count = TeamCounterDefinition::where('is_active', true)->count();
        
        return $this->success([
            'status' => 'ok',
            'active_definitions' => $count,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

