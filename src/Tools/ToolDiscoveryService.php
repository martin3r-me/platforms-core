<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolDependencyContract;

/**
 * Tool Discovery Service (MCP-Pattern)
 * 
 * Erweiterte Tool-Discovery mit Metadaten, Tags, Kategorien.
 * Ermöglicht semantische Suche und bessere AI-Integration.
 */
class ToolDiscoveryService
{
    public function __construct(
        private ToolRegistry $registry
    ) {}

    /**
     * Findet Tools nach verschiedenen Kriterien
     * 
     * @param array $criteria Suchkriterien
     * @return array Gefundene Tools mit Metadaten
     */
    public function discover(array $criteria = []): array
    {
        $allTools = $this->registry->all();
        $results = [];

        foreach ($allTools as $tool) {
            $matches = true;

            // Prüfe Kriterien
            if (isset($criteria['category'])) {
                $metadata = $this->getToolMetadata($tool);
                if (($metadata['category'] ?? null) !== $criteria['category']) {
                    $matches = false;
                }
            }

            if (isset($criteria['tag'])) {
                $metadata = $this->getToolMetadata($tool);
                $tags = $metadata['tags'] ?? [];
                if (!in_array($criteria['tag'], $tags)) {
                    $matches = false;
                }
            }

            if (isset($criteria['read_only'])) {
                $metadata = $this->getToolMetadata($tool);
                if (($metadata['read_only'] ?? false) !== $criteria['read_only']) {
                    $matches = false;
                }
            }

            if ($matches) {
                $results[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'schema' => $tool->getSchema(),
                    'metadata' => $this->getToolMetadata($tool),
                    'has_dependencies' => $tool instanceof ToolDependencyContract
                ];
            }
        }

        return $results;
    }

    /**
     * Findet Tools, die für eine bestimmte Aufgabe relevant sind
     * 
     * WICHTIG: Diese Methode ist OPTIONAL und nur für spezielle Use-Cases (z.B. Playground).
     * Der Standard-MCP-Flow sendet ALLE Tools an das LLM, das dann selbst entscheidet.
     * 
     * Für die Tool-Discovery sollte das LLM das Tool "tools.list" mit Filtern verwenden.
     * 
     * @param string $intent Benutzer-Intent (z.B. "Projekt erstellen", "Teams auflisten")
     * @return array Relevante Tools
     */
    public function findByIntent(string $intent): array
    {
        // MCP Best Practice: Immer alle Tools zurückgeben
        // Das LLM kann selbst entscheiden, welche Tools es verwenden möchte
        // Oder es kann das Tool "tools.list" mit Filtern aufrufen
        return array_values($this->registry->all());
    }
    
    /**
     * Findet Tools nach Kriterien (für tools.list Tool)
     * 
     * @param array $criteria Suchkriterien (search, module, category, tag, read_only)
     * @return array Gefundene Tools
     */
    public function findByCriteria(array $criteria): array
    {
        $allTools = $this->registry->all();
        $results = [];
        
        foreach ($allTools as $tool) {
            $matches = true;
            $metadata = $this->getToolMetadata($tool);
            $toolName = strtolower($tool->getName());
            $description = strtolower($tool->getDescription());
            
            // Filter: search (Keyword-Suche in Name/Beschreibung)
            if (isset($criteria['search']) && !empty($criteria['search'])) {
                $search = strtolower($criteria['search']);
                if (stripos($toolName, $search) === false && stripos($description, $search) === false) {
                    $matches = false;
                }
            }
            
            // Filter: module
            if (isset($criteria['module']) && !empty($criteria['module'])) {
                $module = strtolower($criteria['module']);
                if (!str_starts_with($toolName, $module . '.')) {
                    $matches = false;
                }
            }
            
            // Filter: category
            if (isset($criteria['category']) && ($metadata['category'] ?? null) !== $criteria['category']) {
                $matches = false;
            }
            
            // Filter: tag
            if (isset($criteria['tag'])) {
                $tags = $metadata['tags'] ?? [];
                if (!in_array($criteria['tag'], $tags)) {
                    $matches = false;
                }
            }
            
            // Filter: read_only
            if (isset($criteria['read_only'])) {
                if (($metadata['read_only'] ?? false) !== $criteria['read_only']) {
                    $matches = false;
                }
            }
            
            if ($matches) {
                $results[] = $tool;
            }
        }
        
        return $results;
    }
    
    /**
     * Vereinfachte Keyword-Extraktion (ohne Bigrams, ohne Memory-Probleme)
     * 
     * @param string $intent
     * @return array Maximal 10 Keywords
     */
    private function extractSimpleKeywords(string $intent): array
    {
        // Stop-Wörter
        $stopWords = ['ein', 'eine', 'einen', 'einer', 'einem', 'eines', 'der', 'die', 'das', 'den', 'dem', 'des', 
                      'und', 'oder', 'aber', 'mit', 'für', 'von', 'zu', 'auf', 'in', 'an', 'ist', 'sind', 'war', 
                      'waren', 'wird', 'werden', 'hat', 'haben', 'hatte', 'hatten', 'namens', 'heißt', 'heissen',
                      'test', 'bitte', 'kannst', 'kann', 'möchte', 'möchten'];
        
        // Einfache Aufteilung nach Leerzeichen (keine komplexe Regex)
        $words = preg_split('/\s+/u', strtolower(trim($intent)), -1, PREG_SPLIT_NO_EMPTY);
        
        // Fallback: Wenn preg_split fehlschlägt
        if ($words === false || empty($words)) {
            $words = array_filter(explode(' ', strtolower(trim($intent))), fn($w) => !empty(trim($w)));
        }
        
        // Filtere Stop-Wörter und kurze Wörter
        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        // Maximal 10 Keywords (keine Bigrams!)
        return array_slice(array_unique($keywords), 0, 10);
    }

