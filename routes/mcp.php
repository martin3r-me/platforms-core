<?php

use Platform\Core\Http\Controllers\DynamicClientRegistrationController;
use Platform\Core\Mcp\Servers\DefaultMcpServer;
use Platform\Core\Mcp\Servers\DiscoveryMcpServer;
use Laravel\Mcp\Facades\Mcp;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MCP (Model Context Protocol) Routes
|--------------------------------------------------------------------------
|
| Diese Routes werden für MCP Server verwendet.
| Externe Clients (ChatGPT, Claude Desktop, Cursor) können sich hier verbinden.
|
*/

// MCP SSE Endpoint - Discovery-basierter Server (empfohlen für Claude.ai)
// Lädt initial nur 5 Discovery-Tools, weitere Tools werden dynamisch nachgeladen
Mcp::web('sse', DiscoveryMcpServer::class)
    ->middleware('auth:api')
    ->name('mcp.sse');

// Alternativer Endpoint mit allen Tools (für Clients die Discovery nicht unterstützen)
Mcp::web('sse-full', DefaultMcpServer::class)
    ->middleware('auth:api')
    ->name('mcp.sse.full');

// OAuth-Routes für Claude Desktop (benötigt Laravel Passport)
Mcp::oauthRoutes('oauth');

// Dynamic Client Registration (RFC 7591) - für Claude.ai Web Connector
Route::post('oauth/register', [DynamicClientRegistrationController::class, 'register'])
    ->name('mcp.oauth.register');

