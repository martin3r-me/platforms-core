<?php

namespace Platform\Core\Mcp\Servers;

use Laravel\Mcp\Server\Contracts\Transport;

/**
 * Default MCP Server
 *
 * Standard-Implementation des PlatformMcpServer für alle Instanzen.
 * Lädt automatisch alle ToolContract Tools aus der ToolRegistry.
 */
class DefaultMcpServer extends PlatformMcpServer
{
    protected string $name = 'Platform MCP Server';
    protected string $version = '1.0.0';
    protected string $instructions = 'Platform MCP Server. Bietet Zugriff auf alle registrierten Tools der Platform.';

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);
    }
}
