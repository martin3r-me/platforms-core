<?php

namespace Platform\Core\Tools\Checkins;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\GoalCategory;
use Platform\Core\Models\Checkin;
use Platform\Core\Models\CheckinTodo;

/**
 * core.checkins.PUT
 *
 * Aktualisiert einen bestehenden Checkin. Nur eigene Checkins können bearbeitet werden.
 * Unterstützt partielle Updates – nur übergebene Felder werden geändert.
 * Todos können hinzugefügt, aktualisiert (toggle) und gelöscht werden.
 */
class UpdateCheckinTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.checkins.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /core/checkins/{id} - Aktualisiert einen bestehenden Checkin. Nur eigene Checkins können bearbeitet werden. '
            . 'Partielle Updates: nur übergebene Felder werden geändert. '
            . 'Felder: daily_goal, goal_category, mood_score (0-4), energy_score (0-4), '
            . 'Reflexionsfelder (hydrated, exercised, slept_well, focused_work, social_time, needs_support), notes. '
            . 'Todo-Verwaltung: add_todos (neue Todos), toggle_todo_id (Todo umschalten), delete_todo_id (Todo löschen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'checkin_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Checkins (ERFORDERLICH).',
                ],
                'daily_goal' => [
                    'type' => 'string',
                    'description' => 'Tagesziel (max 1000 Zeichen). Leer-String zum Entfernen.',
                ],
                'goal_category' => [
                    'type' => 'string',
                    'enum' => ['focus', 'team', 'health', 'learning', 'project', 'personal', 'growth', ''],
                    'description' => 'Kategorie des Tagesziels. Leer-String zum Entfernen. Erlaubte Werte: focus, team, health, learning, project, personal, growth.',
                ],
                'mood_score' => [
                    'type' => 'integer',
                    'description' => 'Stimmungswert 0-4. 0=Sehr schlecht, 1=Schlecht, 2=Neutral, 3=Gut, 4=Ausgezeichnet. Null zum Entfernen.',
                ],
                'energy_score' => [
                    'type' => 'integer',
                    'description' => 'Energielevel 0-4. 0=Sehr niedrig, 1=Niedrig, 2=Mittel, 3=Hoch, 4=Sehr hoch. Null zum Entfernen.',
                ],
                'hydrated' => [
                    'type' => 'boolean',
                    'description' => 'Ausreichend getrunken?',
                ],
                'exercised' => [
                    'type' => 'boolean',
                    'description' => 'Sport gemacht?',
                ],
                'slept_well' => [
                    'type' => 'boolean',
                    'description' => 'Gut geschlafen?',
                ],
                'focused_work' => [
                    'type' => 'boolean',
                    'description' => 'Fokussiert gearbeitet?',
                ],
                'social_time' => [
                    'type' => 'boolean',
                    'description' => 'Soziale Zeit gehabt?',
                ],
                'needs_support' => [
                    'type' => 'boolean',
                    'description' => 'Braucht Unterstützung?',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Freitext-Notizen (max 2000 Zeichen). Leer-String zum Entfernen.',
                ],
                // Todo-Verwaltung
                'add_todos' => [
                    'type' => 'array',
                    'description' => 'Neue Todos hinzufügen. Array von Objekten mit "title" (string) und optional "done" (boolean).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => [
                                'type' => 'string',
                                'description' => 'Titel des Todos.',
                            ],
                            'done' => [
                                'type' => 'boolean',
                                'description' => 'Erledigt? Standard: false.',
                            ],
                        ],
                        'required' => ['title'],
                    ],
                ],
                'toggle_todo_id' => [
                    'type' => 'integer',
                    'description' => 'ID eines Todos, dessen done-Status umgeschaltet werden soll.',
                ],
                'delete_todo_id' => [
                    'type' => 'integer',
                    'description' => 'ID eines Todos, das gelöscht werden soll.',
                ],
            ],
            'required' => ['checkin_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $checkinId = (int) ($arguments['checkin_id'] ?? 0);
            if ($checkinId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'checkin_id ist erforderlich.');
            }

            // Checkin laden und Berechtigung prüfen (nur eigene Checkins)
            $checkin = Checkin::with('todos')->find($checkinId);
            if (!$checkin) {
                return ToolResult::error('NOT_FOUND', "Checkin mit ID {$checkinId} nicht gefunden.");
            }

            if ((int) $checkin->user_id !== (int) $user->id) {
                return ToolResult::error('ACCESS_DENIED', 'Du kannst nur eigene Checkins bearbeiten.');
            }

            // Validierung
            $validationError = $this->validate($arguments);
            if ($validationError !== null) {
                return $validationError;
            }

            // Partielle Updates: nur übergebene Felder
            $updatedFields = [];
            $updateData = [];

            // String-Felder
            foreach (['daily_goal', 'notes'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $value = $arguments[$field];
                    $updateData[$field] = ($value === '' || $value === null) ? null : $value;
                    $updatedFields[] = $field;
                }
            }

            // goal_category
            if (array_key_exists('goal_category', $arguments)) {
                $value = $arguments['goal_category'];
                $updateData['goal_category'] = ($value === '' || $value === null) ? null : $value;
                $updatedFields[] = 'goal_category';
            }

            // Integer-Felder
            foreach (['mood_score', 'energy_score'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $value = $arguments[$field];
                    $updateData[$field] = ($value !== null && $value !== '') ? (int) $value : null;
                    $updatedFields[] = $field;
                }
            }

            // Boolean-Felder
            $booleanFields = ['hydrated', 'exercised', 'slept_well', 'focused_work', 'social_time', 'needs_support'];
            foreach ($booleanFields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $updateData[$field] = (bool) $arguments[$field];
                    $updatedFields[] = $field;
                }
            }

            // Checkin aktualisieren
            if (!empty($updateData)) {
                $checkin->update($updateData);
            }

            // Todo-Verwaltung
            $todoActions = [];

            // Neue Todos hinzufügen
            if (!empty($arguments['add_todos']) && is_array($arguments['add_todos'])) {
                foreach ($arguments['add_todos'] as $todoData) {
                    $title = trim((string) ($todoData['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $todo = CheckinTodo::create([
                        'checkin_id' => $checkin->id,
                        'title' => $title,
                        'done' => (bool) ($todoData['done'] ?? false),
                    ]);
                    $todoActions[] = ['action' => 'added', 'id' => $todo->id, 'title' => $todo->title];
                }
            }

            // Todo toggle
            if (!empty($arguments['toggle_todo_id'])) {
                $todo = CheckinTodo::where('id', (int) $arguments['toggle_todo_id'])
                    ->where('checkin_id', $checkin->id)
                    ->first();
                if ($todo) {
                    $newDone = !$todo->done;
                    $todo->update(['done' => $newDone]);
                    $todoActions[] = ['action' => 'toggled', 'id' => $todo->id, 'done' => $newDone];
                } else {
                    $todoActions[] = ['action' => 'toggle_failed', 'id' => (int) $arguments['toggle_todo_id'], 'reason' => 'Todo nicht gefunden'];
                }
            }

            // Todo löschen
            if (!empty($arguments['delete_todo_id'])) {
                $todo = CheckinTodo::where('id', (int) $arguments['delete_todo_id'])
                    ->where('checkin_id', $checkin->id)
                    ->first();
                if ($todo) {
                    $deletedTitle = $todo->title;
                    $todo->delete();
                    $todoActions[] = ['action' => 'deleted', 'id' => (int) $arguments['delete_todo_id'], 'title' => $deletedTitle];
                } else {
                    $todoActions[] = ['action' => 'delete_failed', 'id' => (int) $arguments['delete_todo_id'], 'reason' => 'Todo nicht gefunden'];
                }
            }

            // Frischen Checkin laden
            $checkin = $checkin->fresh(['todos']);

            return ToolResult::success([
                'checkin' => [
                    'id' => $checkin->id,
                    'user_id' => $checkin->user_id,
                    'date' => $checkin->date->format('Y-m-d'),
                    'daily_goal' => $checkin->daily_goal,
                    'goal_category' => $checkin->goal_category?->value,
                    'goal_category_label' => $checkin->goal_category?->label(),
                    'mood_score' => $checkin->mood_score,
                    'energy_score' => $checkin->energy_score,
                    'hydrated' => (bool) $checkin->hydrated,
                    'exercised' => (bool) $checkin->exercised,
                    'slept_well' => (bool) $checkin->slept_well,
                    'focused_work' => (bool) $checkin->focused_work,
                    'social_time' => (bool) $checkin->social_time,
                    'needs_support' => (bool) $checkin->needs_support,
                    'notes' => $checkin->notes,
                    'created_at' => $checkin->created_at?->toIso8601String(),
                    'updated_at' => $checkin->updated_at?->toIso8601String(),
                ],
                'todos' => $checkin->todos->map(fn ($todo) => [
                    'id' => $todo->id,
                    'title' => $todo->title,
                    'done' => (bool) $todo->done,
                    'created_at' => $todo->created_at?->toIso8601String(),
                ])->values()->toArray(),
                'updated_fields' => $updatedFields,
                'todo_actions' => $todoActions,
                'message' => !empty($updatedFields) || !empty($todoActions)
                    ? 'Checkin aktualisiert.'
                    : 'Keine Änderungen vorgenommen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Checkins: ' . $e->getMessage());
        }
    }

    private function validate(array $arguments): ?ToolResult
    {
        // daily_goal
        if (isset($arguments['daily_goal']) && $arguments['daily_goal'] !== null && $arguments['daily_goal'] !== '' && mb_strlen($arguments['daily_goal']) > 1000) {
            return ToolResult::error('VALIDATION_ERROR', 'daily_goal darf maximal 1000 Zeichen lang sein.');
        }

        // goal_category
        if (isset($arguments['goal_category']) && $arguments['goal_category'] !== null && $arguments['goal_category'] !== '') {
            if (!in_array($arguments['goal_category'], GoalCategory::values(), true)) {
                return ToolResult::error('VALIDATION_ERROR', 'goal_category muss einer der folgenden Werte sein: ' . implode(', ', GoalCategory::values()));
            }
        }

        // mood_score
        if (isset($arguments['mood_score']) && $arguments['mood_score'] !== null) {
            $moodScore = (int) $arguments['mood_score'];
            if ($moodScore < 0 || $moodScore > 4) {
                return ToolResult::error('VALIDATION_ERROR', 'mood_score muss zwischen 0 und 4 liegen.');
            }
        }

        // energy_score
        if (isset($arguments['energy_score']) && $arguments['energy_score'] !== null) {
            $energyScore = (int) $arguments['energy_score'];
            if ($energyScore < 0 || $energyScore > 4) {
                return ToolResult::error('VALIDATION_ERROR', 'energy_score muss zwischen 0 und 4 liegen.');
            }
        }

        // notes
        if (isset($arguments['notes']) && $arguments['notes'] !== null && $arguments['notes'] !== '' && mb_strlen($arguments['notes']) > 2000) {
            return ToolResult::error('VALIDATION_ERROR', 'notes darf maximal 2000 Zeichen lang sein.');
        }

        return null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'checkins', 'wellbeing', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
            'examples' => [
                'Aktualisiere Checkin 42: mood_score auf 4 setzen',
                'Füge ein Todo zum Checkin hinzu: add_todos=[{"title": "Code Review"}]',
                'Toggle Todo 15: toggle_todo_id=15',
                'Lösche Todo 15: delete_todo_id=15',
            ],
            'related_tools' => ['core.checkins.GET', 'core.checkins.POST'],
        ];
    }
}
