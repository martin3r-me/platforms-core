<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class BulkDeleteByPatternTool implements ToolContract, ToolMetadataContract
{
    private const MAX_FILES = 5000;
    private const MIN_PATTERN_LENGTH = 3;

    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.BULK_DELETE_PATTERN';
    }

    public function getDescription(): string
    {
        return 'BULK_DELETE_PATTERN /obsidian/files - Löscht alle Dateien, die einem Glob-Pattern entsprechen. Erfordert confirm=true. Mit dry_run=true werden nur die Treffer zurückgegeben. Hartlimit: 5000 Treffer. Pure Wildcards (*, **) sind verboten.';
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
                'name_pattern' => [
                    'type' => 'string',
                    'description' => 'Glob-Pattern für Dateinamen oder -pfade. Z.B. "log_-*", "*.tmp", "2026-05-* 2.md". Mindestens 3 Zeichen, kein bloßes "*".',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Startpfad für die Suche (relativ). Standard: "/" (gesamter Vault).',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, wenn dry_run nicht gesetzt ist.',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true, wird nichts gelöscht — gibt nur die Treffer zurück.',
                ],
            ],
            'required' => ['vault_id', 'name_pattern'],
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

            $pattern = trim((string) ($arguments['name_pattern'] ?? ''));
            if ($pattern === '') {
                return ToolResult::error('name_pattern ist erforderlich.', 'VALIDATION_ERROR');
            }

            if (strlen($pattern) < self::MIN_PATTERN_LENGTH) {
                return ToolResult::error('name_pattern muss mindestens ' . self::MIN_PATTERN_LENGTH . ' Zeichen lang sein.', 'VALIDATION_ERROR');
            }

            if (in_array($pattern, ['*', '**', '*.*', '*/*'], true)) {
                return ToolResult::error('Wildcard-only Pattern sind verboten ("' . $pattern . '" matcht zu viel).', 'VALIDATION_ERROR');
            }

            $path = (string) ($arguments['path'] ?? '/');

            $matches = $this->storage->findByPattern($vault, $pattern, $path);

            if (count($matches) > self::MAX_FILES) {
                return ToolResult::error(
                    'Pattern matcht ' . count($matches) . ' Dateien — über dem Limit von ' . self::MAX_FILES . '. Pattern verengen oder spezifischen path angeben.',
                    'VALIDATION_ERROR'
                );
            }

            if ($dryRun) {
                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'name_pattern' => $pattern,
                    'path' => $path,
                    'dry_run' => true,
                    'matches' => $matches,
                    'total' => count($matches),
                    'message' => 'Dry-Run: ' . count($matches) . ' Datei(en) würden gelöscht.',
                ]);
            }

            if (empty($matches)) {
                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'name_pattern' => $pattern,
                    'path' => $path,
                    'matches' => [],
                    'total' => 0,
                    'deleted' => [],
                    'failed' => [],
                    'total_deleted' => 0,
                    'total_failed' => 0,
                    'message' => 'Keine Treffer — nichts gelöscht.',
                ]);
            }

            $result = $this->storage->deleteMany($vault, $matches);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'name_pattern' => $pattern,
                'path' => $path,
                'total_matched' => count($matches),
                'total_deleted' => count($result['deleted']),
                'total_failed' => count($result['failed']),
                'failed' => $result['failed'],
                'message' => count($result['deleted']) . ' von ' . count($matches) . ' Datei(en) gelöscht'
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
            'tags' => ['obsidian', 'files', 'bulk', 'delete', 'pattern', 'glob'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}
