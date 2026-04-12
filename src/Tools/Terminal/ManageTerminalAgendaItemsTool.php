<?php

namespace Platform\Core\Tools\Terminal;

use Platform\Core\Contracts\AgendaRenderable;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\TerminalAgenda;
use Platform\Core\Models\TerminalAgendaItem;
use Platform\Core\Models\TerminalAgendaMember;

class ManageTerminalAgendaItemsTool implements ToolContract
{
    private const AGENDABLE_TYPES = [
        'planner.task' => \Platform\Planner\Models\PlannerTask::class,
        'planner.project' => \Platform\Planner\Models\PlannerProject::class,
        'canvas' => \Platform\Canvas\Models\Canvas::class,
        'helpdesk.board' => \Platform\Helpdesk\Models\HelpdeskBoard::class,
        'helpdesk.ticket' => \Platform\Helpdesk\Models\HelpdeskTicket::class,
        'okr.objective' => \Platform\Okr\Models\Objective::class,
        'okr.key_result' => \Platform\Okr\Models\KeyResult::class,
        'brands.brand' => \Platform\Brands\Models\BrandsBrand::class,
    ];

    public function getName(): string
    {
        return 'terminal.agenda_items.POST';
    }

    public function getDescription(): string
    {
        return 'Erstellt freie Agenda-Items, hängt existierende Entities (Tasks, Canvases) an, oder löst Verknüpfungen. '
            . 'Aktionen: "create" (freies Item), "attach" (Entity verlinken), "detach" (Item entfernen). '
            . 'Beim Attach werden Titel/Farbe automatisch von der Entity übernommen, wenn sie AgendaRenderable implementiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'agenda_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Agenda.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['create', 'attach', 'detach'],
                    'description' => 'Aktion: "create" (neues freies Item), "attach" (Entity verlinken), "detach" (Item aus Agenda entfernen).',
                ],
                'title' => [
                    'type' => 'string',
                    'description' => 'Titel des Items (erforderlich bei action=create).',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Notizen zum Item.',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Optional: Datum im Format YYYY-MM-DD.',
                ],
                'time_start' => [
                    'type' => 'string',
                    'description' => 'Optional: Startzeit im Format HH:MM.',
                ],
                'time_end' => [
                    'type' => 'string',
                    'description' => 'Optional: Endzeit im Format HH:MM.',
                ],
                'color' => [
                    'type' => 'string',
                    'description' => 'Optional: Farbe (z.B. "red", "blue", "green").',
                ],
                'agendable_type' => [
                    'type' => 'string',
                    'enum' => array_keys(self::AGENDABLE_TYPES),
                    'description' => 'Entity-Typ zum Verlinken (erforderlich bei action=attach). Z.B. "planner.task", "canvas", "helpdesk.ticket".',
                ],
                'agendable_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Entity zum Verlinken (erforderlich bei action=attach).',
                ],
                'slot_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines Agenda-Slots, in den das Item einsortiert wird.',
                ],
                'item_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Agenda-Items zum Entfernen (erforderlich bei action=detach).',
                ],
            ],
            'required' => ['agenda_id', 'action'],
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
        $action = $arguments['action'] ?? null;

        if (! $agendaId || ! $action) {
            return ToolResult::error('agenda_id und action sind erforderlich.', 'VALIDATION_ERROR');
        }

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

        return match ($action) {
            'create' => $this->createItem($arguments, $agenda, $user),
            'attach' => $this->attachItem($arguments, $agenda, $user),
            'detach' => $this->detachItem($arguments, $agenda),
            default => ToolResult::error('Ungültige action. Erlaubt: create, attach, detach.', 'VALIDATION_ERROR'),
        };
    }

    private function createItem(array $arguments, TerminalAgenda $agenda, $user): ToolResult
    {
        $title = trim($arguments['title'] ?? '');
        if (empty($title)) {
            return ToolResult::error('title ist erforderlich bei action=create.', 'VALIDATION_ERROR');
        }

        $item = TerminalAgendaItem::create([
            'agenda_id' => $agenda->id,
            'agenda_slot_id' => $arguments['slot_id'] ?? null,
            'title' => $title,
            'notes' => ! empty($arguments['notes']) ? trim($arguments['notes']) : null,
            'date' => $arguments['date'] ?? null,
            'time_start' => $arguments['time_start'] ?? null,
            'time_end' => $arguments['time_end'] ?? null,
            'color' => $arguments['color'] ?? null,
            'created_by_user_id' => $user->id,
        ]);

        $agenda->refreshItemCount();

        return ToolResult::success([
            'item_id' => $item->id,
            'agenda_id' => $agenda->id,
            'title' => $item->title,
            'action' => 'created',
        ]);
    }

    private function attachItem(array $arguments, TerminalAgenda $agenda, $user): ToolResult
    {
        $typeKey = $arguments['agendable_type'] ?? null;
        $entityId = $arguments['agendable_id'] ?? null;

        if (! $typeKey || ! $entityId) {
            return ToolResult::error('agendable_type und agendable_id sind erforderlich bei action=attach.', 'VALIDATION_ERROR');
        }

        $fqcn = self::AGENDABLE_TYPES[$typeKey] ?? null;
        if (! $fqcn) {
            return ToolResult::error('Ungültiger agendable_type. Erlaubt: ' . implode(', ', array_keys(self::AGENDABLE_TYPES)), 'VALIDATION_ERROR');
        }

        if (! class_exists($fqcn)) {
            return ToolResult::error('Entity-Klasse nicht verfügbar.', 'VALIDATION_ERROR');
        }

        $entity = $fqcn::find($entityId);
        if (! $entity) {
            return ToolResult::error('Entity nicht gefunden (ID: ' . $entityId . ').', 'NOT_FOUND');
        }

        // Check if already attached
        $existing = TerminalAgendaItem::where('agenda_id', $agenda->id)
            ->where('agendable_type', $fqcn)
            ->where('agendable_id', $entityId)
            ->first();

        if ($existing) {
            return ToolResult::success([
                'item_id' => $existing->id,
                'agenda_id' => $agenda->id,
                'title' => $existing->title,
                'action' => 'already_attached',
            ]);
        }

        // Get title/color from entity
        $title = null;
        $color = null;

        if ($entity instanceof AgendaRenderable) {
            $rendered = $entity->toAgendaItem();
            $title = $rendered['title'] ?? null;
            $color = $rendered['color'] ?? null;
        }

        if (! $title) {
            $title = $entity->title ?? $entity->name ?? $typeKey . ' #' . $entityId;
        }

        $item = TerminalAgendaItem::create([
            'agenda_id' => $agenda->id,
            'agenda_slot_id' => $arguments['slot_id'] ?? null,
            'agendable_type' => $fqcn,
            'agendable_id' => $entityId,
            'title' => $title,
            'color' => $arguments['color'] ?? $color,
            'date' => $arguments['date'] ?? null,
            'time_start' => $arguments['time_start'] ?? null,
            'time_end' => $arguments['time_end'] ?? null,
            'created_by_user_id' => $user->id,
        ]);

        $agenda->refreshItemCount();

        return ToolResult::success([
            'item_id' => $item->id,
            'agenda_id' => $agenda->id,
            'title' => $item->title,
            'agendable_type' => $typeKey,
            'agendable_id' => $entityId,
            'action' => 'attached',
        ]);
    }

    private function detachItem(array $arguments, TerminalAgenda $agenda): ToolResult
    {
        $itemId = $arguments['item_id'] ?? null;
        if (! $itemId) {
            return ToolResult::error('item_id ist erforderlich bei action=detach.', 'VALIDATION_ERROR');
        }

        $item = TerminalAgendaItem::where('id', $itemId)
            ->where('agenda_id', $agenda->id)
            ->first();

        if (! $item) {
            return ToolResult::error('Item nicht gefunden in dieser Agenda.', 'NOT_FOUND');
        }

        $title = $item->title;
        $item->delete();
        $agenda->refreshItemCount();

        return ToolResult::success([
            'deleted_item_id' => $itemId,
            'agenda_id' => $agenda->id,
            'title' => $title,
            'action' => 'detached',
        ]);
    }
}
