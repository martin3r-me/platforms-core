<?php

namespace Platform\Core\Contracts;

/**
 * Optionales Interface für Tools mit Dependencies
 * 
 * Tools können dieses Interface implementieren, um ihre Dependencies zu definieren.
 * Der ToolOrchestrator nutzt diese Informationen, um automatisch Tool-Chains auszuführen.
 */
interface ToolDependencyContract
{
    /**
     * Gibt die Dependencies dieses Tools zurück
     * 
     * Format:
     * [
     *   'required_fields' => ['field1', 'field2'], // Felder, die benötigt werden
     *   'dependencies' => [
     *     [
     *       'tool_name' => 'core.teams.list',
     *       'condition' => function($arguments, $context) {
     *         // Gibt true zurück, wenn Dependency ausgeführt werden soll
     *         return empty($arguments['team_id']);
     *       },
     *       'args' => function($arguments, $context) {
     *         // Gibt Argumente für Dependency-Tool zurück
     *         return ['include_personal' => true];
     *       },
     *       'merge_result' => function($mainToolName, $depResult, $arguments) {
     *         // Merged Dependency-Ergebnis in Arguments (optional)
     *         // Wenn null zurückgegeben wird, wird Dependency-Ergebnis direkt zurückgegeben
     *         return $arguments; // oder null für direkte Rückgabe
     *       }
     *     ]
     *   ]
     * ]
     * 
     * @return array Dependency-Konfiguration
     */
    public function getDependencies(): array;
}

