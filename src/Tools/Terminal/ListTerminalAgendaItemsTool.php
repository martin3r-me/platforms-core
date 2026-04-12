<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaItem;
use Platform\Core\Models\TerminalAgendaMember;

class ListTerminalAgendaItemsTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.agenda_items.GET';
    }

    public function getDescription(): string
    {
        return 'Listet Items einer Agenda oder "Mein Tag" (alle Agendas des Users für ein Datum). '
            . 'Ohne agenda_id → "Mein Tag"-Ansicht mit Items aus allen Agendas. '
            . 'Items können freie Einträge oder verlinkte Entities (Tasks, Canvases) sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agenda_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID einer bestimmten Agenda. Wenn nicht angegeben, werden Items aus allen Agendas des Users angezeigt ("Mein Tag").',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Optional: Datum im Format YYYY-MM-DD. Standard: heute.',
                ],
                'include_backlog' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Auch Items ohne Datum (Backlog) einbeziehen. Standard: false.',
                ],
            ],
            'required' => [],
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
        $date = $arguments['date'] ?? now()->toDateString();
        $includeBacklog = $arguments['include_backlog'] ?? false;

        if ($agendaId) {
            // Single agenda
            $agenda = TerminalAgenda::where('id', $agendaId)
                ->where('team_id', $team->id)
                ->first();

            if (! $agenda) {
                return ToolResult::error('Agenda nicht gefunden.', 'NOT_FOUND');
            }

            $isMember = TerminalAgendaMember::where('agenda_id', $agenda->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $isMember) {
                return ToolResult::error('Du bist kein Mitglied dieser Agenda.', 'FORBIDDEN');
            }

            $query = $agenda->items()->ordered();
        } else {
            // "Mein Tag" — all user agendas in team
            $agendaIds = TerminalAgendaMember::where('user_id', $user->id)
                ->whereHas('agenda', fn ($q) => $q->where('team_id', $team->id))
                ->pluck('agenda_id');

            $query = TerminalAgendaItem::whereIn('agenda_id', $agendaIds)
                ->with('agenda:id,name,icon')
                ->ordered();
        }

        // Date filter
        $items = collect();
        $dateItems = (clone $query)->forDate($date)->get();
        $items = $items->merge($dateItems);

        if ($includeBacklog) {
            $backlogItems = (clone $query)->backlog()->get();
            $items = $items->merge($backlogItems);
        }

        $result = $items->map(function ($item) use ($agendaId) {
            $data = [
                'id' => $item->id,
                'agenda_id' => $item->agenda_id,
                'title' => $item->title,
                'notes' => $item->notes,
                'date' => $item->date?->toDateString(),
                'time_start' => $item->time_start ? substr($item->time_start, 0, 5) : null,
                'time_end' => $item->time_end ? substr($item->time_end, 0, 5) : null,
                'is_done' => $item->is_done,
                'color' => $item->color,
                'is_linked' => ! empty($item->agendable_type),
                'agendable_type' => $item->agendable_type,
                'agendable_id' => $item->agendable_id,
            ];

            if (! $agendaId && $item->relationLoaded('agenda') && $item->agenda) {
                $data['agenda_name'] = $item->agenda->name;
                $data['agenda_icon'] = $item->agenda->icon ?? '📋';
            }

            return $data;
        })->toArray();

        return ToolResult::success([
            'items' => $result,
            'count' => count($result),
            'date' => $date,
            'mode' => $agendaId ? 'agenda' : 'my_day',
        ]);
    }
}
