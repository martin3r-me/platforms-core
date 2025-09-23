<?php

namespace Platform\Core\Services;

use Platform\Core\Registry\CommandRegistry;

class CommandPolicy
{
    /**
     * Bestimmt, ob ein Intent ohne Rückfrage automatisch ausgeführt werden darf.
     * Leicht konfigurierbar über einfache Heuristik + Command-Metadaten.
     */
    public function isAutoAllowed(string $intent): bool
    {
        $intentLower = mb_strtolower($intent);
        foreach (['list', 'show', 'get', 'open', 'query'] as $keyword) {
            if (str_contains($intentLower, $keyword)) {
                return true;
            }
        }
        // Fallback: wenn Command als autoAllowed markiert ist
        $cmd = $this->findCommandByKey($intent);
        if (!empty($cmd) && ($cmd['autoAllowed'] ?? false) === true) {
            return true;
        }
        return false;
    }

    /**
     * Ob ein Command eine Bestätigung erfordert. Nutzt Registry-Metadaten.
     */
    public function requiresConfirm(array $command): bool
    {
        if (array_key_exists('confirmRequired', $command)) {
            return (bool) $command['confirmRequired'];
        }
        $impact = (string) ($command['impact'] ?? 'low');
        return in_array($impact, ['medium','high'], true);
    }

    protected function findCommandByKey(string $key): array
    {
        foreach (CommandRegistry::all() as $module => $cmds) {
            foreach ($cmds as $c) {
                if (($c['key'] ?? null) === $key) {
                    return $c;
                }
            }
        }
        return [];
    }
}

?>