    /**
     * @deprecated Verwende extractSimpleKeywords() stattdessen
     * Diese Methode wird nur noch für Backwards-Kompatibilität aufgerufen
     */
    private function extractKeywords(string $intent): array
    {
        return $this->extractSimpleKeywords($intent);
    }

    /**
     * Prüft ob Tool-Name relevante Keywords enthält
     */
    private function hasRelevantKeywords(string $toolName, array $keywords): bool
    {
        $toolNameLower = strtolower($toolName);
        $toolNameParts = explode('.', $toolNameLower);
        
        foreach ($keywords as $keyword) {
            // Prüfe ob Keyword in Tool-Name oder Teilen vorkommt
            if (stripos($toolNameLower, $keyword) !== false) {
                return true;
            }
            foreach ($toolNameParts as $part) {
                if (stripos($part, $keyword) !== false || stripos($keyword, $part) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Gibt Metadaten für ein Tool zurück
     */
    public function getToolMetadata(ToolContract $tool): array
    {
        if ($tool instanceof ToolMetadataContract) {
            return $tool->getMetadata();
        }

        // Default-Metadaten basierend auf Tool-Name
        $name = $tool->getName();
        $httpMethod = $this->extractHttpMethod($name);
        $metadata = [
            'category' => $this->inferCategory($name),
            'tags' => $this->inferTags($name),
            'read_only' => $this->isReadOnly($name),
            'http_method' => $httpMethod, // REST-Muster
            'requires_auth' => true,
            'requires_team' => false,
        ];

        return $metadata;
    }

    /**
     * Inferiert Kategorie aus Tool-Namen
     */
    private function inferCategory(string $toolName): string
    {
        // REST-Muster
        if (str_ends_with($toolName, '.GET') || str_ends_with($toolName, '.get')) {
            return 'query';
        }
        if (str_ends_with($toolName, '.POST') || str_ends_with($toolName, '.post') ||
            str_ends_with($toolName, '.PUT') || str_ends_with($toolName, '.put') ||
            str_ends_with($toolName, '.DELETE') || str_ends_with($toolName, '.delete')) {
            return 'action';
        }
        
        // Legacy-Muster (Backwards-Kompatibilität)
        if (str_contains($toolName, '.list') || str_contains($toolName, '.get') || str_contains($toolName, '.search')) {
            return 'query';
        }
        if (str_contains($toolName, '.create') || str_contains($toolName, '.update') || str_contains($toolName, '.delete')) {
            return 'action';
        }
        return 'utility';
    }

    /**
     * Inferiert Tags aus Tool-Namen
     */
    private function inferTags(string $toolName): array
    {
        $tags = [];
        $parts = explode('.', $toolName);
        
        if (count($parts) > 0) {
            $tags[] = $parts[0]; // Modul-Name
        }
        
        if (count($parts) > 1) {
            $tags[] = $parts[1]; // Entity-Name
        }
        
        if (str_contains($toolName, 'create')) {
            $tags[] = 'create';
        }
        if (str_contains($toolName, 'list')) {
            $tags[] = 'list';
        }
        if (str_contains($toolName, 'team')) {
            $tags[] = 'team';
        }
        if (str_contains($toolName, 'project')) {
            $tags[] = 'project';
        }

        return $tags;
    }

    /**
     * Prüft, ob Tool read-only ist
     * 
     * REST-Muster: GET ist immer read-only
     * POST, PUT, DELETE sind write-Operationen
     */
    private function isReadOnly(string $toolName): bool
    {
        // REST-Muster: GET ist read-only
        if (str_ends_with($toolName, '.GET') || str_ends_with($toolName, '.get')) {
            return true;
        }
        
        // Legacy-Muster (Backwards-Kompatibilität)
        if (str_contains($toolName, '.list') || 
            str_contains($toolName, '.get') || 
            str_contains($toolName, '.search') ||
            str_contains($toolName, '.describe')) {
            return true;
        }
        
        // POST, PUT, DELETE sind write-Operationen
        if (str_ends_with($toolName, '.POST') || str_ends_with($toolName, '.post') ||
            str_ends_with($toolName, '.PUT') || str_ends_with($toolName, '.put') ||
            str_ends_with($toolName, '.DELETE') || str_ends_with($toolName, '.delete')) {
            return false;
        }
        
        // Legacy write-Muster
        if (str_contains($toolName, '.create') || 
            str_contains($toolName, '.update') || 
            str_contains($toolName, '.delete')) {
            return false;
        }
        
        // Default: read-only (sicherer)
        return true;
    }
    
    /**
     * Extrahiert HTTP-Methode aus Tool-Namen
     * 
     * @return string|null HTTP-Methode (GET, POST, PUT, DELETE) oder null
     */
    private function extractHttpMethod(string $toolName): ?string
    {
        if (str_ends_with($toolName, '.GET') || str_ends_with($toolName, '.get')) {
            return 'GET';
        }
        if (str_ends_with($toolName, '.POST') || str_ends_with($toolName, '.post')) {
            return 'POST';
        }
        if (str_ends_with($toolName, '.PUT') || str_ends_with($toolName, '.put')) {
            return 'PUT';
        }
        if (str_ends_with($toolName, '.DELETE') || str_ends_with($toolName, '.delete')) {
            return 'DELETE';
        }
        
        // Legacy-Mapping
        if (str_contains($toolName, '.list') || str_contains($toolName, '.get')) {
            return 'GET';
        }
        if (str_contains($toolName, '.create')) {
            return 'POST';
        }
        if (str_contains($toolName, '.update')) {
            return 'PUT';
        }
        if (str_contains($toolName, '.delete')) {
            return 'DELETE';
        }
        
        return null;
    }
}


