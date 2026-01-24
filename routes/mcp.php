<?php

use Laravel\Mcp\Server\Facades\Mcp;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MCP (Model Context Protocol) Routes
|--------------------------------------------------------------------------
|
| Diese Routes werden für MCP Server verwendet.
| Externe Clients (ChatGPT, Claude Desktop) können sich hier verbinden.
|
| Die Server-Konfiguration kommt aus config/mcp.php
|
*/

$mcpConfig = config('mcp');
$serverConfig = $mcpConfig['servers']['default'] ?? null;
$prefix = $mcpConfig['routes']['prefix'] ?? 'mcp';
$authMiddleware = $mcpConfig['routes']['auth_middleware'] ?? 'auth:sanctum';

// Nur registrieren, wenn Server-Klasse konfiguriert ist
if ($serverConfig && isset($serverConfig['class']) && class_exists($serverConfig['class'])) {
    // Server-Name für Route-Registrierung (kann von Config-Name abweichen)
    $serverNameKey = basename(str_replace('\\', '/', $serverConfig['class']));
    $serverNameKey = str_replace('Server', '', $serverNameKey);
    $serverNameKey = strtolower($serverNameKey);
    $serverName = $serverConfig['name'] ?? 'MCP Server';
    $serverClass = $serverConfig['class'];
    
    // MCP Server Route mit Authentifizierung
    Mcp::web($serverNameKey, $serverClass)
        ->middleware($authMiddleware);
    
    // Local Server für ChatGPT Desktop (STDIO)
    Mcp::local($serverNameKey, $serverClass);
    
    // Discovery/Info Route - für einfaches Kopieren
    Route::get('info', function () use ($serverConfig, $serverName, $serverNameKey, $prefix) {
        $baseUrl = config('app.url');
        $artisanPath = base_path('artisan');
        
        return response()->json([
            'servers' => [
                $serverNameKey => [
                    'name' => $serverConfig['name'] ?? 'MCP Server',
                    'version' => $serverConfig['version'] ?? '1.0.0',
                    'url' => $baseUrl . '/' . $prefix . '/' . $serverNameKey,
                    'description' => $serverConfig['description'] ?? 'MCP Server',
                    'authentication' => 'Bearer Token (Sanctum)',
                    'chatgpt_desktop' => [
                        'command' => 'php',
                        'args' => [
                            $artisanPath,
                            'mcp:start',
                            $serverNameKey
                        ],
                    ],
                    'web_config' => [
                        'url' => $baseUrl . '/' . $prefix . '/' . $serverNameKey,
                        'headers' => [
                            'Authorization' => 'Bearer YOUR_TOKEN_HERE',
                            'Content-Type' => 'application/json'
                        ]
                    ],
                    'cursor_ide' => [
                        'mcpServers' => [
                            $serverNameKey => [
                                'command' => 'php',
                                'args' => [
                                    $artisanPath,
                                    'mcp:start',
                                    $serverNameKey
                                ]
                            ]
                        ]
                    ],
                ]
            ],
            'instructions' => [
                'chatgpt_desktop' => 'Füge die "chatgpt_desktop" Konfiguration in deine ChatGPT Desktop App ein (Settings → Features → Model Context Protocol)',
                'web_client' => 'Nutze die "web_config" für HTTP-basierte Clients. Ersetze YOUR_TOKEN_HERE mit deinem Sanctum Token.',
                'cursor_ide' => 'Füge die "cursor_ide" Konfiguration in Cursor IDE ein (Settings → Features → Model Context Protocol). Cursor verwendet STDIO (wie ChatGPT Desktop), daher wird kein Token benötigt.',
                'get_token' => 'Erstelle einen Token mit: php artisan api:token:create --email=your@email.com --name="MCP Token" --show'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    })->name('mcp.info');
    
    // HTML Setup-Seite für Copy-Paste
    Route::get('setup', function () use ($serverConfig, $serverName, $serverNameKey, $prefix) {
        $baseUrl = config('app.url');
        $serverUrl = $baseUrl . '/' . $prefix . '/' . $serverNameKey;
        $artisanPath = base_path('artisan');
        
        return response()->view('platform::mcp.setup', [
            'serverName' => $serverConfig['name'] ?? 'MCP Server',
            'serverUrl' => $serverUrl,
            'baseUrl' => $baseUrl,
            'artisanPath' => $artisanPath,
            'serverNameKey' => $serverNameKey, // Für ChatGPT Desktop Config
        ]);
    })->name('mcp.setup');
    
    // OpenAPI Schema für Custom GPTs (Actions)
    Route::get('openapi.json', [\Platform\Core\Http\Controllers\McpController::class, 'schema'])
        ->name('mcp.openapi');
    
    // Proxy-Endpoint für Custom GPT Actions
    Route::post('tools/{toolName}', [\Platform\Core\Http\Controllers\McpController::class, 'proxy'])
        ->middleware($authMiddleware)
        ->name('mcp.tools.proxy');
}
