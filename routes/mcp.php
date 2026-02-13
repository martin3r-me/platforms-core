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

// GET/POST-Endpoint für SSE (Server-Sent Events) - Cursor benötigt das
// Laravel MCP unterstützt SSE über POST mit Accept: text/event-stream Header
// Cursor sendet sowohl GET als auch POST, daher behandeln wir beide
Route::match(['GET', 'POST'], 'sse', function (\Illuminate\Http\Request $request) {
    // Authentifizierung wird über auth:api Middleware gehandhabt
    // auth:api unterstützt sowohl Sanctum als auch Passport (OAuth) automatisch
    // User sollte bereits durch Middleware authentifiziert sein
    if (!auth()->check()) {
        return response('Unauthorized', 401, [
            'Content-Type' => 'text/event-stream',
        ]);
    }

    // Prüfe, ob Cursor bereits einen Request-Body gesendet hat
    $requestBody = $request->getContent();

    // Wenn GET-Request oder kein Body vorhanden ist, erstelle MCP Initialize Request
    // Unterstütze alle Protokollversionen: 2025-06-18 (neueste), 2025-03-26, 2024-11-05
    if ($request->isMethod('GET') || empty($requestBody)) {
        // Generiere eine zufällige ID, um Konflikte zu vermeiden
        $requestId = random_int(1000, 9999);

        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18', // Neueste Version
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'cursor',
                    'version' => '1.0.0',
                ],
            ],
        ]);
    } else {
        // Cursor hat bereits einen Request gesendet (POST), verwende diesen
        $body = $requestBody;

        // Log für Debugging
        \Log::info('[MCP] Client Request Body empfangen', [
            'body' => $body,
            'method' => $request->method(),
            'headers' => [
                'accept' => $request->header('Accept'),
                'content-type' => $request->header('Content-Type'),
                'authorization' => $request->header('Authorization') ? 'present' : 'missing',
            ],
        ]);
    }

    // Erstelle neuen Request für Laravel MCP
    $postRequest = \Illuminate\Http\Request::create(
        $request->url(),
        'POST',
        [],
        $request->cookies->all(),
        $request->files->all(),
        $request->server->all(),
        $body
    );

    // Kopiere alle Header
    foreach ($request->headers->all() as $key => $value) {
        $postRequest->headers->set($key, is_array($value) ? $value[0] : $value);
    }

    // WICHTIG: Setze Accept Header für SSE
    $postRequest->headers->set('Accept', 'text/event-stream');
    $postRequest->headers->set('Content-Type', 'application/json');

    // Session-ID für MCP
    if (!$postRequest->headers->has('Mcp-Session-Id')) {
        $postRequest->headers->set('Mcp-Session-Id', bin2hex(random_bytes(16)));
    }

    // Setze Request im Container
    app()->instance('request', $postRequest);

    // Prüfe Request-Body auf Protokollversion und passe sie an, falls nötig
    try {
        $bodyData = json_decode($body, true);
        if (isset($bodyData['params']['protocolVersion'])) {
            $requestedVersion = $bodyData['params']['protocolVersion'];
            $supportedVersions = ['2025-06-18', '2025-03-26', '2024-11-05'];

            // Wenn angeforderte Version nicht unterstützt wird, verwende neueste unterstützte
            if (!in_array($requestedVersion, $supportedVersions, true)) {
                \Log::warning('[MCP] Unsupported protocol version requested', [
                    'requested' => $requestedVersion,
                    'supported' => $supportedVersions,
                    'using' => $supportedVersions[0],
                ]);

                // Ersetze durch unterstützte Version
                $bodyData['params']['protocolVersion'] = $supportedVersions[0];
                $body = json_encode($bodyData);
                $postRequest->server->set('CONTENT_LENGTH', strlen($body));
            }
        }
    } catch (\Throwable $e) {
        \Log::warning('[MCP] Failed to parse request body', [
            'error' => $e->getMessage(),
            'body' => substr($body, 0, 200),
        ]);
    }

    // Rufe MCP Server auf
    $server = new DefaultMcpServer();
    $transport = new \Laravel\Mcp\Server\Transport\HttpTransport($postRequest);

    try {
        $server->connect($transport);

        // Wenn Accept: text/event-stream, aktiviere SSE-Stream auch für JSON-Responses
        if ($postRequest->header('Accept') === 'text/event-stream') {
            // Führe Request aus und hole Response
            $response = $transport->run();

            // Wenn Response JSON ist, konvertiere zu SSE-Stream
            if ($response->headers->get('Content-Type') === 'application/json') {
                $jsonContent = $response->getContent();

                // Erstelle SSE-Stream, der die JSON-Response als Event sendet
                return response()->stream(function () use ($jsonContent) {
                    // Output Buffering deaktivieren
                    while (ob_get_level() > 0) {
                        @ob_end_flush();
                    }

                    // Sende JSON-Response als SSE-Event
                    echo 'data: ' . $jsonContent . "\n\n";
                    @flush();

                    // Keep-alive: Periodisch Kommentare senden, damit Verbindung offen bleibt
                    $keepAliveInterval = 30; // Sekunden
                    $lastKeepAlive = time();

                    while (true) {
                        if (connection_aborted()) {
                            break;
                        }

                        // Keep-alive alle 30 Sekunden
                        if (time() - $lastKeepAlive >= $keepAliveInterval) {
                            echo ": keep-alive\n\n";
                            @flush();
                            $lastKeepAlive = time();
                        }

                        usleep(100000); // 100ms
                    }
                }, 200, [
                    'Content-Type' => 'text/event-stream; charset=UTF-8',
                    'Cache-Control' => 'no-cache, no-transform',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                ]);
            }
        }

        return $transport->run();
    } catch (\Laravel\Mcp\Server\Exceptions\JsonRpcException $e) {
        // MCP-Fehler (z.B. Unsupported protocol version) als SSE-Event senden
        \Log::error('[MCP] JsonRpcException', [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'data' => $e->getData(),
        ]);

        $errorResponse = json_encode([
            'jsonrpc' => '2.0',
            'id' => $e->getRequestId(),
            'error' => [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => $e->getData(),
            ],
        ]);

        if ($postRequest->header('Accept') === 'text/event-stream') {
            return response()->stream(function () use ($errorResponse) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                echo 'data: ' . $errorResponse . "\n\n";
                @flush();
            }, 200, [
                'Content-Type' => 'text/event-stream; charset=UTF-8',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $e->getRequestId(),
            'error' => [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => $e->getData(),
            ],
        ], 200);
    } catch (\Throwable $e) {
        \Log::error('[MCP] Unexpected error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $errorResponse = json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32603,
                'message' => 'Internal error: ' . $e->getMessage(),
            ],
        ]);

        if ($postRequest->header('Accept') === 'text/event-stream') {
            return response()->stream(function () use ($errorResponse) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                echo 'data: ' . $errorResponse . "\n\n";
                @flush();
            }, 500, [
                'Content-Type' => 'text/event-stream; charset=UTF-8',
            ]);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32603,
                'message' => 'Internal error',
            ],
        ], 500);
    }
})->middleware('auth:api')->name('mcp.sse');

