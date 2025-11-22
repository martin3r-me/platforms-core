<?php

namespace Platform\Core\Http\Controllers\Api;

use Platform\Core\Http\Controllers\ApiController;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Datawarehouse API Controller für Check-ins
 * 
 * Stellt flexible Filter und Aggregationen für das Datawarehouse bereit.
 * Unterstützt Team-Hierarchien (inkl. Kind-Teams) über User-Team-Zuordnung.
 */
class CheckinDatawarehouseController extends ApiController
{
    /**
     * Flexibler Datawarehouse-Endpunkt für Check-ins
     * 
     * Unterstützt komplexe Filter und Aggregationen
     */
    public function index(Request $request)
    {
        $query = Checkin::query();

        // ===== FILTER =====
        $this->applyFilters($query, $request);

        // ===== SORTING =====
        $sortBy = $request->get('sort_by', 'date');
        $sortDir = $request->get('sort_dir', 'desc');
        
        // Validierung der Sort-Spalte (Security)
        $allowedSortColumns = ['id', 'date', 'created_at', 'updated_at', 'mood_score', 'energy_score'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('date', 'desc');
        }

        // ===== PAGINATION =====
        $perPage = min($request->get('per_page', 100), 1000); // Max 1000 pro Seite
        // User-Relation laden für User-Name
        $query->with('user:id,name,email');
        $checkins = $query->paginate($perPage);

        // ===== FORMATTING =====
        // Datawarehouse-freundliches Format
        $formatted = $checkins->map(function ($checkin) {
            return [
                'id' => $checkin->id,
                'user_id' => $checkin->user_id,
                'user_name' => $checkin->user?->name, // User-Name mitliefern (denormalisiert)
                'user_email' => $checkin->user?->email, // User-Email mitliefern
                'date' => $checkin->date->format('Y-m-d'),
                'daily_goal' => $checkin->daily_goal,
                'goal_category' => $checkin->goal_category?->value, // Enum-Wert
                'goal_category_label' => $checkin->goal_category?->label(), // Enum-Label
                'mood_score' => $checkin->mood_score,
                'energy_score' => $checkin->energy_score,
                // Legacy-Felder (für Kompatibilität)
                'mood' => $checkin->mood,
                'happiness' => $checkin->happiness,
                // Reflexionsfelder
                'hydrated' => $checkin->hydrated,
                'exercised' => $checkin->exercised,
                'slept_well' => $checkin->slept_well,
                'focused_work' => $checkin->focused_work,
                'social_time' => $checkin->social_time,
                'needs_support' => $checkin->needs_support,
                'notes' => $checkin->notes,
                'created_at' => $checkin->created_at->toIso8601String(),
                'updated_at' => $checkin->updated_at->toIso8601String(),
            ];
        });

        return $this->paginated(
            $checkins->setCollection($formatted),
            'Check-ins erfolgreich geladen'
        );
    }

