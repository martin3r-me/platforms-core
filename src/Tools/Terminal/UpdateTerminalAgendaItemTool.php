<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalAgendaItem;
use Platform\Core\Models\TerminalAgendaMember;

class UpdateTerminalAgendaItemTool implements ToolContract
{
    public function getName(): string
    {
        return 'terminal.agenda_items.PATCH';
    }

    public function getDescription(): string
    {
        return 'Aktualisiert ein Agenda-Item: Titel, Notizen, Datum, Zeiten, Farbe oder Erledigt-Status. '
            . 'Nur Felder die übergeben werden, werden geändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'item_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Agenda-Items.',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Neuer Titel.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Neue Notizen (leerer String zum Löschen).',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Neues Datum (YYYY-MM-DD) oder null zum Entfernen.',
                ],
                'time_start' => [
                    'type' => 'string',
                    'description' => 'Neue Startzeit (HH:MM) oder null zum Entfernen.',
                ],
                'time_end' => [
                    'type' => 'string',
                    'description' => 'Neue Endzeit (HH:MM) oder null zum Entfernen.',
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'Neue Farbe oder null zum Entfernen.',
                ],
                'is_done' => [
                    'type' => 'boolean',
                    'description' => 'Erledigt-Status setzen.',
                ],
                'agenda_slot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Agenda-Slots zum Verschieben (null für Backlog).',
                ],
            ],
            'required' => ['item_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $user = $context->user;
        $team = $context->team;

        if (! $user || ! $team) {
            return ToolResult::error('User und Team-Kontext erforderlich.', 'NO_CONTEXT');
        }

        $itemId = $arguments['item_id'] ?? null;
        if (! $itemId) {
            return ToolResult::error('item_id ist erforderlich.', 'VALIDATION_ERROR');
        }

        $item = TerminalAgendaItem::whereHas('agenda', fn ($q) => $q->where('team_id', $team->id))
            ->find($itemId);

        if (! $item) {
            return ToolResult::error('Item nicht gefunden.', 'NOT_FOUND');
        }

        // Check membership
        $isMember = TerminalAgendaMember::where('agenda_id', $item->agenda_id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            return ToolResult::error('Du bist kein Mitglied dieser Agenda.', 'FORBIDDEN');
        }

        $updatable = ['title', 'notes', 'date', 'time_start', 'time_end', 'color', 'is_done', 'agenda_slot_id'];
        $updates = [];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $arguments)) {
                $value = $arguments[$field];
                if ($field === 'title' && is_string($value)) {
                    $value = trim($value);
                    if (empty($value)) {
                        continue;
                    }
                }
                if ($field === 'notes' && is_string($value)) {
                    $value = trim($value);
                    $value = $value === '' ? null : $value;
                }
                $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            return ToolResult::error('Keine Felder zum Aktualisieren angegeben.', 'VALIDATION_ERROR');
        }

        $item->update($updates);

        if (array_key_exists('is_done', $updates)) {
            $item->agenda->refreshItemCount();
        }

        return ToolResult::success([
            'item_id' => $item->id,
            'agenda_id' => $item->agenda_id,
            'updated_fields' => array_keys($updates),
            'title' => $item->title,
            'is_done' => $item->is_done,
        ]);
    }
}
