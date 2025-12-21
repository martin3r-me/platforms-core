<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Tool-Implementierung für data_write
 * 
 * Wrapper um CoreWriteProxy, der das ToolContract implementiert
 */
class DataWriteTool implements ToolContract
{
    public function __construct(
        private CoreWriteProxy $proxy
    ) {}

    public function getName(): string
    {
        return 'data_write';
    }

    public function getDescription(): string
    {
        return 'Erstellt, aktualisiert oder löscht Datensätze in verschiedenen Entitäten. Unterstützt die Operationen: create (Erstellen), update (Aktualisieren), delete (Löschen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity' => [
                    'type' => 'string',
                    'description' => 'Name der Entität (z.B. "planner.tasks", "okr.key_results")'
                ],
                'operation' => [
                    'type' => 'string',
                    'enum' => ['create', 'update', 'delete'],
                    'description' => 'Operation: create (Erstellen), update (Aktualisieren), delete (Löschen)'
                ],
                'id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'ID für update/delete-Operationen'
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Daten für create/update. Erforderliche Felder hängen von der Entität ab.'
                ]
            ],
            'required' => ['entity', 'operation', 'data']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $entity = $arguments['entity'] ?? '';
        $operation = $arguments['operation'] ?? '';
        
        // Alle Argumente als Input übergeben
        $input = $arguments;

        $result = $this->proxy->executeCommand($entity, $operation, $input, [
            'trace_id' => bin2hex(random_bytes(8))
        ]);

        // Konvertiere altes Format zu ToolResult
        if (($result['ok'] ?? false) === true) {
            return ToolResult::success($result['data'] ?? null, [
                'message' => $result['message'] ?? null,
                '_source' => $result['data']['_source'] ?? []
            ]);
        }

        $error = $result['error'] ?? [];
        return ToolResult::error(
            $error['message'] ?? 'Unbekannter Fehler',
            $error['code'] ?? 'UNKNOWN_ERROR',
            ['trace_id' => $error['trace_id'] ?? null]
        );
    }
}

