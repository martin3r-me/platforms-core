<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;

class DeleteVaultTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'obsidian.vaults.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /obsidian/vaults - Löscht einen Obsidian Vault aus der Datenbank. S3-Inhalte werden NICHT gelöscht. Erfordert confirm=true.';
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
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein, um das Löschen zu bestätigen.',
                ],
            ],
            'required' => ['vault_id', 'confirm'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['confirm'])) {
                return ToolResult::error('Löschung muss mit confirm=true bestätigt werden.', 'CONFIRMATION_REQUIRED');
            }

            $vault = ObsidianVault::where('id', (int) ($arguments['vault_id'] ?? 0))
                ->where('user_id', $context->user->id)
                ->first();

            if (! $vault) {
                return ToolResult::error('Vault nicht gefunden.', 'NOT_FOUND');
            }

            $name = $vault->name;
            $vault->delete();

            return ToolResult::success([
                'deleted' => true,
                'message' => "Vault \"{$name}\" gelöscht. S3-Inhalte wurden nicht verändert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['obsidian', 'vault', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}
