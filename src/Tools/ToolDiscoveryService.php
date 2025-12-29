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
     * @param string $intent Benutzer-Intent (z.B. "Projekt erstellen", "Teams auflisten")
     * @return array Relevante Tools
     */
    public function findByIntent(string $intent): array
    {
        $allTools = $this->registry->all();
        $results = [];

        // Normalisiere Intent: entferne häufige Wörter und extrahiere Keywords
        $intentLower = strtolower(trim($intent));
        $intentKeywords = $this->extractKeywords($intentLower);

        foreach ($allTools as $tool) {
            $metadata = $this->getToolMetadata($tool);
            $examples = $metadata['examples'] ?? [];
            $tags = $metadata['tags'] ?? [];
            $description = strtolower($tool->getDescription());
            $toolName = strtolower($tool->getName());

            // Prüfe, ob Intent zu Tool passt
            $score = 0;

            // Prüfe Examples
            foreach ($examples as $example) {
                $exampleLower = strtolower($example);
                foreach ($intentKeywords as $keyword) {
                    if (stripos($exampleLower, $keyword) !== false) {
                        $score += 10;
                        break; // Nur einmal pro Example
                    }
                }
            }

            // Prüfe Tags
            foreach ($tags as $tag) {
                $tagLower = strtolower($tag);
                foreach ($intentKeywords as $keyword) {
                    if (stripos($tagLower, $keyword) !== false || stripos($keyword, $tagLower) !== false) {
                        $score += 5;
                        break; // Nur einmal pro Tag
                    }
                }
            }

            // Prüfe Tool-Name (z.B. "planner.projects.create" für "projekt erstellen")
            // WICHTIG: Prüfe auch einzelne Teile des Tool-Namens (z.B. "projects" oder "create")
            $toolNameParts = explode('.', $toolName);
            foreach ($intentKeywords as $keyword) {
                // Prüfe vollständigen Tool-Namen
                if (stripos($toolName, $keyword) !== false) {
                    $score += 8;
                }
                // Prüfe einzelne Teile (z.B. "projects" in "planner.projects.create")
                foreach ($toolNameParts as $part) {
                    if (stripos($part, $keyword) !== false || stripos($keyword, $part) !== false) {
                        $score += 6;
                    }
                }
            }
            
            // BONUS: Spezielle Mappings für häufige Aktionen
            $actionMappings = [
                'erstellen' => ['create', 'new', 'add', 'anlegen'],
                'anzeigen' => ['list', 'show', 'get', 'find'],
                'löschen' => ['delete', 'remove', 'destroy'],
                'bearbeiten' => ['update', 'edit', 'modify'],
            ];
            
            foreach ($intentKeywords as $keyword) {
                if (isset($actionMappings[$keyword])) {
                    foreach ($actionMappings[$keyword] as $mappedAction) {
                        if (stripos($toolName, $mappedAction) !== false) {
                            $score += 10; // Hoher Score für Action-Matches
                        }
                    }
                }
            }

            // Prüfe Beschreibung (auch Teilstrings)
            foreach ($intentKeywords as $keyword) {
                if (stripos($description, $keyword) !== false) {
                    $score += 3;
                }
            }

            // Bonus: Wenn Intent-Wörter direkt in Beschreibung vorkommen
            if (stripos($description, $intentLower) !== false) {
                $score += 5;
            }

            // Mindest-Score: Auch wenn Score niedrig, aber Tool-Name enthält relevante Keywords
            $hasRelevantKeywords = $this->hasRelevantKeywords($toolName, $intentKeywords);
            
            if ($score > 0 || $hasRelevantKeywords) {
                $results[] = [
                    'tool' => $tool,
                    'score' => max($score, 1), // Mindestens Score 1 wenn relevant
                    'metadata' => $metadata
                ];
            }
        }

        // Sortiere nach Score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($r) => $r['tool'], $results);
    }

    /**
     * Extrahiert relevante Keywords aus einem Intent-String
     * 
     * @param string $intent
     * @return array
     */
    private function extractKeywords(string $intent): array
    {
        // Entferne häufige Stop-Wörter
        $stopWords = ['ein', 'eine', 'einen', 'einer', 'einem', 'eines', 'der', 'die', 'das', 'den', 'dem', 'des', 
                      'und', 'oder', 'aber', 'mit', 'für', 'von', 'zu', 'auf', 'in', 'an', 'ist', 'sind', 'war', 
                      'waren', 'wird', 'werden', 'hat', 'haben', 'hatte', 'hatten', 'namens', 'heißt', 'heissen',
                      'test', 'bitte', 'kannst', 'kann', 'möchte', 'möchten'];
        
        // Normalisiere: entferne Sonderzeichen, teile in Wörter
        // Verbesserte Regex: erlaubt auch Umlaute und mehr Zeichen
        $words = preg_split('/[\s,\.!?;:()\[\]{}]+/u', $intent, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            $wordLower = mb_strtolower($word, 'UTF-8');
            if (mb_strlen($word, 'UTF-8') > 2 && !in_array($wordLower, $stopWords)) {
                $keywords[] = $wordLower;
            }
        }
        
        // Füge auch zusammengesetzte Begriffe hinzu (z.B. "projekt erstellen" -> ["projekt", "erstellen", "projekt erstellen"])
        if (count($keywords) > 1) {
            // Erstelle Bigrams (zwei aufeinanderfolgende Wörter)
            for ($i = 0; $i < count($keywords) - 1; $i++) {
                $bigram = $keywords[$i] . ' ' . $keywords[$i + 1];
                $keywords[] = $bigram;
            }
        }
        
        return array_unique($keywords);
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
    private function getToolMetadata(ToolContract $tool): array
    {
        if ($tool instanceof ToolMetadataContract) {
            return $tool->getMetadata();
        }

        // Default-Metadaten basierend auf Tool-Name
        $name = $tool->getName();
        $metadata = [
            'category' => $this->inferCategory($name),
            'tags' => $this->inferTags($name),
            'read_only' => $this->isReadOnly($name),
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
     */
    private function isReadOnly(string $toolName): bool
    {
        return str_contains($toolName, '.list') || 
               str_contains($toolName, '.get') || 
               str_contains($toolName, '.search') ||
               str_contains($toolName, '.describe');
    }
}

