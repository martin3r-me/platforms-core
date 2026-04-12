<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaMember;

class CreateTerminalAgendaTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.agendas.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt eine neue Agenda im Team. Der User wird automatisch als Owner eingetragen. '
            . 'Eine Agenda ist eine Sammlung von Items (Aufgaben, Termine, verlinkte Entities).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Agenda (erforderlich).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung der Agenda.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Emoji-Icon für die Agenda (z.B. "📅").',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $user = $context->user;
        $team = $context->team;

        if (! $user || ! $team) {
            return ToolResult::error('User und Team-Kontext erforderlich.', 'NO_CONTEXT');
        }

        $name = trim($arguments['name'] ?? '');
        if (empty($name)) {
            return ToolResult::error('name ist erforderlich.', 'VALIDATION_ERROR');
        }

        $agenda = TerminalAgenda::create([
            'team_id' => $team->id,
            'name' => $name,
            'description' => ! empty($arguments['description']) ? trim($arguments['description']) : null,
            'icon' => $arguments['icon'] ?? null,
            'created_by_user_id' => $user->id,
        ]);

        TerminalAgendaMember::create([
            'agenda_id' => $agenda->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        return ToolResult::success([
            'agenda_id' => $agenda->id,
            'name' => $agenda->name,
            'description' => $agenda->description,
            'icon' => $agenda->icon,
        ]);
    }
}
