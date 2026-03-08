<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;

class GetDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.GET';
    }

    public function getDescription(): string
    {
        return 'Gibt Details zu einem Dokument zurück oder listet Dokumente des Teams auf. '
            . 'Mit document_id: Einzelnes Dokument mit Status, Daten und Download-URL (wenn gerendert). '
            . 'Ohne document_id: Liste aller Dokumente mit Filtern. '
            . 'Typischer Workflow nach core.documents.CREATE: Rufe dieses Tool mit document_id auf um zu prüfen, ob das PDF fertig gerendert ist und die Download-URL abzurufen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'ID eines bestimmten Dokuments. Gibt Details inkl. Download-URL zurück.',
                ],
                'template_key' => [
                    'type' => 'string',
                    'description' => 'Filter nach Template-Key (z.B. "report", "letter"). Nur bei Listenabfrage.',
                ],
                'status' => [
                    'type' => 'string',
                    'description' => 'Filter nach Status: draft, rendered, failed. Nur bei Listenabfrage.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Ergebnisse (Standard: 25, Max: 100)',
                ],
                'offset' => [
                    'type' => 'integer',
                    'description' => 'Offset für Pagination',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }

            // Single document
            if (!empty($arguments['document_id'])) {
                return $this->getOne((int) $arguments['document_id'], $team->id);
            }

            // List documents
            return $this->listDocuments($arguments, $team->id);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function getOne(int $documentId, int $teamId): ToolResult
    {
        $document = Document::with(['template', 'outputFile', 'exports', 'teamTags'])
            ->where('id', $documentId)
            ->where('team_id', $teamId)
            ->first();

        if (!$document) {
            return ToolResult::error('Dokument nicht gefunden oder kein Zugriff.', 'NOT_FOUND');
        }

        $result = [
            'id' => $document->id,
            'title' => $document->title,
            'template_key' => $document->template_key,
            'template_name' => $document->template?->name,
            'status' => $document->status,
            'data' => $document->data,
            'share_url' => $document->share_url,
            'tags' => $document->teamTags->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'color' => $t->color,
            ])->toArray(),
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];

        // Output URLs if rendered (signed, time-limited)
        if ($document->outputFile) {
            $result['output_url'] = $document->output_url;
            $result['download_url'] = $document->download_url;
        }

        // Export history
        if ($document->exports->isNotEmpty()) {
            $result['exports'] = $document->exports->map(fn($export) => [
                'id' => $export->id,
                'renderer' => $export->renderer,
                'status' => $export->status,
                'error_message' => $export->error_message,
                'created_at' => $export->created_at?->toIso8601String(),
            ])->toArray();
        }

        // Hints based on status
        if ($document->status === 'draft') {
            $result['hint'] = 'Dokument ist noch ein Draft. Nutze core.documents.EXPORT um es zu rendern.';
        } elseif ($document->status === 'failed') {
            $result['hint'] = 'Rendering fehlgeschlagen. Prüfe exports.error_message. Nutze core.documents.EXPORT zum erneuten Versuch.';
        }

        return ToolResult::success($result);
    }

    private function listDocuments(array $arguments, int $teamId): ToolResult
    {
        $limit = min((int) ($arguments['limit'] ?? 25), 100);
        $offset = (int) ($arguments['offset'] ?? 0);

        $query = Document::where('team_id', $teamId)
            ->orderByDesc('created_at');

        if (!empty($arguments['template_key'])) {
            $query->where('template_key', $arguments['template_key']);
        }

        if (!empty($arguments['status'])) {
            $query->where('status', $arguments['status']);
        }

        $total = $query->count();
        $documents = $query->skip($offset)->take($limit)->get();

        return ToolResult::success([
            'documents' => $documents->map(fn($doc) => [
                'id' => $doc->id,
                'title' => $doc->title,
                'template_key' => $doc->template_key,
                'status' => $doc->status,
                'has_output' => $doc->output_context_file_id !== null,
                'created_at' => $doc->created_at?->toIso8601String(),
            ])->toArray(),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total,
        ]);
    }
}
