<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Einfaches Test-Tool für Entwicklung und Tests
 * 
 * Gibt einfach die übergebenen Argumente zurück - perfekt zum Testen der Basis-Architektur
 */
class EchoTool implements ToolContract
{
    public function getName(): string
    {
        return 'echo';
    }

    public function getDescription(): string
    {
        return 'Echo-Tool für Tests. Gibt die übergebenen Argumente zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'Nachricht die zurückgegeben werden soll'
                ],
                'number' => [
                    'type' => 'integer',
                    'description' => 'Optional: Eine Zahl'
                ]
            ],
            'required' => ['message']
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::success([
            'echo' => $arguments['message'] ?? '',
            'number' => $arguments['number'] ?? null,
            'user_id' => $context->user->id ?? null,
        ]);
    }
}

