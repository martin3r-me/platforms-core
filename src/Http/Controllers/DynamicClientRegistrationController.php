<?php

namespace Platform\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

/**
 * Controller für OAuth 2.0 Dynamic Client Registration (RFC 7591)
 *
 * Ermöglicht MCP Clients (Claude.ai, Claude Desktop, etc.) sich automatisch
 * zu registrieren ohne manuellen Admin-Eingriff.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7591
 */
class DynamicClientRegistrationController extends Controller
{
    /**
     * Erlaubte Redirect URI Patterns
     */
    protected array $allowedRedirectPatterns = [
        // Claude.ai Web Connector
        'https://claude.ai/*',
        // Lokale Callbacks (für CLI Tools)
        'http://127.0.0.1*',
        'http://localhost*',
        // Claude Desktop
        'claude://callback',
    ];

    /**
     * Handle Dynamic Client Registration Request
     *
     * POST /mcp/oauth/register
     */
    public function register(Request $request, ClientRepository $clientRepository): JsonResponse
    {
        Log::info('Dynamic Client Registration Request', [
            'client_name' => $request->input('client_name'),
            'redirect_uris' => $request->input('redirect_uris'),
            'token_endpoint_auth_method' => $request->input('token_endpoint_auth_method'),
            'grant_types' => $request->input('grant_types'),
        ]);

        // Validierung
        $clientName = $request->input('client_name', 'MCP Client');
        $redirectUris = $request->input('redirect_uris', []);
        $tokenEndpointAuthMethod = $request->input('token_endpoint_auth_method', 'none');

        // Redirect URIs validieren
        if (empty($redirectUris)) {
            $redirectUris = ['http://127.0.0.1'];
        }

        // Sicherstellen dass redirect_uris ein Array ist
        if (is_string($redirectUris)) {
            $redirectUris = [$redirectUris];
        }

        // Redirect URIs auf erlaubte Patterns prüfen
        foreach ($redirectUris as $uri) {
            if (!$this->isRedirectUriAllowed($uri)) {
                Log::warning('Dynamic Client Registration: Redirect URI not allowed', [
                    'uri' => $uri,
                ]);

                return response()->json([
                    'error' => 'invalid_redirect_uri',
                    'error_description' => "Redirect URI not allowed: {$uri}",
                ], 400);
            }
        }

        // Public Client wenn token_endpoint_auth_method = 'none'
        // Das ist der Standard für MCP Clients (verwenden PKCE)
        $isConfidential = $tokenEndpointAuthMethod !== 'none';

        try {
            // Client erstellen
            $client = $clientRepository->createAuthorizationCodeGrantClient(
                name: $clientName,
                redirectUris: $redirectUris,
                confidential: $isConfidential,
            );

            Log::info('Dynamic Client Registration successful', [
                'client_id' => $client->id,
                'client_name' => $clientName,
                'is_public' => !$isConfidential,
            ]);

            // Response gemäß RFC 7591
            $response = [
                'client_id' => $client->id,
                'client_name' => $clientName,
                'redirect_uris' => $redirectUris,
                'token_endpoint_auth_method' => $isConfidential ? 'client_secret_post' : 'none',
                'grant_types' => ['authorization_code', 'refresh_token'],
                'response_types' => ['code'],
                'client_id_issued_at' => now()->timestamp,
            ];

            // Nur für Confidential Clients: Secret zurückgeben
            if ($isConfidential && $client->plainSecret) {
                $response['client_secret'] = $client->plainSecret;
                $response['client_secret_expires_at'] = 0; // Läuft nicht ab
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            Log::error('Dynamic Client Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => 'Could not create client',
            ], 500);
        }
    }

    /**
     * Prüft ob eine Redirect URI erlaubt ist
     */
    protected function isRedirectUriAllowed(string $uri): bool
    {
        foreach ($this->allowedRedirectPatterns as $pattern) {
            if ($this->matchPattern($uri, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Einfacher Pattern-Match mit * Wildcard
     */
    protected function matchPattern(string $uri, string $pattern): bool
    {
        // Pattern in Regex konvertieren
        $regex = '/^' . str_replace(
            ['*', '/'],
            ['.*', '\/'],
            $pattern
        ) . '$/';

        return (bool) preg_match($regex, $uri);
    }
}
