<?php

namespace Platform\Core\Mcp\Adapters;

use Platform\Core\Mcp\Tools\ToolDiscoveryToolContract;
use Platform\Core\Contracts\ToolContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolResult as McpToolResult;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Adapter für ToolDiscoveryToolContract
 *
 * Spezieller Adapter der Session-ID und Callback unterstützt.
 */
class ToolDiscoveryAdapter extends Tool
{
    public function __construct(
        private ToolDiscoveryToolContract $tool
    ) {
    }

    public function name(): string
    {
        // MCP-konformer Name mit Underscores
        return 'tools__GET';
    }

    public function description(): string
    {
        return $this->tool->getDescription();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()
                ->description('Required: Modul-Key. Beispiel: "planner" → aktiviert alle planner.* Tools. Nutze core__modules__GET, um gültige Module zu sehen.'),
        ];
    }

    public function handle(array $arguments): McpToolResult
    {
        try {
            $context = $this->createContext();
            $result = $this->tool->execute($arguments, $context);

            if (!$result->success) {
                return McpToolResult::error($result->error ?? 'Unknown error');
            }

            $json = json_encode($result->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return McpToolResult::text($json);

        } catch (\Throwable $e) {
            return McpToolResult::error('Fehler: ' . $e->getMessage());
        }
    }

    private function createContext(): ToolContext
    {
        $user = auth()->user();
        $team = null;

        if ($user && method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;
        }

        return ToolContext::create($user, $team);
    }
}
