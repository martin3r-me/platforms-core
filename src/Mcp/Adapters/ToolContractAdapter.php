<?php

namespace Platform\Core\Mcp\Adapters;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Events\ToolExecuted;
use Platform\Core\Events\ToolFailed;
use Platform\Core\Services\ToolPermissionService;
use Platform\Core\Services\ToolValidationService;
use Platform\Core\Services\TeamContext;
use Platform\Core\Models\McpSession;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Platform\Core\Mcp\Content\ImageResponse;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Laravel\Mcp\Request;
use Laravel\Passport\Token;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;

/**
 * Adapter für ToolContract → Laravel MCP Tool
 *
 * Wrappt ein ToolContract Tool und macht es als MCP Tool verfügbar.
 * Konvertiert automatisch Schema, Context und Results.
 */
class ToolContractAdapter extends Tool
{
    public function __construct(
        private ToolContract $tool,
        private ?string $mcpSessionId = null,
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

                // Erlaubte Werte (enum) in Description dokumentieren
                $description = $this->enrichDescriptionWithConstraints($description, $property, $type);

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
     * Reichert die Description mit Schema-Constraints an (enum, min/max, pattern)
     *
     * Damit sieht die LLM direkt, welche Werte erlaubt sind.
     */
    private function enrichDescriptionWithConstraints(?string $description, array $property, string $type): ?string
    {
        $parts = [];

        if ($description) {
            $parts[] = $description;
        }

        // Enum-Werte dokumentieren (z.B. "Erlaubte Werte: low|normal|high")
        if (isset($property['enum']) && is_array($property['enum'])) {
            $enumValues = implode('|', $property['enum']);
            // Nur anfügen, wenn die Description die Werte nicht bereits enthält
            if (!$description || !str_contains($description, $enumValues)) {
                $parts[] = "Erlaubte Werte: {$enumValues}";
            }
        }

        // Default-Wert dokumentieren
        if (isset($property['default']) && !isset($property['enum'])) {
            $default = is_bool($property['default'])
                ? ($property['default'] ? 'true' : 'false')
                : $property['default'];
            if (!$description || !str_contains($description, 'Standard:') && !str_contains($description, 'Default:')) {
                $parts[] = "Standard: {$default}";
            }
        }

        // Number-Constraints (minimum/maximum)
        if (($type === 'integer' || $type === 'number') && (isset($property['minimum']) || isset($property['maximum']))) {
            $range = [];
            if (isset($property['minimum'])) {
                $range[] = "min: {$property['minimum']}";
            }
            if (isset($property['maximum'])) {
                $range[] = "max: {$property['maximum']}";
            }
            $parts[] = 'Bereich: ' . implode(', ', $range);
        }

        // String-Constraints (minLength/maxLength)
        if ($type === 'string' && (isset($property['minLength']) || isset($property['maxLength']))) {
            $lengths = [];
            if (isset($property['minLength'])) {
                $lengths[] = "min. {$property['minLength']} Zeichen";
            }
            if (isset($property['maxLength'])) {
                $lengths[] = "max. {$property['maxLength']} Zeichen";
            }
            $parts[] = 'Länge: ' . implode(', ', $lengths);
        }

        return !empty($parts) ? implode('. ', $parts) : null;
    }

    /**
     * Führt das Tool aus und konvertiert das Ergebnis
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        try {
            // Arguments aus MCP Request extrahieren
            $arguments = $request->all();

            // JSON-Strings in Arrays/Objekte konvertieren (falls nötig)
            $arguments = $this->normalizeArguments($arguments);

            // Context aus Request extrahieren
            $context = $this->createContextFromRequest();

            $toolName = $this->tool->getName();

            // Logging: Jeder Tool-Call mit Session + Team + User
            Log::info('[MCP Tool] ' . $toolName, [
                'session' => $this->mcpSessionId ? substr($this->mcpSessionId, 0, 12) . '...' : null,
                'team_id' => $context->team?->id,
                'team_name' => $context->team?->name,
                'user_id' => $context->user?->id,
            ]);

            // TeamContext SOFORT setzen: User::currentTeam liefert ab hier das MCP-Team.
            // Muss VOR Permission-Check stehen, damit hasAccess() das richtige Team prüft.
            TeamContext::setTeam($context->team);

            try {
                // Berechtigungsprüfung: Hat der User Zugriff auf das Modul?
                try {
                    $permissionService = app(ToolPermissionService::class);
                    if (!$permissionService->hasAccess($toolName)) {
                        Log::warning('[MCP ToolContractAdapter] Zugriff verweigert', [
                            'tool' => $toolName,
                            'user_id' => $context->user?->id,
                            'team_id' => $context->team?->id,
                        ]);
                        return Response::error("Kein Zugriff auf Tool '{$toolName}'. Das Modul ist für dein Team nicht freigeschaltet.");
                    }
                } catch (\Throwable $e) {
                    Log::warning('[MCP ToolContractAdapter] Permission-Check fehlgeschlagen, erlaube Zugriff', [
                        'tool' => $this->tool->getName(),
                        'error' => $e->getMessage(),
                    ]);
                }

                // Parameter validieren (Fehlermeldungen direkt an die LLM)
                try {
                    $validationService = app(ToolValidationService::class);
                    $validationResult = $validationService->validate($this->tool, $arguments);
                    if (!$validationResult['valid']) {
                        $errorLines = array_map(fn($e) => "- {$e}", $validationResult['errors']);
                        $errorMessage = "[VALIDATION_ERROR] Validierung fehlgeschlagen für Tool '{$toolName}':\n"
                            . implode("\n", $errorLines);
                        return Response::error($errorMessage);
                    }
                    // Verwende validierte (normalisierte) Daten
                    $arguments = $validationResult['data'];
                } catch (\Throwable $e) {
                    // Validation-Service nicht verfügbar – weiter ohne Validierung
                    Log::debug('[MCP ToolContractAdapter] Validation-Service nicht verfügbar', [
                        'tool' => $toolName,
                    ]);
                }

                // Tool ausführen
                $start = microtime(true);
                $memStart = memory_get_usage();
                $traceId = bin2hex(random_bytes(8));

                $result = $this->tool->execute($arguments, $context);

                $duration = microtime(true) - $start;
                $memUsage = memory_get_usage() - $memStart;

                try {
                    $trackedResult = new ToolResult(
                        success: $result->success,
                        data: $result->data,
                        error: $result->error,
                        errorCode: $result->errorCode,
                        metadata: array_merge($result->metadata, [
                            'source' => 'mcp_direct',
                            'token_estimate_input' => (int) (mb_strlen(json_encode($arguments)) / 4),
                            'token_estimate_output' => (int) (mb_strlen(json_encode($result->data ?? [])) / 4),
                        ]),
                    );

                    event(new ToolExecuted(
                        toolName: $toolName,
                        arguments: $arguments,
                        context: $context,
                        result: $trackedResult,
                        duration: $duration,
                        memoryUsage: $memUsage,
                        traceId: $traceId,
                    ));
                } catch (\Throwable $e) {
                    Log::warning('[MCP ToolContractAdapter] Event-Tracking fehlgeschlagen', ['error' => $e->getMessage()]);
                }

                // ToolResult zu MCP Response konvertieren
                return $this->convertToolResult($result);
            } finally {
                TeamContext::clear();
            }
        } catch (\RuntimeException $e) {
            $this->fireFailedEvent($toolName ?? 'unknown', $arguments ?? [], $context ?? null, $e, $start ?? null, $memStart ?? null, $traceId ?? null);
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            $this->fireFailedEvent($toolName ?? 'unknown', $arguments ?? [], $context ?? null, $e, $start ?? null, $memStart ?? null, $traceId ?? null);
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

        // MCP-Kontext: Team aus mcp_sessions Tabelle, Fallback auf current_team_id
        $team = null;
        $teamSource = 'none';
        $metadata = [];

        if ($this->mcpSessionId) {
            $metadata['mcp_session_id'] = $this->mcpSessionId;

            try {
                $mcpSession = McpSession::find($this->mcpSessionId);
                if ($mcpSession && $mcpSession->team_id) {
                    $team = $mcpSession->team;
                    $teamSource = 'mcp_session';
                    $mcpSession->update(['last_activity_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::warning('[MCP ToolContractAdapter] McpSession-Lookup fehlgeschlagen', [
                    'session_id' => $this->mcpSessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$team && method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;
            $teamSource = 'user_current';
        }

        $metadata['team_source'] = $teamSource;

        Log::debug('[MCP Context]', [
            'session' => $this->mcpSessionId ? substr($this->mcpSessionId, 0, 12) . '...' : null,
            'team_source' => $teamSource,
            'team_id' => $team?->id,
        ]);

        return ToolContext::create($user, $team, $metadata);
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
    private function convertToolResult(ToolResult $result): Response|ResponseFactory
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
            $text = Response::text($json);
        } elseif (is_string($data)) {
            $text = Response::text($data);
        } else {
            $text = Response::text((string) $data);
        }

        if (empty($result->images)) {
            return $text;
        }

        $parts = [$text];
        $skipped = 0;
        foreach (array_slice($result->images, 0, self::MAX_IMAGES) as $image) {
            $prepared = $this->prepareImage($image['data'], $image['mime']);
            if ($prepared === null) {
                $skipped++;
                continue;
            }
            $parts[] = ImageResponse::fromBase64(base64_encode($prepared['data']), $prepared['mime']);
        }
        $skipped += max(0, count($result->images) - self::MAX_IMAGES);
        if ($skipped > 0) {
            $parts[] = Response::text("[Hinweis: {$skipped} Bild(er) nicht ausgegeben – Limit " . self::MAX_IMAGES . ' Bilder, ' . (self::MAX_IMAGE_BYTES / 1024) . ' KB je Bild, png/jpeg/webp]');
        }

        return Response::make($parts);
    }

    private const MAX_IMAGES = 6;
    private const MAX_IMAGE_BYTES = 1572864; // 1,5 MB je Bild
    private const ALLOWED_IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /**
     * Prüft Mime/Größe, skaliert zu große Bilder herunter. null = nicht ausgebbar.
     *
     * @return array{data: string, mime: string}|null
     */
    private function prepareImage(string $binary, string $mime): ?array
    {
        if ($binary === '' || !in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            return null;
        }
        if (strlen($binary) <= self::MAX_IMAGE_BYTES) {
            return ['data' => $binary, 'mime' => $mime];
        }

        try {
            $manager = new ImageManager(new GdDriver());
            $img = $manager->read($binary);
            $scale = 1.0;
            for ($i = 0; $i < 5; $i++) {
                $scale *= 0.75;
                $copy = $manager->read($binary)->scaleDown(
                    width: max(1, (int) ($img->width() * $scale)),
                    height: max(1, (int) ($img->height() * $scale))
                );
                $png = $mime === 'image/png';
                $encoded = $png ? (string) $copy->toPng() : (string) $copy->toJpeg(80);
                if (strlen($encoded) <= self::MAX_IMAGE_BYTES) {
                    return ['data' => $encoded, 'mime' => $png ? 'image/png' : 'image/jpeg'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[MCP ToolContractAdapter] Bild-Downscaling fehlgeschlagen', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Feuert ein ToolFailed Event (graceful)
     */
    private function fireFailedEvent(string $toolName, array $arguments, ?ToolContext $context, \Throwable $exception, ?float $start, ?int $memStart, ?string $traceId): void
    {
        if (!$context) {
            return;
        }

        try {
            event(new ToolFailed(
                toolName: $toolName,
                arguments: $arguments,
                context: $context,
                errorMessage: $exception->getMessage(),
                errorCode: 'EXECUTION_ERROR',
                exception: $exception,
                duration: $start ? microtime(true) - $start : 0,
                memoryUsage: $memStart ? memory_get_usage() - $memStart : 0,
                traceId: $traceId,
            ));
        } catch (\Throwable $e) {
            // Silent
        }
    }

    /**
     * Gibt das gewrappte Tool zurück
     */
    public function getWrappedTool(): ToolContract
    {
        return $this->tool;
    }
}
