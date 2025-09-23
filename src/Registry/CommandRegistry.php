<?php

namespace Platform\Core\Registry;

/**
 * In-Memory Registry für Modul-Kommandos.
 * Module registrieren beim Booten ihre Kommandos; der Core liest hieraus.
 */
class CommandRegistry
{
    /**
     * @var array<string, array<int, array>>
     */
    protected static array $moduleKeyToCommands = [];
    /**
     * Mappt normalisierte Tool-Namen (OpenAI) zurück auf originale Command-Keys
     * @var array<string, string>
     */
    protected static array $toolNameToCommandKey = [];

    /**
     * Registriert eine Menge von Kommandos für ein Modul (überschreibt bestehende).
     *
     * @param string $moduleKey
     * @param array<int, array> $commands
     */
    public static function register(string $moduleKey, array $commands): void
    {
        // Optional: Schema-Validierung minimal absichern
        foreach ($commands as $cmd) {
            if (!isset($cmd['key'])) {
                throw new \InvalidArgumentException('Command requires key');
            }
            // Standard-Felder vorbelegen
            $cmd['description'] = $cmd['description'] ?? '';
            $cmd['parameters'] = $cmd['parameters'] ?? [];
            $cmd['impact'] = $cmd['impact'] ?? 'low'; // low|medium|high
            $cmd['confirmRequired'] = $cmd['confirmRequired'] ?? in_array(($cmd['impact'] ?? 'low'), ['medium','high'], true);
            // Optionale Felder: scope, examples, autoAllowed
            $cmd['scope'] = $cmd['scope'] ?? null; // z. B. read:planner, write:planner.tasks
            $cmd['examples'] = $cmd['examples'] ?? [];
            $commandsValidated[] = $cmd;
        }
        self::$moduleKeyToCommands[$moduleKey] = $commandsValidated ?? $commands;
    }

    public static function unregister(string $moduleKey): void
    {
        unset(self::$moduleKeyToCommands[$moduleKey]);
    }

    /**
     * @return array<string, array<int, array>>
     */
    public static function all(): array
    {
        return self::$moduleKeyToCommands;
    }

    /**
     * @return array<int, array>
     */
    public static function getByModule(string $moduleKey): array
    {
        return self::$moduleKeyToCommands[$moduleKey] ?? [];
    }

    /**
     * Export im Function-Calling-Stil (LLM-Tools):
     * [{ name, description, parameters: { type: 'object', properties: {...}, required: [...] } }]
     */
    public static function exportFunctionSchemas(): array
    {
        $tools = [];
        // Mapping bei jedem Export frisch aufbauen
        self::$toolNameToCommandKey = [];
        foreach (self::$moduleKeyToCommands as $moduleKey => $commands) {
            foreach ($commands as $cmd) {
                $name = $cmd['key'];
                // OpenAI tool name: only a-zA-Z0-9_-
                $toolName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
                // Rückwärts-Mapping sichern
                self::$toolNameToCommandKey[$toolName] = $name;
                $desc = trim(($cmd['description'] ?? '') . ' Module: ' . $moduleKey);
                $props = [];
                $required = [];
                foreach ($cmd['parameters'] as $p) {
                    $pname = $p['name'] ?? null;
                    if (!$pname) continue;
                    $ptype = $p['type'] ?? 'string';
                    $props[$pname] = [ 'type' => $ptype, 'description' => $p['description'] ?? '' ];
                    if (!empty($p['required'])) { $required[] = $pname; }
                }
                // OpenAI erwartet parameters.properties als JSON-Object, nicht leeres Array
                $propertiesObject = (object) $props;
                $schema = [
                    'name' => $toolName,
                    'description' => $desc,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $propertiesObject,
                        'required' => $required,
                    ],
                    // Zusatzinfos für interne Heuristik/Filter (vom LLM ignoriert)
                    'x-scope' => $cmd['scope'] ?? null,
                    'x-examples' => $cmd['examples'] ?? [],
                ];
                $tools[] = $schema;
            }
        }
        return $tools;
    }

    public static function resolveKeyFromToolName(string $toolName): ?string
    {
        return self::$toolNameToCommandKey[$toolName] ?? null;
    }

    public static function findCommandByKey(string $key): array
    {
        foreach (self::$moduleKeyToCommands as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }
}


