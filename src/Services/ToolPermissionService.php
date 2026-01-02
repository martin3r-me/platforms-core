<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Module;

/**
 * Service für Tool-Berechtigungen
 * 
 * Nutzt die bestehende Module::hasAccess() Logik (wie CheckModulePermission Middleware)
 * Prüft, ob ein User Zugriff auf ein Tool hat, basierend auf Modul-Zugriff
 */
class ToolPermissionService
{
    /**
     * Extrahiert den Modul-Key aus einem Tool-Namen
     * 
     * @param string $toolName Tool-Name (z.B. "planner.projects.GET")
     * @return string|null Modul-Key (z.B. "planner") oder null für Core-Tools
     */
    public function extractModuleFromToolName(string $toolName): ?string
    {
        if (!str_contains($toolName, '.')) {
            return null; // Core-Tools haben keinen Modul-Präfix
        }
        
        $parts = explode('.', $toolName);
        $moduleKey = $parts[0];
        
        // Core-Tools haben "core" als Präfix, aber sind immer erlaubt
        if ($moduleKey === 'core' || $moduleKey === 'tools') {
            return null; // Core-Tools sind immer erlaubt
        }
        
        return $moduleKey;
    }
    
    /**
     * Prüft, ob der aktuelle User Zugriff auf ein Tool hat
     * 
     * Nutzt die gleiche Logik wie CheckModulePermission Middleware:
     * - Module::hasAccess() für zentrale Berechtigungsprüfung
     * - Berücksichtigt Root-Scoped vs. Team-Scoped Module
     * 
     * @param string $toolName Tool-Name (z.B. "planner.projects.GET")
     * @return bool True wenn User Zugriff hat, sonst false
     */
    public function hasAccess(string $toolName): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false; // Nicht eingeloggt = kein Zugriff
        }
        
        $moduleKey = $this->extractModuleFromToolName($toolName);
        
        // Core-Tools und Discovery-Tools sind immer erlaubt
        if ($moduleKey === null) {
            return true;
        }
        
        $baseTeam = $user->currentTeamRelation;
        if (!$baseTeam) {
            return false; // Kein Team = kein Zugriff
        }
        
        // Modul finden und Zugriff prüfen (wie CheckModulePermission Middleware)
        $module = Module::where('key', $moduleKey)->first();
        if (!$module) {
            // Modul nicht gefunden - könnte ein neues Modul sein, das noch nicht registriert ist
            // Für Sicherheit: kein Zugriff
            return false;
        }
        
        // Zentrale Berechtigungsprüfung verwenden (wie CheckModulePermission Middleware)
        // Diese Methode berücksichtigt bereits Root-Scoped vs. Team-Scoped Module
        return $module->hasAccess($user, $baseTeam);
    }
    
    /**
     * Filtert Tools nach Berechtigung
     * 
     * @param array $tools Array von Tool-Instanzen
     * @return array Gefilterte Tools (nur die, auf die User Zugriff hat)
     */
    public function filterToolsByPermission(array $tools): array
    {
        return array_filter($tools, function($tool) {
            $toolName = $tool->getName();
            return $this->hasAccess($toolName);
        });
    }
}

