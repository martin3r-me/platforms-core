<?php

use Platform\Core\Http\Controllers\DynamicClientRegistrationController;
use Platform\Core\Mcp\Servers\DefaultMcpServer;
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

// MCP SSE Endpoint - neue Laravel MCP API
// Mcp::web() registriert automatisch GET (405) und POST mit korrektem Transport
Mcp::web('sse', DefaultMcpServer::class)
    ->middleware('auth:api')
    ->name('mcp.sse');

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
            'default' => [
                'name' => 'Platform MCP Server',
                'version' => '1.0.0',
                'url' => $baseUrl . '/mcp/sse',
                'description' => 'Platform MCP Server',
                'authentication' => 'OAuth 2.0 / Bearer Token',
            ],
        ],
        'instructions' => [
            'claude_desktop' => 'Nutze URL: ' . $baseUrl . '/mcp/sse mit OAuth Client ID',
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

// Debug Endpoint - testet MCP Server Klasse direkt
Route::get('debug/mcp-server', function () {
    // Simuliere einen Transport für den Test
    $transport = new class implements \Laravel\Mcp\Server\Contracts\Transport {
        public function listen(): void {}
        public function receive(): mixed { return null; }
        public function send(mixed $message): void {}
        public function close(): void {}
    };

    try {
        $server = new \Platform\Core\Mcp\Servers\DefaultMcpServer($transport);
        $server->boot();

        // Versuche die Tools zu bekommen (Reflection da protected)
        $reflection = new \ReflectionClass($server);

        // Suche nach tools Property
        $toolsProperty = null;
        $current = $reflection;
        while ($current) {
            if ($current->hasProperty('tools')) {
                $toolsProperty = $current->getProperty('tools');
                break;
            }
            $current = $current->getParentClass();
        }

        $tools = [];
        if ($toolsProperty) {
            $toolsProperty->setAccessible(true);
            $tools = $toolsProperty->getValue($server);
        }

        return response()->json([
            'server_class' => get_class($server),
            'server_name' => $server->name ?? 'unknown',
            'boot_called' => true,
            'tools_count' => is_array($tools) ? count($tools) : 0,
            'tools' => is_array($tools) ? array_map(fn($t) => [
                'class' => get_class($t),
                'name' => method_exists($t, 'name') ? $t->name() : 'unknown',
            ], array_slice($tools, 0, 10)) : [],
            'reflection_classes' => array_map(fn($c) => $c->getName(), class_parents($server) ?: []),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
})->name('mcp.debug.server');
