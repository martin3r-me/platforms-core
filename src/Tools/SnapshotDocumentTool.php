<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;
use Platform\Core\Models\DocumentSnapshot;

class SnapshotDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.SNAPSHOT';
    }

    public function getDescription(): string
    {
        return 'Erstellt einen Versionsstand (Snapshot) des aktuellen Dokument-Inhalts. '
            . 'Der Server speichert den kompletten data-Stand — es wird KEIN Content übertragen, nur die document_id. '
            . 'Kosten: 0 Tokens für Content, nur der API-Call selbst. '
            . 'Nutze dies VOR größeren Änderungen als Sicherungspunkt. '
            . 'Mit action:"list" die Snapshot-Historie abrufen. '
            . 'Mit action:"restore" einen früheren Stand wiederherstellen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Dokuments',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['create', 'list', 'restore'],
                    'description' => 'create: Neuen Snapshot erstellen (Standard). list: Snapshots auflisten. restore: Snapshot wiederherstellen.',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Snapshots (z.B. "Vor Quartals-Update"). Nur bei action:create.',
                ],
                'snapshot_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Snapshots der wiederhergestellt werden soll. Nur bei action:restore.',
                ],
            ],
            'required' => ['document_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            $document = Document::where('id', (int) $arguments['document_id'])
                ->where('team_id', $team->id)
                ->first();

            if (!$document) {
                return ToolResult::error('Dokument nicht gefunden oder kein Zugriff.', 'NOT_FOUND');
            }

            $action = $arguments['action'] ?? 'create';

            return match ($action) {
                'create' => $this->createSnapshot($document, $context, $arguments),
                'list' => $this->listSnapshots($document),
                'restore' => $this->restoreSnapshot($document, $arguments),
                default => ToolResult::error("Unbekannte action: {$action}", 'INVALID_ACTION'),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function createSnapshot(Document $document, ToolContext $context, array $arguments): ToolResult
    {
        $snapshot = DocumentSnapshot::create([
            'document_id' => $document->id,
            'created_by_user_id' => $context->user->id,
            'label' => $arguments['label'] ?? null,
            'data' => $document->data ?? [],
        ]);

        return ToolResult::success([
            'snapshot_id' => $snapshot->id,
            'document_id' => $document->id,
            'label' => $snapshot->label,
            'created_at' => $snapshot->created_at->toIso8601String(),
            'hint' => 'Snapshot erstellt. Kann mit action:"restore", snapshot_id:' . $snapshot->id . ' wiederhergestellt werden.',
        ]);
    }

    private function listSnapshots(Document $document): ToolResult
    {
        $snapshots = DocumentSnapshot::where('document_id', $document->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return ToolResult::success([
            'document_id' => $document->id,
            'snapshots' => $snapshots->map(fn($s) => [
                'id' => $s->id,
                'label' => $s->label,
                'created_by' => $s->createdByUser?->name,
                'created_at' => $s->created_at->toIso8601String(),
            ])->toArray(),
            'total' => $snapshots->count(),
        ]);
    }

    private function restoreSnapshot(Document $document, array $arguments): ToolResult
    {
        $snapshotId = $arguments['snapshot_id'] ?? null;
        if (!$snapshotId) {
            return ToolResult::error('snapshot_id ist erforderlich für action:restore.', 'MISSING_SNAPSHOT_ID');
        }

        $snapshot = DocumentSnapshot::where('id', $snapshotId)
            ->where('document_id', $document->id)
            ->first();

        if (!$snapshot) {
            return ToolResult::error('Snapshot nicht gefunden.', 'SNAPSHOT_NOT_FOUND');
        }

        $document->update(['data' => $snapshot->data]);

        return ToolResult::success([
            'document_id' => $document->id,
            'restored_from_snapshot' => $snapshot->id,
            'label' => $snapshot->label,
            'snapshot_created_at' => $snapshot->created_at->toIso8601String(),
            'hint' => 'Dokument auf Snapshot-Stand zurückgesetzt. Nutze core.documents.EXPORT zum Neu-Rendern.',
        ]);
    }
}
