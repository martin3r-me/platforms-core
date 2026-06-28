<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class BulkDeleteFilesTool implements ToolContract, ToolMetadataContract
{
    private const MAX_PATHS = 1000;

    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.BULK_DELETE';
    }

    public function getDescription(): string
    {
        return 'BULK_DELETE /obsidian/files - Löscht mehrere Dateien auf einmal. Maximal 1000 Pfade pro Aufruf. Erfordert confirm=true. Mit dry_run=true werden die Pfade nur zurückgegeben, ohne zu löschen.';
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
                    'description' => 'Array von Dateipfaden (max. 1000). Nur Dateien, keine Ordner.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, wenn dry_run nicht gesetzt ist.',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true, wird nichts gelöscht — gibt nur die Pfade zurück, die gelöscht würden.',
                ],
            ],
            'required' => ['vault_id', 'paths'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            if (! $dryRun && empty($arguments['confirm'])) {
                return ToolResult::error('Löschung muss mit confirm=true bestätigt werden (oder dry_run=true zum Testen).', 'CONFIRMATION_REQUIRED');
            }

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

            $paths = array_values(array_unique(array_filter(
                $paths,
                fn ($p) => is_string($p) && trim($p) !== ''
            )));

            if (count($paths) > self::MAX_PATHS) {
                return ToolResult::error('Maximal ' . self::MAX_PATHS . ' Pfade pro Aufruf.', 'VALIDATION_ERROR');
            }

            if ($dryRun) {
                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'dry_run' => true,
                    'paths' => $paths,
                    'total' => count($paths),
                    'message' => 'Dry-Run: ' . count($paths) . ' Datei(en) würden gelöscht.',
                ]);
            }

            $result = $this->storage->deleteMany($vault, $paths);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'total_requested' => count($paths),
                'total_deleted' => count($result['deleted']),
                'total_failed' => count($result['failed']),
                'message' => count($result['deleted']) . ' Datei(en) gelöscht'
                    . (count($result['failed']) > 0 ? ', ' . count($result['failed']) . ' Fehler' : '')
                    . '.',
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
            'category' => 'action',
            'tags' => ['obsidian', 'files', 'bulk', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}
