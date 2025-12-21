<?php

namespace Platform\Core\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\TeamCounterEvent;
use Illuminate\Http\Request;

/**
 * Datawarehouse API für Team Counter Events
 */
class TeamCounterEventDatawarehouseController extends ApiController
{
    /**
     * Gibt alle Team Counter Events zurück (paginiert)
     * 
     * Filter:
     * - team_id: Nur Events für bestimmtes Team
     * - team_counter_definition_id: Nur Events für bestimmte Definition
     * - occurred_on: Nur Events für bestimmtes Datum (YYYY-MM-DD)
     * - occurred_from: Events ab diesem Datum (YYYY-MM-DD)
     * - occurred_to: Events bis zu diesem Datum (YYYY-MM-DD)
     */
    public function index(Request $request)
    {
        $query = TeamCounterEvent::query()
            ->with(['definition', 'team', 'user']);

        // Filter: team_id
        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        // Filter: team_counter_definition_id (WICHTIG: Für Counter-spezifische Analysen)
        if ($request->has('team_counter_definition_id')) {
            $definitionId = $request->team_counter_definition_id;
            // Unterstützt auch Array für mehrere Counter
            if (is_array($definitionId)) {
                $query->whereIn('team_counter_definition_id', $definitionId);
            } else {
                $query->where('team_counter_definition_id', $definitionId);
            }
        }

        // Filter: occurred_on (genaues Datum)
        if ($request->has('occurred_on')) {
            $query->whereDate('occurred_on', $request->occurred_on);
        }

        // Filter: occurred_from (ab Datum)
        if ($request->has('occurred_from')) {
            $query->whereDate('occurred_on', '>=', $request->occurred_from);
        }

        // Filter: occurred_to (bis Datum)
        if ($request->has('occurred_to')) {
            $query->whereDate('occurred_on', '<=', $request->occurred_to);
        }

        // Sortierung: Neueste zuerst
        $query->orderBy('occurred_at', 'desc')->orderBy('id', 'desc');

        // Pagination
        $perPage = min((int) ($request->per_page ?? 100), 500);
        $events = $query->paginate($perPage);

        // Transformiere für Datawarehouse
        $data = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'team_counter_definition_id' => $event->team_counter_definition_id,
                'team_id' => $event->team_id,
                'user_id' => $event->user_id,
                'delta' => $event->delta,
                'occurred_on' => $event->occurred_on?->format('Y-m-d'),
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'created_at' => $event->created_at?->toIso8601String(),
                'updated_at' => $event->updated_at?->toIso8601String(),
            ];
        });

        return $this->success([
            'data' => $data,
            'pagination' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    /**
     * Health-Check Endpunkt
     */
    public function health()
    {
        $count = TeamCounterEvent::count();
        $todayCount = TeamCounterEvent::whereDate('occurred_on', today())->count();
        
        return $this->success([
            'status' => 'ok',
            'total_events' => $count,
            'events_today' => $todayCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

