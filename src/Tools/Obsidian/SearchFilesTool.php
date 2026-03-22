<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class SearchFilesTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.SEARCH';
    }

    public function getDescription(): string
    {
        return 'SEARCH /obsidian/files - Durchsucht einen Vault nach Dateinamen und/oder Dateiinhalt. Gibt Treffer mit Kontext-Zeilen zurück. Ideal für "finde alle Notes wo X vorkommt".';
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
                'query' => [
                    'type' => 'string',
                    'description' => 'Suchbegriff im Dateiinhalt (case-insensitive). Z.B. "BANKETT.DIGITAL", "tag: venture".',
                ],
                'name_pattern' => [
                    'type' => 'string',
                    'description' => 'Glob-Pattern für Dateinamen. Z.B. "*.md", "2026-03-*", "meeting*".',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Startpfad für die Suche (relativ). Standard: "/" (gesamter Vault).',
                ],
            ],
            'required' => ['vault_id'],
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

            $query = isset($arguments['query']) ? trim((string) $arguments['query']) : null;
            $namePattern = isset($arguments['name_pattern']) ? trim((string) $arguments['name_pattern']) : null;
            $path = (string) ($arguments['path'] ?? '/');

            if ($query === null && $namePattern === null) {
                return ToolResult::error('Mindestens query oder name_pattern ist erforderlich.', 'VALIDATION_ERROR');
            }

            if ($query === '') {
                $query = null;
            }
            if ($namePattern === '') {
                $namePattern = null;
            }

            $results = $this->storage->search($vault, $query, $namePattern, $path);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'query' => $query,
                'name_pattern' => $namePattern,
                'path' => $path,
                'results' => $results,
                'count' => count($results),
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
            'tags' => ['obsidian', 'files', 'search', 'find', 'grep'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
