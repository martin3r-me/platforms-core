<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;

class ListVaultsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'obsidian.vaults.GET';
    }

    public function getDescription(): string
    {
        return 'GET /obsidian/vaults - Listet alle Obsidian Vaults des aktuellen Users auf. Jeder Vault ist eine S3-kompatible Verbindung zu einem Obsidian-Vault.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $vaults = ObsidianVault::where('user_id', $context->user->id)
                ->orderBy('name')
                ->get()
                ->map(fn (ObsidianVault $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'slug' => $v->slug,
                    'driver' => $v->driver,
                    'region' => $v->region,
                    'prefix' => $v->prefix,
                    'created_at' => $v->created_at?->toIso8601String(),
                ])
                ->all();

            return ToolResult::success([
                'vaults' => $vaults,
                'count' => count($vaults),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['obsidian', 'vault', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
