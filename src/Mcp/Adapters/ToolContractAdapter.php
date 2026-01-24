<?php

namespace Platform\Core\Mcp\Adapters;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult as McpToolResult;
use Laravel\Mcp\Server\Tools\TextContent;
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
        // JSON-Strings in Arrays/Objekte konvertieren (falls nötig)
        $arguments = $this->normalizeArguments($arguments);
        
        // Context aus Request extrahieren
        $context = $this->createContextFromRequest();
        
        // Tool ausführen
        $result = $this->tool->execute($arguments, $context);
        
        // ToolResult zu MCP ToolResult konvertieren
        return $this->convertToolResult($result);
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
     * Erstellt ToolContext aus dem aktuellen Request
     */
    private function createContextFromRequest(): ToolContext
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new \RuntimeException('User must be authenticated to execute tools');
        }
        
        // Team aus User extrahieren (falls vorhanden)
        $team = null;
        if (method_exists($user, 'currentTeam')) {
            $team = $user->currentTeam;
        }
        
        return ToolContext::create($user, $team);
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
