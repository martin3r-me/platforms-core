<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class UpdateFileTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /obsidian/files - Überschreibt oder hängt Inhalt an eine bestehende Datei an. Nutze mode="append" zum Anhängen, Standard ist "overwrite".';
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
                    'description' => 'Dateipfad im Vault.',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Neuer Dateiinhalt (oder anzuhängender Text bei mode="append").',
                ],
                'mode' => [
                    'type' => 'string',
                    'description' => 'Schreibmodus: "overwrite" (Standard) oder "append".',
                    'enum' => ['overwrite', 'append'],
                ],
            ],
            'required' => ['vault_id', 'path', 'content'],
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

            $path = (string) ($arguments['path'] ?? '');
            if (trim($path) === '') {
                return ToolResult::error('path ist erforderlich.', 'VALIDATION_ERROR');
            }

            $content = (string) ($arguments['content'] ?? '');
            $mode = (string) ($arguments['mode'] ?? 'overwrite');

            if ($mode === 'append') {
                $existing = $this->storage->exists($vault, $path)
                    ? $this->storage->readFile($vault, $path)
                    : '';
                $content = $existing . $content;
            }

            $this->storage->writeFile($vault, $path, $content);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'path' => $path,
                'mode' => $mode,
                'size' => strlen($content),
                'message' => $mode === 'append'
                    ? "Inhalt an {$path} angehängt."
                    : "Datei überschrieben: {$path}",
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
            'tags' => ['obsidian', 'files', 'update', 'write', 'append'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
