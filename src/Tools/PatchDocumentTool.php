<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Document;

class PatchDocumentTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.documents.PATCH';
    }

    public function getDescription(): string
    {
        return 'Gezielte Textänderungen innerhalb von html_content per Search-and-Replace. '
            . 'WICHTIG: Zuerst core.documents.GET aufrufen um den aktuellen html_content zu lesen, dann PATCH mit den Änderungen. '
            . 'Übergib ein Array von {search, replace} Paaren — jedes search muss exakt im html_content vorkommen. '
            . 'Spart massiv Tokens: Statt den gesamten html_content neu zu senden, nur die geänderten Stellen übertragen. '
            . 'Für komplettes Überschreiben: core.documents.UPDATE. Für Anhängen: core.documents.APPEND.';
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
                'patches' => [
                    'type' => 'array',
                    'description' => 'Array von Search-and-Replace Operationen. Werden in Reihenfolge angewendet.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'search' => [
                                'type' => 'string',
                                'description' => 'Exakter Text der ersetzt werden soll (muss im html_content vorkommen)',
                            ],
                            'replace' => [
                                'type' => 'string',
                                'description' => 'Neuer Text der den search-Text ersetzt',
                            ],
                        ],
                        'required' => ['search', 'replace'],
                    ],
                ],
            ],
            'required' => ['document_id', 'patches'],
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

            $patches = $arguments['patches'] ?? [];
            if (empty($patches)) {
                return ToolResult::error('Keine Patches übergeben.', 'NO_PATCHES');
            }

            $data = $document->data ?? [];
            $content = $data['html_content'] ?? '';

            if ($content === '') {
                return ToolResult::error('Dokument hat keinen html_content zum Patchen.', 'NO_CONTENT');
            }

            $applied = [];
            $failed = [];

            foreach ($patches as $i => $patch) {
                $search = $patch['search'] ?? '';
                $replace = $patch['replace'] ?? '';

                if ($search === '') {
                    $failed[] = ['index' => $i, 'reason' => 'search ist leer'];
                    continue;
                }

                if (str_contains($content, $search)) {
                    $content = str_replace($search, $replace, $content);
                    $applied[] = $i;
                } else {
                    $failed[] = ['index' => $i, 'reason' => 'search-Text nicht gefunden', 'search' => mb_substr($search, 0, 80)];
                }
            }

            if (empty($applied)) {
                return ToolResult::error('Kein Patch konnte angewendet werden.', 'ALL_PATCHES_FAILED', ['failed' => $failed]);
            }

            $data['html_content'] = $content;
            $document->update(['data' => $data]);

            $result = [
                'id' => $document->id,
                'title' => $document->title,
                'patches_applied' => count($applied),
                'patches_total' => count($patches),
                'content_length' => mb_strlen($content),
            ];

            if (!empty($failed)) {
                $result['failed'] = $failed;
                $result['hint'] = count($applied) . ' von ' . count($patches) . ' Patches angewendet. Fehlgeschlagene Patches prüfen — search-Text muss exakt übereinstimmen. Nutze core.documents.GET um den aktuellen Content zu lesen. Danach core.documents.EXPORT zum Neu-Rendern.';
            } else {
                $result['hint'] = 'Alle Patches angewendet. Nutze core.documents.EXPORT um das PDF neu zu rendern.';
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler beim Patchen: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
