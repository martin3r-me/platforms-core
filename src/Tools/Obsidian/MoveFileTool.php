<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class MoveFileTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.MOVE';
    }

    public function getDescription(): string
    {
        return 'MOVE /obsidian/files - Verschiebt oder benennt eine Datei im Vault um.';
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
                'from' => [
                    'type' => 'string',
                    'description' => 'Quellpfad der Datei.',
                ],
                'to' => [
                    'type' => 'string',
                    'description' => 'Zielpfad der Datei.',
                ],
            ],
            'required' => ['vault_id', 'from', 'to'],
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

            $from = (string) ($arguments['from'] ?? '');
            $to = (string) ($arguments['to'] ?? '');

            if (trim($from) === '' || trim($to) === '') {
                return ToolResult::error('from und to sind erforderlich.', 'VALIDATION_ERROR');
            }

            if (! $this->storage->exists($vault, $from)) {
                return ToolResult::error("Quelldatei nicht gefunden: {$from}", 'NOT_FOUND');
            }

            $this->storage->moveFile($vault, $from, $to);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'from' => $from,
                'to' => $to,
                'message' => "Datei verschoben: {$from} → {$to}",
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
            'tags' => ['obsidian', 'files', 'move', 'rename'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
