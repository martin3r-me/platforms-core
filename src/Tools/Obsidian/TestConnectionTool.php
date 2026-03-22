<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class TestConnectionTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.vaults.TEST';
    }

    public function getDescription(): string
    {
        return 'TEST /obsidian/vaults - Testet die S3-Verbindung eines Vaults. Gibt true/false zurück.';
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

            $connected = $this->storage->testConnection($vault);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'vault_name' => $vault->name,
                'connected' => $connected,
                'message' => $connected
                    ? "Verbindung zu \"{$vault->name}\" erfolgreich."
                    : "Verbindung zu \"{$vault->name}\" fehlgeschlagen. Bitte Credentials und Endpoint prüfen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['obsidian', 'vault', 'test', 'connection'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
