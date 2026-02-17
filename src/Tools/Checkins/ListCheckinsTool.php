<?php

namespace Platform\Core\Tools\Checkins;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\GoalCategory;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\Checkin;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;

/**
 * core.checkins.GET
 *
 * Listet Checkins auf. Reguläre User sehen nur eigene Checkins.
 * Parent-Team-Owner/Admin erhalten konsolidierte Ansicht aller Checkins im Team.
 *
 * Unterstützt Filterung nach Datum, Zeitraum, Scores, Kategorien und Reflexionsfeldern.
 */
class ListCheckinsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'core.checkins.GET';
    }

    public function getDescription(): string
    {
        return 'GET /core/checkins - Listet Checkins auf. Reguläre User sehen nur eigene Checkins. '
            . 'Team-Owner/Admin können mit team_view=true alle Checkins im Team (inkl. Kind-Teams) sehen. '
            . 'Unterstützt Filterung nach Datum (date, date_from, date_to, today, this_week, this_month, last_days), '
            . 'Scores (mood_score, energy_score), Kategorie (goal_category) und Reflexionsfeldern. '
            . 'Standard-Filter, Suche, Sortierung und Pagination verfügbar.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    // Datum-Shortcut-Filter
                    'date' => [
                        'type' => 'string',
                        'description' => 'Exaktes Datum (YYYY-MM-DD). Beispiel: "2025-06-15".',
                    ],
                    'date_from' => [
                        'type' => 'string',
                        'description' => 'Startdatum für Zeitraum (YYYY-MM-DD, inklusive). Beispiel: "2025-06-01".',
                    ],
                    'date_to' => [
                        'type' => 'string',
                        'description' => 'Enddatum für Zeitraum (YYYY-MM-DD, inklusive). Beispiel: "2025-06-30".',
                    ],
                    'today' => [
                        'type' => 'boolean',
                        'description' => 'Nur Checkins von heute anzeigen.',
                    ],
                    'this_week' => [
                        'type' => 'boolean',
                        'description' => 'Nur Checkins dieser Woche anzeigen.',
                    ],
                    'this_month' => [
                        'type' => 'boolean',
                        'description' => 'Nur Checkins dieses Monats anzeigen.',
                    ],
                    'last_days' => [
                        'type' => 'integer',
                        'description' => 'Checkins der letzten X Tage anzeigen. Beispiel: 7 für die letzte Woche.',
                    ],
                    // Team-View
                    'team_view' => [
                        'type' => 'boolean',
                        'description' => 'Team-Ansicht: Zeigt alle Checkins im Team (inkl. Kind-Teams). Nur für Team-Owner/Admin verfügbar. Standard: false.',
                    ],
                    // Einzel-Abfrage
                    'checkin_id' => [
                        'type' => 'integer',
                        'description' => 'Einzelnen Checkin per ID laden (inkl. Todos). Überschreibt alle anderen Filter.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Einzel-Abfrage per ID
            if (!empty($arguments['checkin_id'])) {
                return $this->getSingleCheckin((int) $arguments['checkin_id'], $context);
            }

            $query = Checkin::query();

            // Berechtigungslogik: Team-View vs. eigene Checkins
            $teamView = !empty($arguments['team_view']);

            if ($teamView) {
                $result = $this->applyTeamScope($query, $context);
                if ($result !== null) {
                    return $result; // Fehler bei fehlenden Berechtigungen
                }
            } else {
                // Regulärer User: nur eigene Checkins
                $query->where('user_id', $user->id);
            }

            // Datum-Shortcut-Filter
            $this->applyDateFilters($query, $arguments);

            // Standard-Filter (auf erlaubte Felder beschränkt)
            $this->applyStandardFilters($query, $arguments, [
                'date', 'user_id', 'mood_score', 'energy_score', 'goal_category',
                'hydrated', 'exercised', 'slept_well', 'focused_work', 'social_time',
                'needs_support', 'created_at', 'updated_at',
            ]);

            // Suche
            $this->applyStandardSearch($query, $arguments, ['daily_goal', 'notes']);

            // Sortierung
            $this->applyStandardSort($query, $arguments, [
                'date', 'created_at', 'updated_at', 'mood_score', 'energy_score', 'id',
            ], 'date', 'desc');

            // Pagination mit Result
            $paginationResult = $this->applyStandardPaginationResult($query, $arguments);
            $checkins = $paginationResult['data'];

            // Formatierung
            $formatted = $checkins->map(function (Checkin $checkin) use ($teamView) {
                $data = $this->formatCheckin($checkin);
                if ($teamView) {
                    // Bei Team-View: User-Info hinzufügen
                    $checkin->loadMissing('user:id,name,email');
                    $data['user_name'] = $checkin->user?->name;
                    $data['user_email'] = $checkin->user?->email;
                }
                return $data;
            })->values()->toArray();

            return ToolResult::success([
                'checkins' => $formatted,
                'count' => count($formatted),
                'pagination' => $paginationResult['pagination'],
                'view_mode' => $teamView ? 'team' : 'personal',
                'enums' => [
                    'goal_categories' => GoalCategory::options(),
                    'mood_scores' => Checkin::getMoodScoreOptions(),
                    'energy_scores' => Checkin::getEnergyScoreOptions(),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Checkins: ' . $e->getMessage());
        }
    }

    /**
     * Einzelnen Checkin per ID laden (inkl. Todos)
     */
    private function getSingleCheckin(int $checkinId, ToolContext $context): ToolResult
    {
        $user = $context->user;

        $checkin = Checkin::with(['todos', 'user:id,name,email'])->find($checkinId);

        if (!$checkin) {
            return ToolResult::error('NOT_FOUND', "Checkin mit ID {$checkinId} nicht gefunden.");
        }

        // Berechtigung: Eigener Checkin oder Team-Owner/Admin
        if ((int) $checkin->user_id !== (int) $user->id) {
            if (!$this->userCanViewTeamCheckins($user, $checkin->user_id, $context)) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Checkin.');
            }
        }

        $data = $this->formatCheckin($checkin);
        $data['user_name'] = $checkin->user?->name;
        $data['user_email'] = $checkin->user?->email;
        $data['todos'] = $checkin->todos->map(fn ($todo) => [
            'id' => $todo->id,
            'title' => $todo->title,
            'done' => (bool) $todo->done,
            'created_at' => $todo->created_at?->toIso8601String(),
        ])->values()->toArray();

        return ToolResult::success([
            'checkin' => $data,
        ]);
    }

    /**
     * Team-Scope anwenden: Owner/Admin sieht alle Checkins im Team (inkl. Kind-Teams)
     */
    private function applyTeamScope($query, ToolContext $context): ?ToolResult
    {
        $user = $context->user;
        $team = $context->team ?? (method_exists($user, 'currentTeam') ? $user->currentTeam : null);

        if (!$team) {
            return ToolResult::error('NO_TEAM', 'Kein Team im Kontext. Bitte wechsle zuerst in ein Team (core.team.switch).');
        }

        // Prüfe ob User Owner oder Admin im Team ist
        $membership = $team->users()->where('user_id', $user->id)->first();
        $role = $membership?->pivot?->role ?? null;

        if (!in_array($role, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true)) {
            return ToolResult::error('ACCESS_DENIED', 'Team-Ansicht ist nur für Team-Owner oder Admin verfügbar.');
        }

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
            $query->whereRaw('1 = 0');
        }

        return null; // Kein Fehler
    }

    /**
     * Prüft ob der User die Checkins eines anderen Users sehen darf (Team-Owner/Admin)
     */
    private function userCanViewTeamCheckins($user, int $targetUserId, ToolContext $context): bool
    {
        $team = $context->team ?? (method_exists($user, 'currentTeam') ? $user->currentTeam : null);
        if (!$team) {
            return false;
        }

        $membership = $team->users()->where('user_id', $user->id)->first();
        $role = $membership?->pivot?->role ?? null;

        if (!in_array($role, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true)) {
            return false;
        }

        // Prüfe ob Target-User im Team (inkl. Kind-Teams) ist
        $teamIds = $team->getAllTeamIdsIncludingChildren();
        return DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->where('user_id', $targetUserId)
            ->exists();
    }

    /**
     * Datum-Shortcut-Filter anwenden
     */
    private function applyDateFilters($query, array $arguments): void
    {
        if (!empty($arguments['date'])) {
            $query->whereDate('date', $arguments['date']);
        }

        if (!empty($arguments['date_from'])) {
            $query->whereDate('date', '>=', $arguments['date_from']);
        }

        if (!empty($arguments['date_to'])) {
            $query->whereDate('date', '<=', $arguments['date_to']);
        }

        if (!empty($arguments['today'])) {
            $query->whereDate('date', Carbon::today());
        }

        if (!empty($arguments['this_week'])) {
            $query->whereBetween('date', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ]);
        }

        if (!empty($arguments['this_month'])) {
            $query->whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year);
        }

        if (!empty($arguments['last_days'])) {
            $days = max(1, (int) $arguments['last_days']);
            $query->whereDate('date', '>=', Carbon::today()->subDays($days));
        }
    }

    /**
     * Checkin-Daten formatieren
     */
    private function formatCheckin(Checkin $checkin): array
    {
        return [
            'id' => $checkin->id,
            'user_id' => $checkin->user_id,
            'date' => $checkin->date->format('Y-m-d'),
            'daily_goal' => $checkin->daily_goal,
            'goal_category' => $checkin->goal_category?->value,
            'goal_category_label' => $checkin->goal_category?->label(),
            'mood_score' => $checkin->mood_score,
            'mood_score_label' => $checkin->mood_score !== null ? (Checkin::getMoodScoreOptions()[$checkin->mood_score] ?? null) : null,
            'energy_score' => $checkin->energy_score,
            'energy_score_label' => $checkin->energy_score !== null ? (Checkin::getEnergyScoreOptions()[$checkin->energy_score] ?? null) : null,
            'hydrated' => (bool) $checkin->hydrated,
            'exercised' => (bool) $checkin->exercised,
            'slept_well' => (bool) $checkin->slept_well,
            'focused_work' => (bool) $checkin->focused_work,
            'social_time' => (bool) $checkin->social_time,
            'needs_support' => (bool) $checkin->needs_support,
            'notes' => $checkin->notes,
            'created_at' => $checkin->created_at?->toIso8601String(),
            'updated_at' => $checkin->updated_at?->toIso8601String(),
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['core', 'checkins', 'wellbeing', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'examples' => [
                'Zeige meine Checkins von heute',
                'Zeige meine Checkins der letzten 7 Tage',
                'Zeige alle Team-Checkins dieser Woche (team_view=true)',
                'Lade Checkin mit ID 42 (checkin_id=42)',
            ],
            'related_tools' => ['core.checkins.POST', 'core.checkins.PUT'],
        ];
    }
}
