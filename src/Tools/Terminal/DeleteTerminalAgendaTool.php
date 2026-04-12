<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaItem;
use Platform\Core\Models\TerminalAgendaMember;

class DeleteTerminalAgendaTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.agendas.DELETE';
    }

    public function getDescription(): string
    {
        return 'Löscht eine Agenda inkl. aller Items und Mitgliedschaften. '
            . 'Nur Agenda-Owner können löschen. Verlinkte Entities (Tasks, Canvases etc.) werden NICHT gelöscht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agenda_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu löschenden Agenda.',
                ],
            ],
            'required' => ['agenda_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $user = $context->user;
        $team = $context->team;

        if (! $user || ! $team) {
            return ToolResult::error('User und Team-Kontext erforderlich.', 'NO_CONTEXT');
        }

        $agendaId = $arguments['agenda_id'] ?? null;
        if (! $agendaId) {
            return ToolResult::error('agenda_id ist erforderlich.', 'VALIDATION_ERROR');
        }

        $agenda = TerminalAgenda::where('id', $agendaId)
            ->where('team_id', $team->id)
            ->first();

        if (! $agenda) {
            return ToolResult::error('Agenda nicht gefunden.', 'NOT_FOUND');
        }

        $isOwner = TerminalAgendaMember::where('agenda_id', $agenda->id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return ToolResult::error('Nur Agenda-Owner können Agendas löschen.', 'FORBIDDEN');
        }

        $name = $agenda->name;
        $itemCount = $agenda->items()->count();

        TerminalAgendaItem::where('agenda_id', $agenda->id)->delete();
        TerminalAgendaMember::where('agenda_id', $agenda->id)->delete();
        $agenda->delete();

        return ToolResult::success([
            'deleted_agenda_id' => $agendaId,
            'name' => $name,
            'items_deleted' => $itemCount,
        ]);
    }
}
