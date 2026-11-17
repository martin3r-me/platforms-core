<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\DirectUploadService;

/**
 * Verknüpft eine vorhandene file_id mit einem oder mehreren Kontexten.
 */
class AttachContextFileTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.context.files.attach';
    }

    public function getDescription(): string
    {
        return 'Verknüpft eine vorhandene Datei (file_id) mit einem oder mehreren Kontexten. Der erste Kontext wird primär, falls die Datei noch keinen hat; '
            . 'weitere werden als Referenzen angelegt. Unverknüpfte Dateien werden nach 24h automatisch gelöscht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'file_id' => ['type' => 'integer', 'description' => 'ID des Context-Files'],
                'contexts' => [
                    'type' => 'array',
                    'description' => 'Ziel-Kontexte',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'context_type' => ['type' => 'string'],
                            'context_id' => ['type' => 'integer'],
                        ],
                        'required' => ['context_type', 'context_id'],
                    ],
                ],
            ],
            'required' => ['file_id', 'contexts'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->team) {
                return ToolResult::error('Kein Team-Kontext verfügbar', 'NO_TEAM');
            }
            $file = ContextFile::where('id', (int) ($arguments['file_id'] ?? 0))->where('team_id', $context->team->id)->first();
            if (!$file) {
                return ToolResult::error('Datei nicht gefunden.', 'FILE_NOT_FOUND');
            }
            $contexts = $arguments['contexts'] ?? [];
            if (!is_array($contexts) || $contexts === []) {
                return ToolResult::error('contexts darf nicht leer sein', 'VALIDATION_ERROR');
            }

            $service = app(DirectUploadService::class);
            foreach ($contexts as $c) {
                $type = $c['context_type'] ?? null;
                $id = (int) ($c['context_id'] ?? 0);
                if (!$type || !$id) {
                    return ToolResult::error('Jeder Kontext braucht context_type und context_id', 'VALIDATION_ERROR');
                }
                if ($err = $service->validateContext($type, $id, (int) $context->team->id)) {
                    return ToolResult::error($err, 'INVALID_CONTEXT');
                }
            }

            $results = [];
            foreach ($contexts as $c) {
                $results[] = [
                    'context_type' => $c['context_type'],
                    'context_id' => (int) $c['context_id'],
                    'link' => $service->attach($file->refresh(), $c['context_type'], (int) $c['context_id']),
                ];
            }

            return ToolResult::success(['file_id' => $file->id, 'attached' => $results]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler bei files.attach: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
