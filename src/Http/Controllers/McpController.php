<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Mcp\Server\Tools\TextContent;

/**
 * MCP Controller für OpenAPI Schema und Proxy-Endpoints
 * 
 * Generiert OpenAPI 3.1 Schema aus MCP Tools für ChatGPT Custom GPTs
 * und stellt Proxy-Endpoints für Tool-Aufrufe bereit.
 */
class McpController extends Controller
{
    /**
     * Lädt den konfigurierten MCP Server
     */
    private function getServer()
    {
        $mcpConfig = config('mcp');
        $serverConfig = $mcpConfig['servers']['default'] ?? null;
        
        if (!$serverConfig || !isset($serverConfig['class']) || !class_exists($serverConfig['class'])) {
            throw new \RuntimeException('MCP Server nicht konfiguriert. Bitte setze MCP_SERVER_CLASS in der .env oder config/mcp.php');
        }
        
        $serverClass = $serverConfig['class'];
        return new $serverClass();
    }
    
    /**
     * Generiert OpenAPI Schema für Custom GPT Actions
     */
    public function schema(): JsonResponse
    {
        $server = $this->getServer();
        $baseUrl = config('app.url');
        $mcpConfig = config('mcp');
        $openApiConfig = $mcpConfig['openapi'] ?? [];
        
        // OpenAPI 3.1 Schema
        $openApi = [
            'openapi' => $openApiConfig['version'] ?? '3.1.0',
            'info' => [
                'title' => $openApiConfig['title'] ?? 'Platform MCP Server',
                'version' => config('mcp.servers.default.version', '1.0.0'),
                'description' => $openApiConfig['description'] ?? 'Platform MCP Server API für Custom GPTs',
            ],
            'servers' => [
                [
                    'url' => $baseUrl,
                    'description' => config('mcp.servers.default.name', 'MCP Server'),
                ],
            ],
            'paths' => [],
            'components' => [
                'schemas' => new \stdClass(), // Muss ein Objekt sein, nicht Array
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
        // Server booten, um Tools zu laden
        $server->boot();
        
        // Hole Tools via Reflection (registeredTools ist protected)
        $reflection = new \ReflectionClass($server);
        $toolsProperty = $reflection->getProperty('registeredTools');
        $toolsProperty->setAccessible(true);
        $tools = $toolsProperty->getValue($server);
        
        // Tools auflösen (wie ServerContext::tools())
        $resolvedTools = collect($tools)
            ->map(fn($toolClass) => is_string($toolClass) ? app($toolClass) : $toolClass)
            ->filter(fn($tool) => $tool->shouldRegister());
        
        $prefix = config('mcp.routes.prefix', 'mcp');
        
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
                            'schema' => $this->convertMcpSchemaToOpenApi($toolSchema['inputSchema']),
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
            
            // Path: /mcp/tools/{toolName}
            $openApi['paths']["/{$prefix}/tools/{$toolName}"] = [
                'post' => $operation,
            ];
        }
        
        return response()->json($openApi, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Konvertiert MCP inputSchema zu OpenAPI Schema
     */
    private function convertMcpSchemaToOpenApi(array $mcpSchema): array
    {
        $openApiSchema = [
            'type' => 'object',
            'properties' => [],
            'required' => $mcpSchema['required'] ?? [],
        ];
        
        if (isset($mcpSchema['properties'])) {
            foreach ($mcpSchema['properties'] as $name => $property) {
                $openApiSchema['properties'][$name] = [
                    'type' => $property['type'] ?? 'string',
                    'description' => $property['description'] ?? '',
                ];
                
                // Zusätzliche Constraints
                if (isset($property['enum'])) {
                    $openApiSchema['properties'][$name]['enum'] = $property['enum'];
                }
            }
        }
        
        return $openApiSchema;
    }
    
    /**
     * Proxy-Endpoint für Custom GPT Actions
     * 
     * Custom GPTs senden Requests hierher, wir leiten sie an den MCP Server weiter
     */
    public function proxy(Request $request, string $toolName): JsonResponse
    {
        // Validiere Authentifizierung
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Bearer token required',
            ], 401);
        }
        
        // Authentifiziere User
        try {
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if (!$personalAccessToken) {
                // Debug: Prüfe Token-Format
                $tokenParts = explode('|', $token, 2);
                $tokenId = $tokenParts[0] ?? null;
                
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid token',
                    'debug' => [
                        'token_id' => $tokenId,
                        'token_length' => strlen($token),
                        'token_format' => count($tokenParts) === 2 ? 'valid' : 'invalid',
                    ],
                ], 401);
            }
            
            $user = $personalAccessToken->tokenable;
            
            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Token has no associated user',
                ], 401);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[McpController] Token validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token validation failed: ' . $e->getMessage(),
            ], 401);
        }
        
        // Setze User für Request
        auth()->setUser($user);
        
        // Hole Tool vom Server
        $server = $this->getServer();
        $server->boot();
        
        // Hole Tools via Reflection
        $reflection = new \ReflectionClass($server);
        $toolsProperty = $reflection->getProperty('registeredTools');
        $toolsProperty->setAccessible(true);
        $tools = $toolsProperty->getValue($server);
        
        // Tools auflösen (wie ServerContext::tools())
        $resolvedTools = collect($tools)
            ->map(fn($toolClass) => is_string($toolClass) ? app($toolClass) : $toolClass)
            ->filter(fn($tool) => $tool->shouldRegister());
        
        $tool = $resolvedTools->first(fn($t) => $t->name() === $toolName);
        
        if (!$tool) {
            return response()->json([
                'error' => 'Tool not found',
                'message' => "Tool '{$toolName}' not found",
            ], 404);
        }
        
        // Führe Tool aus
        try {
            $arguments = $request->json()->all();
            $result = $tool->handle($arguments);
            
            // Konvertiere ToolResult zu OpenAPI Response
            // ToolResult hat ein content-Array mit Content-Objekten
            $textContent = '';
            if (!empty($result->content)) {
                foreach ($result->content as $content) {
                    if ($content instanceof TextContent) {
                        $textContent .= $content->text;
                    }
                }
            }
            
            // Falls kein Text gefunden, verwende toArray()
            if (empty($textContent)) {
                $textContent = json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
            
            return response()->json([
                'result' => $textContent,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Tool execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
