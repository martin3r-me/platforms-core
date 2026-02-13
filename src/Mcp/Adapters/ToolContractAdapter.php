<?php

namespace Platform\Core\Mcp\Adapters;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult as McpToolResult;
use Laravel\Mcp\Server\Tools\TextContent;
use Laravel\Passport\TokenRepository;
use Illuminate\Http\Request;

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
        // Parent-Konstruktor aufrufen
        parent::__construct();
    }

    /**
     * Gibt den Namen des Tools zurück
     */
    public function name(): string
    {
        return $this->tool->getName();
    }

    /**
     * Gibt die Beschreibung des Tools zurück
     */
    public function description(): string
    {
        return $this->tool->getDescription();
    }

    /**
     * Konvertiert JSON Schema zu ToolInputSchema
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $jsonSchema = $this->tool->getSchema();

        // JSON Schema zu ToolInputSchema konvertieren
        if (isset($jsonSchema['properties']) && is_array($jsonSchema['properties'])) {
            $required = $jsonSchema['required'] ?? [];

            foreach ($jsonSchema['properties'] as $propertyName => $property) {
                $type = $property['type'] ?? 'string';

                // Property hinzufügen basierend auf Typ
                switch ($type) {
                    case 'string':
                        $schema->string($propertyName);
                        break;
                    case 'integer':
                        $schema->integer($propertyName);
                        break;
                    case 'number':
                        $schema->number($propertyName);
                        break;
                    case 'boolean':
                        $schema->boolean($propertyName);
                        break;
                    case 'array':
                        // Arrays werden als string behandelt (MCP unterstützt keine komplexen Arrays direkt)
                        // Der Client muss JSON-String senden, der dann geparst wird
                        $schema->string($propertyName);
                        break;
                    case 'object':
                        // Objekte werden als string behandelt (JSON)
                        $schema->string($propertyName);
                        break;
                    default:
                        $schema->string($propertyName);
                }

                // Description hinzufügen
                if (isset($property['description'])) {
                    $schema->description($property['description']);
                }

                // Required markieren
                if (in_array($propertyName, $required)) {
                    $schema->required();
                }

                // Enum wird im JSON Schema bleiben (wird bei Validierung verwendet)
            }
        }

        return $schema;
    }

    /**
     * Führt das Tool aus und konvertiert das Ergebnis
     */
    public function handle(array $arguments): McpToolResult
    {
        try {
            // JSON-Strings in Arrays/Objekte konvertieren (falls nötig)
            $arguments = $this->normalizeArguments($arguments);

            // Context aus Request extrahieren
            $context = $this->createContextFromRequest();

            // Tool ausführen
            $result = $this->tool->execute($arguments, $context);

            // ToolResult zu MCP ToolResult konvertieren
            return $this->convertToolResult($result);
        } catch (\RuntimeException $e) {
            // Authentifizierungsfehler als Fehler-Result zurückgeben
            return McpToolResult::error($e->getMessage());
        } catch (\Throwable $e) {
            // Andere Fehler ebenfalls als Fehler-Result zurückgeben
            return McpToolResult::error('Tool execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Normalisiert Argumente (konvertiert JSON-Strings zu Arrays/Objekten)
     */
    private function normalizeArguments(array $arguments): array
    {
        $normalized = [];

        foreach ($arguments as $key => $value) {
            // Wenn Wert ein JSON-String ist, versuche ihn zu parsen
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
     * Erstellt ToolContext aus dem aktuellen Request oder Umgebungsvariable
     */
    private function createContextFromRequest(): ToolContext
    {
        // 1. Versuche User aus HTTP-Request zu holen (für Web-basierte Clients)
        $user = auth()->user();

        // 2. Falls kein User im Request, versuche Token aus Umgebungsvariable (für STDIO-Server)
        if (!$user) {
            $token = env('MCP_AUTH_TOKEN') ?? $_ENV['MCP_AUTH_TOKEN'] ?? null;

            if ($token) {
                // Authentifiziere User via Passport JWT Token
                $user = $this->authenticateViaPassport($token);
                if ($user) {
                    // Setze User für Auth-System
                    auth()->setUser($user);
                }
            }
        }

        if (!$user) {
            // Bei STDIO-Servern: Prüfe ob Token in Umgebungsvariable vorhanden ist
            $token = env('MCP_AUTH_TOKEN') ?? $_ENV['MCP_AUTH_TOKEN'] ?? getenv('MCP_AUTH_TOKEN');

            if (!$token) {
                throw new \RuntimeException('User must be authenticated to execute tools. Provide MCP_AUTH_TOKEN environment variable or authenticate via HTTP request.');
            }

            // Token wurde gefunden, aber User konnte nicht authentifiziert werden
            throw new \RuntimeException('Invalid MCP_AUTH_TOKEN. Please check your token and try again.');
        }

        // Team aus User extrahieren (falls vorhanden)
        $team = null;
        if (method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;
        }

        return ToolContext::create($user, $team);
    }

    /**
     * Authentifiziert User via Passport JWT Token
     *
     * @param string $bearerToken
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private function authenticateViaPassport(string $bearerToken)
    {
        try {
            // JWT parsen und Token-ID extrahieren
            $tokenId = $this->parseJwtTokenId($bearerToken);

            if (!$tokenId) {
                return null;
            }

            // Token in Datenbank nachschlagen
            $tokenRepository = app(TokenRepository::class);
            $token = $tokenRepository->find($tokenId);

            if (!$token) {
                return null;
            }

            // Prüfen ob Token widerrufen oder abgelaufen ist
            if ($token->revoked) {
                return null;
            }

            if ($token->expires_at && $token->expires_at->isPast()) {
                return null;
            }

            // User laden
            $userModelClass = config('auth.providers.users.model');
            $user = $userModelClass::find($token->user_id);

            if ($user) {
                // Token am User setzen für tokenCan() etc.
                $user->withAccessToken($token);
            }

            return $user;
        } catch (\Exception $e) {
            // Bei Parsing-Fehlern: null zurückgeben
            return null;
        }
    }

    /**
     * Parst JWT Token und extrahiert die Token-ID (jti claim)
     *
     * @param string $jwt
     * @return string|null
     */
    private function parseJwtTokenId(string $jwt): ?string
    {
        try {
            $parts = explode('.', $jwt);

            if (count($parts) !== 3) {
                return null;
            }

            // Payload dekodieren (mittlerer Teil)
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
     * Konvertiert ToolResult zu MCP ToolResult
     */
    private function convertToolResult(ToolResult $result): McpToolResult
    {
        if (!$result->success) {
            // Fehler-Result
            $errorMessage = $result->error ?? 'Unknown error';
            if ($result->errorCode) {
                $errorMessage = "[{$result->errorCode}] {$errorMessage}";
            }
            return McpToolResult::error($errorMessage);
        }

        // Erfolgreiches Result
        $data = $result->data;

        // Konvertiere zu JSON-String
        if (is_array($data) || is_object($data)) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return McpToolResult::text($json);
        }

        // Wenn bereits String, direkt verwenden
        if (is_string($data)) {
            return McpToolResult::text($data);
        }

        // Fallback: Konvertiere zu String
        return McpToolResult::text((string) $data);
    }

    /**
     * Gibt das gewrappte Tool zurück (für Debugging)
     */
    public function getWrappedTool(): ToolContract
    {
        return $this->tool;
    }
}
