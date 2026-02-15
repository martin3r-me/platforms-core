<?php

namespace Platform\Core\Mcp\Adapters;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Mcp\McpSessionTeamManager;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\Request;
use Laravel\Passport\Token;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * Adapter für ToolContract → Laravel MCP Tool
 *
 * Wrappt ein ToolContract Tool und macht es als MCP Tool verfügbar.
 * Konvertiert automatisch Schema, Context und Results.
 */
class ToolContractAdapter extends Tool
{
    public function __construct(
        private ToolContract $tool
    ) {
    }

    /**
     * Gibt den Namen des Tools zurück (MCP-konform mit Underscores statt Punkte)
     */
    public function name(): string
    {
        // MCP erfordert Pattern: ^[a-zA-Z0-9_-]{1,64}$
        // Ersetze Punkte durch doppelte Underscores
        return str_replace('.', '__', $this->tool->getName());
    }

    /**
     * Gibt die Beschreibung des Tools zurück
     */
    public function description(): string
    {
        return $this->tool->getDescription();
    }

    /**
     * Konvertiert JSON Schema zu Laravel JsonSchema Format
     */
    public function schema(JsonSchema $schema): array
    {
        $jsonSchema = $this->tool->getSchema();
        $properties = [];

        if (isset($jsonSchema['properties']) && is_array($jsonSchema['properties'])) {
            $required = $jsonSchema['required'] ?? [];

            foreach ($jsonSchema['properties'] as $propertyName => $property) {
                $type = $property['type'] ?? 'string';
                $description = $property['description'] ?? null;
                $isRequired = in_array($propertyName, $required);

                // Property basierend auf Typ erstellen
                $prop = match ($type) {
                    'integer' => $schema->integer(),
                    'number' => $schema->number(),
                    'boolean' => $schema->boolean(),
                    'array' => $schema->array(),
                    default => $schema->string(),
                };

                // Description hinzufügen falls vorhanden
                if ($description && method_exists($prop, 'description')) {
                    $prop = $prop->description($description);
                }

                // Required markieren falls nötig
                if (!$isRequired && method_exists($prop, 'nullable')) {
                    $prop = $prop->nullable();
                }

                $properties[$propertyName] = $prop;
            }
        }

        return $properties;
    }

    /**
     * Führt das Tool aus und konvertiert das Ergebnis
     */
    public function handle(Request $request): Response
    {
        try {
            // Arguments aus MCP Request extrahieren
            $arguments = $request->all();

            // JSON-Strings in Arrays/Objekte konvertieren (falls nötig)
            $arguments = $this->normalizeArguments($arguments);

            // Context aus Request extrahieren
            $context = $this->createContextFromRequest();

            // Tool ausführen
            $result = $this->tool->execute($arguments, $context);

            // ToolResult zu MCP Response konvertieren
            return $this->convertToolResult($result);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            return Response::error('Tool execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Normalisiert Argumente (konvertiert JSON-Strings zu Arrays/Objekten)
     */
    private function normalizeArguments(array $arguments): array
    {
        $normalized = [];

        foreach ($arguments as $key => $value) {
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $normalized[$key] = $decoded;
                } else {
                    $normalized[$key] = $value;
                }
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Erstellt ToolContext aus dem aktuellen Request
     */
    private function createContextFromRequest(): ToolContext
    {
        $user = auth()->user();

        if (!$user) {
            $token = env('MCP_AUTH_TOKEN') ?? $_ENV['MCP_AUTH_TOKEN'] ?? null;

            if ($token) {
                $user = $this->authenticateViaPassport($token);
                if ($user) {
                    auth()->setUser($user);
                }
            }
        }

        if (!$user) {
            $token = env('MCP_AUTH_TOKEN') ?? $_ENV['MCP_AUTH_TOKEN'] ?? getenv('MCP_AUTH_TOKEN');

            if (!$token) {
                throw new \RuntimeException('User must be authenticated to execute tools.');
            }

            throw new \RuntimeException('Invalid MCP_AUTH_TOKEN.');
        }

        $team = null;
        if (method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;
        }

        // Session-Team-Override prüfen (gesetzt durch core.team.switch)
        $sessionId = McpSessionTeamManager::resolveSessionId();
        if ($sessionId && McpSessionTeamManager::hasTeamOverride($sessionId)) {
            $overrideTeam = McpSessionTeamManager::getTeamOverride($sessionId);
            if ($overrideTeam) {
                $team = $overrideTeam;
            }
        }

        return ToolContext::create($user, $team);
    }

    /**
     * Authentifiziert User via Passport JWT Token
     */
    private function authenticateViaPassport(string $bearerToken)
    {
        try {
            $tokenId = $this->parseJwtTokenId($bearerToken);

            if (!$tokenId) {
                return null;
            }

            $token = Token::find($tokenId);

            if (!$token || $token->revoked) {
                return null;
            }

            if ($token->expires_at && $token->expires_at->isPast()) {
                return null;
            }

            $userModelClass = config('auth.providers.users.model');
            $user = $userModelClass::find($token->user_id);

            if ($user) {
                $user->withAccessToken($token);
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parst JWT Token und extrahiert die Token-ID (jti claim)
     */
    private function parseJwtTokenId(string $jwt): ?string
    {
        try {
            $parts = explode('.', $jwt);

            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload || !isset($payload['jti'])) {
                return null;
            }

            return $payload['jti'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Konvertiert ToolResult zu MCP Response
     */
    private function convertToolResult(ToolResult $result): Response
    {
        if (!$result->success) {
            $errorMessage = $result->error ?? 'Unknown error';
            if ($result->errorCode) {
                $errorMessage = "[{$result->errorCode}] {$errorMessage}";
            }
            return Response::error($errorMessage);
        }

        $data = $result->data;

        if (is_array($data) || is_object($data)) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return Response::text($json);
        }

        if (is_string($data)) {
            return Response::text($data);
        }

        return Response::text((string) $data);
    }

    /**
     * Gibt das gewrappte Tool zurück
     */
    public function getWrappedTool(): ToolContract
    {
        return $this->tool;
    }
}