// Überschreibe Discovery Routes mit korrekten URLs (inkl. /mcp Prefix)
Route::get('.well-known/oauth-authorization-server/{path?}', function () {
    $baseUrl = config('app.url');

    return response()->json([
        'issuer' => $baseUrl,
        'authorization_endpoint' => $baseUrl . '/oauth/authorize',
        'token_endpoint' => $baseUrl . '/oauth/token',
        'registration_endpoint' => $baseUrl . '/mcp/oauth/register',
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => ['mcp:use'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]);
})->name('mcp.oauth.authorization-server');

Route::get('.well-known/oauth-protected-resource/{path?}', function () {
    $baseUrl = config('app.url');

    return response()->json([
        'resource' => $baseUrl,
        'authorization_servers' => [$baseUrl],
        'scopes_supported' => ['mcp:use'],
    ]);
})->name('mcp.oauth.protected-resource');

// Zusätzliche Helper-Routes (ohne Middleware, da öffentlich zugänglich)
Route::get('info', function () {
    $baseUrl = config('app.url');
    $artisanPath = base_path('artisan');

    return response()->json([
        'servers' => [
            'discovery' => [
                'name' => 'Platform MCP Server (Discovery)',
                'version' => '1.0.0',
                'url' => $baseUrl . '/mcp/sse',
                'description' => 'Discovery-basierter Server. Lädt initial 5 Tools, weitere via tools.GET.',
                'authentication' => 'OAuth 2.0 / Bearer Token',
                'recommended_for' => 'Claude.ai, Claude Desktop',
                'initial_tools' => [
                    'core__user__GET',
                    'core__teams__GET',
                    'core__context__GET',
                    'core__modules__GET',
                    'tools__GET',
                ],
            ],
            'full' => [
                'name' => 'Platform MCP Server (Full)',
                'version' => '1.0.0',
                'url' => $baseUrl . '/mcp/sse-full',
                'description' => 'Lädt alle Tools sofort. Kann Context Window überlasten.',
                'authentication' => 'OAuth 2.0 / Bearer Token',
                'recommended_for' => 'Debugging, lokale Clients',
            ],
        ],
        'discovery_workflow' => [
            '1' => 'Verbinde mit /mcp/sse',
            '2' => 'Rufe core__modules__GET auf um Module zu sehen',
            '3' => 'Rufe tools__GET(module="...") auf um Tools zu aktivieren',
            '4' => 'Nutze die aktivierten Tools',
        ],
        'instructions' => [
            'claude_desktop' => 'Nutze URL: ' . $baseUrl . '/mcp/sse mit OAuth',
            'get_token' => 'Erstelle einen Token mit: php artisan api:token:create --email=your@email.com --name="MCP Token" --show',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->name('mcp.info');

Route::get('setup', function () {
    $baseUrl = config('app.url');
    $serverUrl = $baseUrl . '/mcp/sse';
    $artisanPath = base_path('artisan');

    return response()->view('platform::mcp.setup', [
        'serverName' => 'Platform MCP Server',
        'serverUrl' => $serverUrl,
        'baseUrl' => $baseUrl,
        'artisanPath' => $artisanPath,
        'serverNameKey' => 'default',
    ]);
})->name('mcp.setup');

// Cursor IDE Konfiguration (JSON)
Route::get('cursor-config.json', function () {
    $baseUrl = config('app.url');
    $serverUrl = $baseUrl . '/mcp/sse';

    return response()->json([
        'mcpServers' => [
            'platform' => [
                'url' => $serverUrl,
                'headers' => [
                    'Authorization' => 'Bearer YOUR_TOKEN_HERE',
                ],
            ],
        ],
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->name('mcp.cursor-config');

// Debug Endpoint - zeigt alle verfügbaren MCP Tools
Route::get('debug/tools', function () {
    $registry = app(\Platform\Core\Tools\ToolRegistry::class);

    // Tools laden falls nötig
    if (count($registry->all()) === 0) {
        $coreTools = \Platform\Core\Tools\ToolLoader::loadCoreTools();
        foreach ($coreTools as $tool) {
            if (!$registry->has($tool->getName())) {
                $registry->register($tool);
            }
        }

        // Module-Tools laden
        $modulesPath = realpath(__DIR__ . '/../../../modules');
        if ($modulesPath && is_dir($modulesPath)) {
            $moduleTools = \Platform\Core\Tools\ToolLoader::loadFromAllModules($modulesPath);
            foreach ($moduleTools as $tool) {
                if (!$registry->has($tool->getName())) {
                    $registry->register($tool);
                }
            }
        }
    }

    return response()->json([
        'tool_count' => count($registry->all()),
        'tools' => collect($registry->all())->map(fn($t) => [
            'name' => $t->getName(),
            'description' => substr($t->getDescription(), 0, 100),
        ])->values(),
    ]);
})->name('mcp.debug.tools');

// Debug Endpoint - testet ToolContractAdapter direkt
Route::get('debug/mcp-server', function () {
    try {
        $registry = app(\Platform\Core\Tools\ToolRegistry::class);

        // Tools laden falls nötig
        if (count($registry->all()) === 0) {
            $coreTools = \Platform\Core\Tools\ToolLoader::loadCoreTools();
            foreach ($coreTools as $tool) {
                if (!$registry->has($tool->getName())) {
                    $registry->register($tool);
                }
            }
        }

        // Teste ToolContractAdapter mit erstem Tool
        $allTools = $registry->all();
        $firstTool = reset($allTools);
        $adapterTest = null;

        if ($firstTool) {
            try {
                $adapter = new \Platform\Core\Mcp\Adapters\ToolContractAdapter($firstTool);
                $adapterTest = [
                    'name' => $adapter->name(),
                    'description' => substr($adapter->description(), 0, 100),
                    'adapter_class' => get_class($adapter),
                    'parent_class' => get_parent_class($adapter),
                ];
            } catch (\Throwable $e) {
                $adapterTest = ['error' => $e->getMessage()];
            }
        }

        // Prüfe Laravel MCP Server Klasse
        $serverClass = \Platform\Core\Mcp\Servers\DefaultMcpServer::class;
        $serverReflection = new \ReflectionClass($serverClass);
        $parentClasses = [];
        $current = $serverReflection;
        while ($parent = $current->getParentClass()) {
            $parentClasses[] = $parent->getName();
            $current = $parent;
        }

        return response()->json([
            'registry_tools_count' => count($allTools),
            'adapter_test' => $adapterTest,
            'server_class' => $serverClass,
            'server_parents' => $parentClasses,
            'laravel_mcp_server_exists' => class_exists(\Laravel\Mcp\Server::class),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
})->name('mcp.debug.server');
