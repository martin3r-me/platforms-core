<?php

namespace Platform\Core\Contracts;

/**
 * Optionales Interface für erweiterte Tool-Metadaten (MCP-Pattern)
 * 
 * Tools können dieses Interface implementieren, um zusätzliche Metadaten
 * über sich selbst bereitzustellen. Dies ermöglicht bessere Tool-Discovery,
 * Chain-Planning und AI-Integration.
 */
interface ToolMetadataContract
{
    /**
     * Gibt Metadaten über das Tool zurück
     * 
     * Format:
     * [
     *   'category' => 'data|action|query|utility', // Tool-Kategorie
     *   'tags' => ['project', 'team', 'create'], // Such-Tags
     *   'requires_auth' => true, // Benötigt Authentifizierung
     *   'requires_team' => true, // Benötigt Team-Context
     *   'side_effects' => ['creates', 'updates', 'deletes'], // Seiteneffekte
     *   'read_only' => false, // Nur Lese-Operation
     *   'idempotent' => false, // Tool ist idempotent (kann sicher wiederholt werden)
     *   'risk_level' => 'safe', // safe|write|destructive - Risiko-Level
     *   'confirmation_required' => false, // Benötigt Bestätigung vor Ausführung
     *   'examples' => [ // Beispiel-Nutzungen für AI
     *     'Erstelle ein Projekt namens "Test"',
     *     'Zeige mir alle Teams'
     *   ],
     *   'related_tools' => ['core.teams.list', 'planner.tasks.create'], // Verwandte Tools
     *   'output_schema' => [ // Schema des Outputs (für AI)
     *     'type' => 'object',
     *     'properties' => [...]
     *   ]
     * ]
     * 
     * @return array Metadaten
     */
    public function getMetadata(): array;
}

