<?php

namespace Platform\Core\Contracts;

/**
 * Standard-Interface für alle Tools im System
 * 
 * Jedes Tool muss dieses Interface implementieren, um registriert und ausgeführt werden zu können.
 */
interface ToolContract
{
    /**
     * Eindeutiger Name des Tools (z.B. 'data_read', 'planner.tasks.create')
     */
    public function getName(): string;

    /**
     * Beschreibung des Tools für die AI
     * Sollte klar beschreiben, was das Tool macht und wann es verwendet werden sollte
     */
    public function getDescription(): string;

    /**
     * JSON Schema für die Parameter-Validierung
     * Format: https://json-schema.org/
     * 
     * @return array JSON Schema Definition
     */
    public function getSchema(): array;

    /**
     * Führt das Tool aus
     * 
     * @param array $arguments Die validierten Argumente
     * @param ToolContext $context Kontext (User, Team, etc.)
     * @return ToolResult Ergebnis der Ausführung
     */
    public function execute(array $arguments, ToolContext $context): ToolResult;
}

