<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class BulkMoveFilesTool implements ToolContract, ToolMetadataContract
{
    private const MAX_FILES = 5000;
    private const MIN_PREFIX_LENGTH = 3;

    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.BULK_MOVE';
    }

    public function getDescription(): string
    {
        return 'BULK_MOVE /obsidian/files - Verschiebt alle Dateien unter from_prefix in den entsprechenden Pfad unter to_prefix (Prefix-Replace). Erfordert confirm=true. Mit dry_run=true werden nur die geplanten Verschiebungen zurückgegeben. Existierende Ziele werden uebersprungen, nicht ueberschrieben.';
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
                'from_prefix' => [
                    'type' => 'string',
                    'description' => 'Quellpfad. Alle Dateien darunter werden verschoben. Mindestens 3 Zeichen.',
                ],
                'to_prefix' => [
                    'type' => 'string',
                    'description' => 'Zielpfad. Die Substruktur unter from_prefix wird unter to_prefix nachgebildet.',
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, wenn dry_run nicht gesetzt ist.',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true, wird nichts verschoben — gibt nur die geplanten Verschiebungen zurueck.',
                ],
            ],
            'required' => ['vault_id', 'from_prefix', 'to_prefix'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            if (! $dryRun && empty($arguments['confirm'])) {
                return ToolResult::error('Verschiebung muss mit confirm=true bestaetigt werden (oder dry_run=true zum Testen).', 'CONFIRMATION_REQUIRED');
            }

            $vault = ObsidianVault::where('id', (int) ($arguments['vault_id'] ?? 0))
                ->where('user_id', $context->user->id)
                ->first();

            if (! $vault) {
                return ToolResult::error('Vault nicht gefunden.', 'NOT_FOUND');
            }

            $fromPrefix = trim((string) ($arguments['from_prefix'] ?? ''));
            $toPrefix = trim((string) ($arguments['to_prefix'] ?? ''));

            if ($fromPrefix === '' || $toPrefix === '') {
                return ToolResult::error('from_prefix und to_prefix sind erforderlich.', 'VALIDATION_ERROR');
            }

            if (strlen(trim($fromPrefix, '/')) < self::MIN_PREFIX_LENGTH) {
                return ToolResult::error('from_prefix muss mindestens ' . self::MIN_PREFIX_LENGTH . ' Zeichen lang sein.', 'VALIDATION_ERROR');
            }

            $normalizedFrom = trim($fromPrefix, '/');
            $normalizedTo = trim($toPrefix, '/');

            if ($normalizedFrom === $normalizedTo) {
                return ToolResult::error('from_prefix und to_prefix sind identisch.', 'VALIDATION_ERROR');
            }

            if (str_starts_with($normalizedTo . '/', $normalizedFrom . '/')) {
                return ToolResult::error('to_prefix darf nicht innerhalb von from_prefix liegen (verursacht Zyklen).', 'VALIDATION_ERROR');
            }

            $paths = $this->storage->listByPrefix($vault, $fromPrefix);

            if (count($paths) > self::MAX_FILES) {
                return ToolResult::error(
                    'from_prefix enthaelt ' . count($paths) . ' Dateien — ueber dem Limit von ' . self::MAX_FILES . '. Bitte in Teil-Praefixen verschieben.',
                    'VALIDATION_ERROR'
                );
            }

            if ($dryRun) {
                $planned = [];
                foreach ($paths as $p) {
                    $oldNormalized = trim($p, '/');
                    if ($oldNormalized === $normalizedFrom) {
                        $tail = '';
                    } elseif (str_starts_with($oldNormalized, $normalizedFrom . '/')) {
                        $tail = substr($oldNormalized, strlen($normalizedFrom));
                    } else {
                        continue;
                    }
                    $planned[] = ['from' => $p, 'to' => $normalizedTo . $tail];
                }

                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'from_prefix' => $fromPrefix,
                    'to_prefix' => $toPrefix,
                    'dry_run' => true,
                    'planned' => $planned,
                    'total' => count($planned),
                    'message' => 'Dry-Run: ' . count($planned) . ' Datei(en) wuerden verschoben.',
                ]);
            }

            if (empty($paths)) {
                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'from_prefix' => $fromPrefix,
                    'to_prefix' => $toPrefix,
                    'total_matched' => 0,
                    'total_moved' => 0,
                    'total_failed' => 0,
                    'moved' => [],
                    'failed' => [],
                    'message' => 'Keine Dateien unter from_prefix gefunden.',
                ]);
            }

            $result = $this->storage->moveByPrefix($vault, $fromPrefix, $toPrefix);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'from_prefix' => $fromPrefix,
                'to_prefix' => $toPrefix,
                'total_matched' => count($paths),
                'total_moved' => count($result['moved']),
                'total_failed' => count($result['failed']),
                'moved' => $result['moved'],
                'failed' => $result['failed'],
                'message' => count($result['moved']) . ' von ' . count($paths) . ' Datei(en) verschoben'
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
            'tags' => ['obsidian', 'files', 'bulk', 'move', 'rename'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'moderate',
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}
