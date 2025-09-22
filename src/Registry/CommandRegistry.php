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
        }
        self::$moduleKeyToCommands[$moduleKey] = $commands;
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
}


