<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class FrontmatterTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.FRONTMATTER';
    }

    public function getDescription(): string
    {
        return 'FRONTMATTER /obsidian/files - Liest YAML-Frontmatter einer oder mehrerer Markdown-Dateien. Ideal für Metadaten-Abfragen (Tags, Status, Links) ohne den gesamten Inhalt zu laden. Kann auch alle Dateien eines Pfades scannen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vault_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Vaults.',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Dateipfad oder Ordnerpfad. Bei Ordner werden alle .md Dateien darin gescannt.',
                ],
                'recursive' => [
                    'type' => 'boolean',
                    'description' => 'Bei Ordner: auch Unterordner durchsuchen. Standard: false.',
                ],
                'filter_tag' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur Dateien mit diesem Tag im Frontmatter zurückgeben (sucht in tags/tag Feld).',
                ],
                'filter_field' => [
                    'type' => 'string',
                    'description' => 'Optional: Frontmatter-Feld das existieren muss (z.B. "status", "project").',
                ],
                'filter_value' => [
                    'type' => 'string',
                    'description' => 'Optional: Wert den filter_field haben muss.',
                ],
            ],
            'required' => ['vault_id', 'path'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $vault = ObsidianVault::where('id', (int) ($arguments['vault_id'] ?? 0))
                ->where('user_id', $context->user->id)
                ->first();

            if (! $vault) {
                return ToolResult::error('Vault nicht gefunden.', 'NOT_FOUND');
            }

            $path = (string) ($arguments['path'] ?? '');
            if (trim($path) === '') {
                return ToolResult::error('path ist erforderlich.', 'VALIDATION_ERROR');
            }

            $recursive = (bool) ($arguments['recursive'] ?? false);
            $filterTag = isset($arguments['filter_tag']) ? trim((string) $arguments['filter_tag']) : null;
            $filterField = isset($arguments['filter_field']) ? trim((string) $arguments['filter_field']) : null;
            $filterValue = isset($arguments['filter_value']) ? trim((string) $arguments['filter_value']) : null;

            // Single file or directory?
            $filePaths = [];
            if (str_ends_with($path, '.md') || str_ends_with($path, '.markdown')) {
                $filePaths[] = $path;
            } else {
                // It's a directory — list .md files
                $items = $recursive
                    ? $this->storage->listFilesRecursive($vault, $path)
                    : $this->storage->listFiles($vault, $path);

                foreach ($items as $item) {
                    if ($item['type'] === 'file' && (str_ends_with($item['path'], '.md') || str_ends_with($item['path'], '.markdown'))) {
                        $filePaths[] = $item['path'];
                    }
                }
            }

            $results = [];
            foreach ($filePaths as $filePath) {
                try {
                    $frontmatter = $this->storage->parseFrontmatter($vault, $filePath);
                } catch (\Throwable) {
                    continue;
                }

                if ($frontmatter === null) {
                    continue;
                }

                // Apply filters
                if ($filterTag !== null && $filterTag !== '') {
                    $tags = $frontmatter['tags'] ?? $frontmatter['tag'] ?? [];
                    if (is_string($tags)) {
                        $tags = array_map('trim', explode(',', $tags));
                    }
                    if (! is_array($tags) || ! in_array($filterTag, $tags, true)) {
                        continue;
                    }
                }

                if ($filterField !== null && $filterField !== '') {
                    if (! array_key_exists($filterField, $frontmatter)) {
                        continue;
                    }
                    if ($filterValue !== null && $filterValue !== '' && (string) ($frontmatter[$filterField] ?? '') !== $filterValue) {
                        continue;
                    }
                }

                $results[] = [
                    'path' => $filePath,
                    'frontmatter' => $frontmatter,
                ];
            }

            return ToolResult::success([
                'vault_id' => $vault->id,
                'path' => $path,
                'results' => $results,
                'count' => count($results),
                'scanned' => count($filePaths),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['obsidian', 'files', 'frontmatter', 'metadata', 'yaml', 'tags'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
