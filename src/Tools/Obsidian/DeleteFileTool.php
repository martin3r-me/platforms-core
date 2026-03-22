<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class DeleteFileTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /obsidian/files - Löscht eine Datei oder einen Ordner im Vault. Erfordert confirm=true.';
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
                'path' => [
                    'type' => 'string',
                    'description' => 'Pfad der Datei oder des Ordners.',
                ],
                'type' => [
                    'type' => 'string',
                    'description' => '"file" (Standard) oder "directory".',
                    'enum' => ['file', 'directory'],
                ],
                'confirm' => [
                    'type' => 'boolean',
                    'description' => 'Muss true sein.',
                ],
            ],
            'required' => ['vault_id', 'path', 'confirm'],
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

            $path = (string) ($arguments['path'] ?? '');
            if (trim($path) === '') {
                return ToolResult::error('path ist erforderlich.', 'VALIDATION_ERROR');
            }

            $type = (string) ($arguments['type'] ?? 'file');

            if ($type === 'directory') {
                $this->storage->deleteFolder($vault, $path);
            } else {
                $this->storage->deleteFile($vault, $path);
            }

            return ToolResult::success([
                'vault_id' => $vault->id,
                'path' => $path,
                'type' => $type,
                'deleted' => true,
                'message' => ucfirst($type) . " gelöscht: {$path}",
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
            'tags' => ['obsidian', 'files', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
            'confirmation_required' => true,
        ];
    }
}