// OAuth-Routes für Claude Desktop (benötigt Laravel Passport)
Mcp::oauthRoutes('oauth');

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
                'authentication' => 'Bearer Token (Passport/Sanctum)',
                'chatgpt_desktop' => [
                    'command' => 'php',
                    'args' => [
                        $artisanPath,
                        'mcp:start',
                        'default',
                    ],
                ],
                'web_config' => [
                    'url' => $baseUrl . '/mcp/sse',
                    'headers' => [
                        'Authorization' => 'Bearer YOUR_TOKEN_HERE',
                        'Content-Type' => 'application/json',
                    ],
                ],
            ],
        ],
        'instructions' => [
            'chatgpt_desktop' => 'Füge die "chatgpt_desktop" Konfiguration in deine ChatGPT Desktop App ein (Settings → Features → Model Context Protocol)',
            'claude_desktop' => 'Füge die "claude_desktop" Konfiguration in deine Claude Desktop App ein (Settings → Developer → Model Context Protocol → Benutzerdefinierten Connector hinzufügen)',
            'web_client' => 'Nutze die "web_config" für HTTP-basierte Clients. Ersetze YOUR_TOKEN_HERE mit deinem API Token.',
            'get_token' => 'Erstelle einen Token mit: php artisan api:token:create --email=your@email.com --name="MCP Token" --show',
        ],
        'claude_desktop' => [
            'url' => $baseUrl . '/mcp/sse',
            'headers' => [
                'Authorization' => 'Bearer YOUR_TOKEN_HERE',
            ],
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

// OpenAPI Schema für Custom GPTs (Actions)
Route::get('openapi.json', function () {
    $server = new DefaultMcpServer();
    $baseUrl = config('app.url');

    // OpenAPI 3.1 Schema
    $openApi = [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'Platform MCP Server',
            'version' => '1.0.0',
            'description' => 'Platform MCP Server API für Custom GPTs',
        ],
        'servers' => [
            [
                'url' => $baseUrl,
                'description' => 'Platform MCP Server',
            ],
        ],
        'paths' => [],
        'components' => [
            'schemas' => new \stdClass(),
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
        ],
        'security' => [
            ['bearerAuth' => []],
        ],
    ];

    // Konvertiere MCP Tools zu OpenAPI Paths
    $server->boot();

    // Hole Tools via Reflection
    $reflection = new \ReflectionClass($server);
    $toolsProperty = $reflection->getProperty('registeredTools');
    $toolsProperty->setAccessible(true);
    $tools = $toolsProperty->getValue($server);

    // Tools auflösen
    $resolvedTools = collect($tools)
        ->map(fn ($toolClass) => is_string($toolClass) ? app($toolClass) : $toolClass)
        ->filter(fn ($tool) => $tool->shouldRegister());

    foreach ($resolvedTools as $tool) {
        $toolName = $tool->name();
        $toolSchema = $tool->toArray();

        // OpenAPI Operation
        $operation = [
            'operationId' => $toolName,
            'summary' => $toolSchema['description'] ?? $toolName,
            'description' => $toolSchema['description'] ?? '',
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => $toolSchema['inputSchema']['properties'] ?? [],
                            'required' => $toolSchema['inputSchema']['required'] ?? [],
                        ],
                    ],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'result' => [
                                        'type' => 'string',
                                        'description' => 'Tool execution result',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $openApi['paths']["/mcp/tools/{$toolName}"] = [
            'post' => $operation,
        ];
    }

    return response()->json($openApi, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->name('mcp.openapi');

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
                    'Accept' => 'text/event-stream',
                    'Content-Type' => 'application/json',
                ],
            ],
        ],
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
})->name('mcp.cursor-config');
