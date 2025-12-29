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

        foreach ($allTools as $tool) {
            $metadata = $this->getToolMetadata($tool);
            $examples = $metadata['examples'] ?? [];
            $tags = $metadata['tags'] ?? [];
            $description = strtolower($tool->getDescription());
            $intentLower = strtolower($intent);

            // Prüfe, ob Intent zu Tool passt
            $score = 0;

            // Prüfe Examples
            foreach ($examples as $example) {
                if (stripos($example, $intentLower) !== false) {
                    $score += 10;
                }
            }

            // Prüfe Tags
            foreach ($tags as $tag) {
                if (stripos($intentLower, $tag) !== false) {
                    $score += 5;
                }
            }

            // Prüfe Beschreibung
            if (stripos($description, $intentLower) !== false) {
                $score += 3;
            }

            if ($score > 0) {
                $results[] = [
                    'tool' => $tool,
                    'score' => $score,
                    'metadata' => $metadata
                ];
            }
        }

        // Sortiere nach Score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($r) => $r['tool'], $results);
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

