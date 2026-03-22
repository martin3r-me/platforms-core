<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class AppendFileTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.APPEND';
    }

    public function getDescription(): string
    {
        return 'APPEND /obsidian/files - Hängt Inhalt an eine Datei an. Optional: unter einer bestimmten Markdown-Überschrift einfügen (heading Parameter). Erstellt die Datei falls sie nicht existiert.';
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
                    'description' => 'Dateipfad im Vault (z.B. "daily/2026-03-22.md").',
                ],
                'content' => [
                    'type' => 'string',
                    'description' => 'Der anzuhängende Inhalt.',
                ],
                'heading' => [
                    'type' => 'string',
                    'description' => 'Optional: Überschrift unter der eingefügt werden soll (z.B. "Notes", "Action Items"). Wird vor der nächsten gleichwertigen Überschrift eingefügt. Ohne heading wird ans Dateiende angehängt.',
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
            $heading = isset($arguments['heading']) ? trim((string) $arguments['heading']) : null;

            if ($heading !== null && $heading !== '') {
                $newContent = $this->storage->insertUnderHeading($vault, $path, $heading, $content);

                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'path' => $path,
                    'heading' => $heading,
                    'size' => strlen($newContent),
                    'message' => "Inhalt unter \"{$heading}\" in {$path} eingefügt.",
                ]);
            }

            $newContent = $this->storage->appendFile($vault, $path, $content);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'path' => $path,
                'size' => strlen($newContent),
                'message' => "Inhalt an {$path} angehängt.",
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
            'tags' => ['obsidian', 'files', 'append', 'write', 'daily'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
