<?php

namespace Platform\Core\Tools\Obsidian;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\ObsidianVault;
use Platform\Core\Services\ObsidianStorageService;

class PatchFileTool implements ToolContract, ToolMetadataContract
{
    public function __construct(
        private ObsidianStorageService $storage
    ) {}

    public function getName(): string
    {
        return 'obsidian.files.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /obsidian/files - Gezieltes Suchen & Ersetzen in einer Datei. Ideal für Checkbox abhaken (- [ ] → - [x]), Werte updaten, Zeilen einfügen/entfernen. Unterstützt einzelne oder alle Treffer (replace_all). Auch Regex möglich.';
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
                'find' => [
                    'type' => 'string',
                    'description' => 'Text der gesucht wird. Exakter Match (oder Regex wenn regex=true).',
                ],
                'replace' => [
                    'type' => 'string',
                    'description' => 'Ersetzungstext. Leer lassen um den Fund zu löschen.',
                ],
                'replace_all' => [
                    'type' => 'boolean',
                    'description' => 'Alle Treffer ersetzen (Standard: false — nur erster Treffer).',
                ],
                'regex' => [
                    'type' => 'boolean',
                    'description' => 'find als Regex interpretieren (Standard: false). Regex-Delimiter wird automatisch gesetzt.',
                ],
            ],
            'required' => ['vault_id', 'path', 'find', 'replace'],
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

            if (! $this->storage->exists($vault, $path)) {
                return ToolResult::error("Datei nicht gefunden: {$path}", 'NOT_FOUND');
            }

            $find = (string) ($arguments['find'] ?? '');
            if ($find === '') {
                return ToolResult::error('find darf nicht leer sein.', 'VALIDATION_ERROR');
            }

            $replace = (string) ($arguments['replace'] ?? '');
            $replaceAll = (bool) ($arguments['replace_all'] ?? false);
            $useRegex = (bool) ($arguments['regex'] ?? false);

            $content = $this->storage->readFile($vault, $path);
            $originalContent = $content;

            if ($useRegex) {
                $pattern = '/' . str_replace('/', '\/', $find) . '/';
                if ($replaceAll) {
                    $content = preg_replace($pattern, $replace, $content, -1, $count);
                } else {
                    $content = preg_replace($pattern, $replace, $content, 1, $count);
                }

                if ($content === null) {
                    return ToolResult::error('Ungültiger Regex-Ausdruck.', 'VALIDATION_ERROR');
                }
            } else {
                if ($replaceAll) {
                    $count = substr_count($content, $find);
                    $content = str_replace($find, $replace, $content);
                } else {
                    $pos = strpos($content, $find);
                    if ($pos !== false) {
                        $content = substr_replace($content, $replace, $pos, strlen($find));
                        $count = 1;
                    } else {
                        $count = 0;
                    }
                }
            }

            if ($count === 0) {
                return ToolResult::error("Kein Treffer gefunden für: {$find}", 'NOT_FOUND');
            }

            if ($content === $originalContent) {
                return ToolResult::success([
                    'vault_id' => $vault->id,
                    'path' => $path,
                    'replacements' => 0,
                    'message' => 'Keine Änderung — find und replace sind identisch.',
                ]);
            }

            $this->storage->writeFile($vault, $path, $content);

            return ToolResult::success([
                'vault_id' => $vault->id,
                'path' => $path,
                'replacements' => $count,
                'size' => strlen($content),
                'message' => "{$count} Ersetzung(en) in {$path} durchgeführt.",
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
            'tags' => ['obsidian', 'files', 'patch', 'replace', 'checkbox', 'edit'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
