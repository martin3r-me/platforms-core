<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class BatchReadTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.BATCH_READ';
    }

    public function getDescription(): string
    {
        return 'BATCH_READ /obsidian/files - Liest mehrere Dateien auf einmal. Maximal 20 Dateien pro Aufruf. Gibt Inhalt und Größe pro Datei zurück (oder Fehler falls nicht lesbar).';
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
                'paths' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array von Dateipfaden (max. 20). Z.B. ["daily/2026-03-22.md", "projects/venture.md"].',
                ],
            ],
            'required' => ['vault_id', 'paths'],
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

            $paths = $arguments['paths'] ?? [];
            if (! is_array($paths) || empty($paths)) {
                return ToolResult::error('paths muss ein nicht-leeres Array sein.', 'VALIDATION_ERROR');
            }

            if (count($paths) > 20) {
                return ToolResult::error('Maximal 20 Dateien pro Aufruf.', 'VALIDATION_ERROR');
            }

            $results = $this->storage->readMultiple($vault, $paths);

            $successCount = count(array_filter($results, fn ($r) => ! isset($r['error'])));

            return ToolResult::success([
                'vault_id' => $vault->id,
                'files' => $results,
                'total' => count($results),
                'success' => $successCount,
                'errors' => count($results) - $successCount,
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
            'tags' => ['obsidian', 'files', 'batch', 'read', 'bulk'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
