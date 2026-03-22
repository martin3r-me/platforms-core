<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;

class CreateVaultTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'obsidian.vaults.POST';
    }

    public function getDescription(): string
    {
        return 'POST /obsidian/vaults - Erstellt einen neuen Obsidian Vault. Benötigt S3-kompatible Credentials (bucket, access_key, secret_key). Unterstützt S3, R2, MinIO, Wasabi etc.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Vaults (z.B. "Work", "Personal").',
                ],
                'driver' => [
                    'type' => 'string',
                    'description' => 'Storage-Driver. Standard: "s3". Unterstützt: s3, r2, minio, wasabi.',
                ],
                'bucket' => [
                    'type' => 'string',
                    'description' => 'S3 Bucket-Name.',
                ],
                'region' => [
                    'type' => 'string',
                    'description' => 'AWS Region (z.B. "eu-central-1"). Optional bei custom endpoints.',
                ],
                'endpoint' => [
                    'type' => 'string',
                    'description' => 'Custom S3-Endpoint URL (für R2, MinIO, Wasabi etc.).',
                ],
                'access_key' => [
                    'type' => 'string',
                    'description' => 'S3 Access Key ID.',
                ],
                'secret_key' => [
                    'type' => 'string',
                    'description' => 'S3 Secret Access Key.',
                ],
                'prefix' => [
                    'type' => 'string',
                    'description' => 'Optionaler Subfolder-Prefix im Bucket.',
                ],
            ],
            'required' => ['name', 'bucket', 'access_key', 'secret_key'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('name ist erforderlich.', 'VALIDATION_ERROR');
            }

            $bucket = trim((string) ($arguments['bucket'] ?? ''));
            if ($bucket === '') {
                return ToolResult::error('bucket ist erforderlich.', 'VALIDATION_ERROR');
            }

            $accessKey = trim((string) ($arguments['access_key'] ?? ''));
            $secretKey = trim((string) ($arguments['secret_key'] ?? ''));
            if ($accessKey === '' || $secretKey === '') {
                return ToolResult::error('access_key und secret_key sind erforderlich.', 'VALIDATION_ERROR');
            }

            $vault = new ObsidianVault([
                'name' => $name,
                'driver' => trim((string) ($arguments['driver'] ?? 's3')) ?: 's3',
                'bucket' => $bucket,
                'region' => !empty($arguments['region']) ? trim((string) $arguments['region']) : null,
                'endpoint' => !empty($arguments['endpoint']) ? trim((string) $arguments['endpoint']) : null,
                'access_key' => $accessKey,
                'secret_key' => $secretKey,
                'prefix' => !empty($arguments['prefix']) ? trim((string) $arguments['prefix']) : null,
            ]);
            $vault->user_id = $context->user->id;
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
                'message' => "Vault \"{$vault->name}\" erfolgreich erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['obsidian', 'vault', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
