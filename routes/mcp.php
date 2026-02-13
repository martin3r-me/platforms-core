<?php

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
