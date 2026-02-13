<?php

namespace Platform\Core\Mcp\Servers;

/**
 * Default MCP Server
 *
 * Standard-Implementation des PlatformMcpServer für alle Instanzen.
 * Lädt automatisch alle ToolContract Tools aus der ToolRegistry.
 */
class DefaultMcpServer extends PlatformMcpServer
{
    public string $name = 'Platform MCP Server';

    public string $version = '1.0.0';

    public string $instructions = 'Platform MCP Server. Bietet Zugriff auf alle registrierten Tools der Platform.';

    /**
     * Zusätzliche direkte MCP Tools (optional)
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    public array $additionalTools = [];

    /**
     * Resources (optional)
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    public array $resources = [];

    /**
     * Prompts (optional)
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    public array $prompts = [];
}
