<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class WriteFileTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.POST';
    }

    public function getDescription(): string
    {
        return 'POST /obsidian/files - Erstellt eine neue Datei im Vault. Schlägt fehl, wenn die Datei bereits existiert (nutze obsidian.files.PUT zum Überschreiben).';
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
                    'description' => 'Dateipfad im Vault (relativ, z.B. "notes/new-note.md").',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Dateiinhalt (Markdown etc.).',
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

            if ($this->storage->exists($vault, $path)) {
                return ToolResult::error("Datei existiert bereits: {$path}. Nutze obsidian.files.PUT zum Überschreiben.", 'CONFLICT');
            }

            $this->storage->writeFile($vault, $path, $content);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'path' => $path,
                'size' => strlen($content),
                'message' => "Datei erstellt: {$path}",
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
            'tags' => ['obsidian', 'files', 'create', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
