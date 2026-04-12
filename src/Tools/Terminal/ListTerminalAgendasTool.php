<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalAgendaMember;

class ListTerminalAgendasTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.agendas.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle Agendas des aktuellen Users im Team. '
            . 'Gibt ID, Name, Beschreibung, Icon, Anzahl offener Items und Rolle zurück. '
            . 'Nutze agenda_id aus den Ergebnissen für terminal.agenda_items.GET oder terminal.agenda_items.POST.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_items' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, werden die Items jeder Agenda mitgeliefert.',
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

        $memberships = TerminalAgendaMember::where('user_id', $user->id)
            ->whereHas('agenda', fn ($q) => $q->where('team_id', $team->id))
            ->with('agenda')
            ->get();

        $includeItems = $arguments['include_items'] ?? false;

        $agendas = [];
        foreach ($memberships as $membership) {
            $agenda = $membership->agenda;
            if (! $agenda) {
                continue;
            }

            $item = [
                'id' => $agenda->id,
                'name' => $agenda->name,
                'description' => $agenda->description,
                'icon' => $agenda->icon,
                'item_count' => $agenda->item_count,
                'role' => $membership->role,
            ];

            if ($includeItems) {
                $items = $agenda->items()->ordered()->get();
                $item['items'] = $items->map(fn ($i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'notes' => $i->notes,
                    'date' => $i->date?->toDateString(),
                    'time_start' => $i->time_start ? substr($i->time_start, 0, 5) : null,
                    'time_end' => $i->time_end ? substr($i->time_end, 0, 5) : null,
                    'is_done' => $i->is_done,
                    'color' => $i->color,
                    'is_linked' => ! empty($i->agendable_type),
                    'agendable_type' => $i->agendable_type,
                    'agendable_id' => $i->agendable_id,
                ])->toArray();
            }

            $agendas[] = $item;
        }

        return ToolResult::success([
            'agendas' => $agendas,
            'count' => count($agendas),
        ]);
    }
}
