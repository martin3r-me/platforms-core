<?php

namespace Platform\Core\Services;

use Platform\Core\PlatformCore;

class CoreAgentService
{
    // $slots: ['destination' => 'planner']
    public function transferAgent(array $slots): array
    {
        $dest = $slots['destination'] ?? null;
        if (!$dest) {
            return ['ok' => false, 'message' => 'Ziel fehlt'];
        }
        $module = PlatformCore::getModule($dest);
        if (!$module) {
            return ['ok' => false, 'message' => 'Unbekannter Agent/Modul'];
        }
        $url = $module['url'] ?? null;
        if (!$url) {
            return ['ok' => false, 'message' => 'Ziel-URL fehlt'];
        }
        return ['ok' => true, 'navigate' => $url, 'message' => 'Wechsele zu '.$dest];
    }
}
