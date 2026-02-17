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
 * core.checkins.POST
 *
 * Erstellt oder aktualisiert einen Checkin für den aktuellen User.
 * Pro User und Datum existiert maximal ein Checkin (Upsert-Logik wie im ModalCheckin).
 * Optional können Todos direkt mit erstellt werden.
 */
class CreateCheckinTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.checkins.POST';
    }

    public function getDescription(): string
    {
        return 'POST /core/checkins - Erstellt einen Checkin für ein bestimmtes Datum (Standard: heute). '
            . 'Pro User und Datum existiert maximal ein Checkin – existiert bereits einer, wird er aktualisiert (Upsert). '
            . 'Felder: date, daily_goal, goal_category (focus|team|health|learning|project|personal|growth), '
            . 'mood_score (0-4), energy_score (0-4), Reflexionsfelder (hydrated, exercised, slept_well, focused_work, social_time, needs_support), '
            . 'notes, todos (Array mit title). Alle Felder außer date sind optional.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => [
                    'type' => 'string',
                    'description' => 'Datum des Checkins (YYYY-MM-DD). Standard: heute.',
                ],
                'daily_goal' => [
                    'type' => 'string',
                    'description' => 'Tagesziel (max 1000 Zeichen).',
                ],
                'goal_category' => [
                    'type' => 'string',
                    'enum' => ['focus', 'team', 'health', 'learning', 'project', 'personal', 'growth'],
                    'description' => 'Kategorie des Tagesziels. Erlaubte Werte: focus (Fokus), team (Team), health (Gesundheit), learning (Lernen), project (Projekt), personal (Persönlich), growth (Wachstum).',
                ],
                'mood_score' => [
                    'type' => 'integer',
                    'description' => 'Stimmungswert 0-4. 0=Sehr schlecht, 1=Schlecht, 2=Neutral, 3=Gut, 4=Ausgezeichnet.',
                ],
                'energy_score' => [
                    'type' => 'integer',
                    'description' => 'Energielevel 0-4. 0=Sehr niedrig, 1=Niedrig, 2=Mittel, 3=Hoch, 4=Sehr hoch.',
                ],
                'hydrated' => [
                    'type' => 'boolean',
                    'description' => 'Ausreichend getrunken? Standard: false.',
                ],
                'exercised' => [
                    'type' => 'boolean',
                    'description' => 'Sport gemacht? Standard: false.',
                ],
                'slept_well' => [
                    'type' => 'boolean',
                    'description' => 'Gut geschlafen? Standard: false.',
                ],
                'focused_work' => [
                    'type' => 'boolean',
                    'description' => 'Fokussiert gearbeitet? Standard: false.',
                ],
                'social_time' => [
                    'type' => 'boolean',
                    'description' => 'Soziale Zeit gehabt? Standard: false.',
                ],
                'needs_support' => [
                    'type' => 'boolean',
                    'description' => 'Braucht Unterstützung? Standard: false.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Freitext-Notizen (max 2000 Zeichen).',
                ],
                'todos' => [
                    'type' => 'array',
                    'description' => 'Todos für den Tag. Array von Objekten mit "title" (string, erforderlich) und optional "done" (boolean).',
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
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $user = $context->user;
            if (!$user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Validierung
            $validationError = $this->validate($arguments);
            if ($validationError !== null) {
                return $validationError;
            }

            $date = $arguments['date'] ?? now()->format('Y-m-d');

            // Checkin-Daten vorbereiten
            $checkinData = [
                'user_id' => $user->id,
                'date' => $date,
            ];

            // Optionale Felder setzen
            $optionalFields = [
                'daily_goal', 'goal_category', 'notes',
            ];
            foreach ($optionalFields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $checkinData[$field] = $arguments[$field];
                }
            }

            // Integer-Felder
            foreach (['mood_score', 'energy_score'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $checkinData[$field] = $arguments[$field] !== null && $arguments[$field] !== '' ? (int) $arguments[$field] : null;
                }
            }

            // Boolean-Felder
            $booleanFields = ['hydrated', 'exercised', 'slept_well', 'focused_work', 'social_time', 'needs_support'];
            foreach ($booleanFields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $checkinData[$field] = (bool) $arguments[$field];
                }
            }

            // Upsert: Existierender Checkin oder neuer
            $existingCheckin = Checkin::where('user_id', $user->id)
                ->where('date', $date)
                ->first();

            $isUpdate = false;
            if ($existingCheckin) {
                $existingCheckin->update($checkinData);
                $checkin = $existingCheckin->fresh();
                $isUpdate = true;
            } else {
                $checkin = Checkin::create($checkinData);
            }

            // Todos erstellen (nur bei neuen Todos)
            $createdTodos = [];
            if (!empty($arguments['todos']) && is_array($arguments['todos'])) {
                foreach ($arguments['todos'] as $todoData) {
                    $title = trim((string) ($todoData['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $todo = CheckinTodo::create([
                        'checkin_id' => $checkin->id,
                        'title' => $title,
                        'done' => (bool) ($todoData['done'] ?? false),
                    ]);
                    $createdTodos[] = [
                        'id' => $todo->id,
                        'title' => $todo->title,
                        'done' => (bool) $todo->done,
                    ];
                }
            }

            // Response
            $response = [
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
                'todos_created' => $createdTodos,
                'was_updated' => $isUpdate,
                'message' => $isUpdate
                    ? "Checkin für {$date} aktualisiert."
                    : "Checkin für {$date} erstellt.",
            ];

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Checkins: ' . $e->getMessage());
        }
    }

    private function validate(array $arguments): ?ToolResult
    {
        // Datum validieren
        if (isset($arguments['date'])) {
            $date = $arguments['date'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return ToolResult::error('VALIDATION_ERROR', 'date muss im Format YYYY-MM-DD sein.');
            }
            try {
                \Carbon\Carbon::parse($date);
            } catch (\Exception $e) {
                return ToolResult::error('VALIDATION_ERROR', 'date ist kein gültiges Datum.');
            }
        }

        // daily_goal
        if (isset($arguments['daily_goal']) && mb_strlen($arguments['daily_goal']) > 1000) {
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
        if (isset($arguments['notes']) && mb_strlen($arguments['notes']) > 2000) {
            return ToolResult::error('VALIDATION_ERROR', 'notes darf maximal 2000 Zeichen lang sein.');
        }

        return null;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'checkins', 'wellbeing', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true, // Upsert: gleicher Tag = Update
            'examples' => [
                'Erstelle einen Checkin für heute mit Tagesziel "Präsentation fertigstellen"',
                'Checkin mit mood_score=3, energy_score=4, hydrated=true, exercised=true',
                'Checkin für 2025-06-15 mit Todos erstellen',
            ],
            'related_tools' => ['core.checkins.GET', 'core.checkins.PUT'],
        ];
    }
}