    /**
     * Wendet alle Filter auf die Query an
     */
    protected function applyFilters($query, Request $request): void
    {
        // User-Filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Team-Filter mit Kind-Teams Option (über User-Team-Zuordnung)
        if ($request->has('team_id')) {
            $teamId = $request->team_id;
            // Standardmäßig Kind-Teams inkludieren (wenn nicht explizit false)
            $includeChildrenValue = $request->input('include_child_teams');
            $includeChildren = $request->has('include_child_teams') 
                ? ($includeChildrenValue === '1' || $includeChildrenValue === 'true' || $includeChildrenValue === true || $includeChildrenValue === 1)
                : true; // Default: true (wenn nicht gesetzt)
            
            if ($includeChildren) {
                // Team mit Kind-Teams laden
                $team = Team::find($teamId);
                
                if ($team) {
                    // Alle Team-IDs inkl. Kind-Teams sammeln
                    $teamIds = $team->getAllTeamIdsIncludingChildren();
                    // User-IDs finden, die zu diesen Teams gehören
                    $userIds = DB::table('team_user')
                        ->whereIn('team_id', $teamIds)
                        ->pluck('user_id')
                        ->unique()
                        ->toArray();
                    
                    if (!empty($userIds)) {
                        $query->whereIn('user_id', $userIds);
                    } else {
                        // Keine User in diesen Teams - leeres Ergebnis
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    // Team nicht gefunden - leeres Ergebnis
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Nur das genannte Team (wenn explizit deaktiviert)
                $userIds = DB::table('team_user')
                    ->where('team_id', $teamId)
                    ->pluck('user_id')
                    ->unique()
                    ->toArray();
                
                if (!empty($userIds)) {
                    $query->whereIn('user_id', $userIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        // Datums-Filter
        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        // Datums-Range
        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Heute
        if ($request->boolean('today')) {
            $query->whereDate('date', Carbon::today());
        }

        // Diese Woche
        if ($request->boolean('this_week')) {
            $query->whereBetween('date', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ]);
        }

        // Dieser Monat
        if ($request->boolean('this_month')) {
            $query->whereMonth('date', Carbon::now()->month)
                  ->whereYear('date', Carbon::now()->year);
        }

        // Erstellt heute
        if ($request->boolean('created_today')) {
            $query->whereDate('created_at', Carbon::today());
        }

        // Erstellt in Range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // Updated Since Filter (für inkrementelle Imports)
        if ($request->has('updated_since')) {
            $updatedSince = Carbon::parse($request->updated_since);
            $query->where('updated_at', '>=', $updatedSince);
        }

        // Mood Score Filter
        if ($request->has('mood_score')) {
            $query->where('mood_score', $request->mood_score);
        }
        if ($request->has('mood_score_min')) {
            $query->where('mood_score', '>=', $request->mood_score_min);
        }
        if ($request->has('mood_score_max')) {
            $query->where('mood_score', '<=', $request->mood_score_max);
        }

        // Energy Score Filter
        if ($request->has('energy_score')) {
            $query->where('energy_score', $request->energy_score);
        }
        if ($request->has('energy_score_min')) {
            $query->where('energy_score', '>=', $request->energy_score_min);
        }
        if ($request->has('energy_score_max')) {
            $query->where('energy_score', '<=', $request->energy_score_max);
        }

        // Goal Category Filter
        if ($request->has('goal_category')) {
            $query->where('goal_category', $request->goal_category);
        }

        // Reflexionsfelder Filter
        if ($request->has('hydrated')) {
            $query->where('hydrated', $request->boolean('hydrated'));
        }
        if ($request->has('exercised')) {
            $query->where('exercised', $request->boolean('exercised'));
        }
        if ($request->has('slept_well')) {
            $query->where('slept_well', $request->boolean('slept_well'));
        }
        if ($request->has('focused_work')) {
            $query->where('focused_work', $request->boolean('focused_work'));
        }
        if ($request->has('social_time')) {
            $query->where('social_time', $request->boolean('social_time'));
        }
        if ($request->has('needs_support')) {
            $query->where('needs_support', $request->boolean('needs_support'));
        }

        // Hat Tagesziel
        if ($request->has('has_daily_goal')) {
            if ($request->has_daily_goal === 'true' || $request->has_daily_goal === '1') {
                $query->whereNotNull('daily_goal')
                      ->where('daily_goal', '!=', '');
            } else {
                $query->where(function($q) {
                    $q->whereNull('daily_goal')
                      ->orWhere('daily_goal', '');
                });
            }
        }

        // Hat Notizen
        if ($request->has('has_notes')) {
            if ($request->has_notes === 'true' || $request->has_notes === '1') {
                $query->whereNotNull('notes')
                      ->where('notes', '!=', '');
            } else {
                $query->where(function($q) {
                    $q->whereNull('notes')
                      ->orWhere('notes', '');
                });
            }
        }
    }
}

