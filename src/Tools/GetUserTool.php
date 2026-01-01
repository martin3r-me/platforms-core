<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;

/**
 * Tool zum Abrufen von User-Informationen
 * 
 * MCP-Pattern: Das Sprachmodell kann diesen Tool nutzen, um User-Details zu erfahren.
 */
class GetUserTool implements ToolContract
{
    public function getName(): string
    {
        return 'core.user.GET';
    }

    public function getDescription(): string
    {
        return 'Gibt Informationen über den aktuellen User zurück. Nutze dieses Tool, wenn du Details über den User benötigst (Name, Email, ID, etc.).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTHENTICATION_REQUIRED', 'Kein User im Kontext gefunden.');
            }

            $user = $context->user;

            return ToolResult::success([
                'id' => $user->id,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abrufen der User-Informationen: ' . $e->getMessage());
        }
    }
}

