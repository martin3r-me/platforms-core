<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;

class UpdateVaultTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'obsidian.vaults.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /obsidian/vaults - Aktualisiert einen bestehenden Obsidian Vault (Name, Credentials, Region, Endpoint, Prefix).';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'Neuer Name.',
                ],
                'driver' => [
                    'type' => 'string',
                    'description' => 'Neuer Driver (s3, r2, minio, wasabi).',
                ],
                'bucket' => [
                    'type' => 'string',
                    'description' => 'Neuer Bucket-Name.',
                ],
                'region' => [
                    'type' => 'string',
                    'description' => 'Neue Region.',
                ],
                'endpoint' => [
                    'type' => 'string',
                    'description' => 'Neuer Endpoint.',
                ],
                'access_key' => [
                    'type' => 'string',
                    'description' => 'Neuer Access Key.',
                ],
                'secret_key' => [
                    'type' => 'string',
                    'description' => 'Neuer Secret Key.',
                ],
                'prefix' => [
                    'type' => 'string',
                    'description' => 'Neuer Prefix.',
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

            $updatable = ['name', 'driver', 'bucket', 'region', 'endpoint', 'access_key', 'secret_key', 'prefix'];
            $changed = [];

            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $value = is_string($arguments[$field]) ? trim($arguments[$field]) : $arguments[$field];
                    $vault->{$field} = $value !== '' ? $value : null;
                    $changed[] = $field;
                }
            }

            if (empty($changed)) {
                return ToolResult::error('Keine Felder zum Aktualisieren angegeben.', 'VALIDATION_ERROR');
            }

            // Re-generate slug if name changed
            if (in_array('name', $changed) && $vault->name) {
                $vault->slug = \Illuminate\Support\Str::slug($vault->name);
            }

            $vault->save();

            return ToolResult::success([
                'vault' => [
                    'id' => $vault->id,
                    'name' => $vault->name,
                    'slug' => $vault->slug,
                    'driver' => $vault->driver,
                    'region' => $vault->region,
                    'prefix' => $vault->prefix,
                ],
                'changed' => $changed,
                'message' => "Vault \"{$vault->name}\" aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['obsidian', 'vault', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
