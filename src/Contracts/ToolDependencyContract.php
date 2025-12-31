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
     * NEUES DSL-Format (empfohlen):
     * [
     *   'dependencies' => [
     *     [
     *       'requires' => ['team_id'], // Fehlende Felder
     *       'resolver_tool' => 'core.teams.list',
     *       'select_strategy' => 'auto_if_single|ask_user|fail',
     *       'map' => [
     *         'team_id' => '$.teams[0].id', // JSONPath für Mapping
     *       ]
     *     ]
     *   ]
     * ]
     * 
     * ALTES Format (für Backwards-Kompatibilität):
     * [
     *   'required_fields' => ['field1', 'field2'],
     *   'dependencies' => [
     *     [
     *       'tool_name' => 'core.teams.list',
     *       'condition' => function($arguments, $context) { ... },
     *       'args' => function($arguments, $context) { ... },
     *       'merge_result' => function($mainToolName, $depResult, $arguments) { ... }
     *     ]
     *   ]
     * ]
     * 
     * @return array Dependency-Konfiguration
     */
    public function getDependencies(): array;
}

