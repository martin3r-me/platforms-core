<?php

namespace Platform\Core\Services;

use Platform\Core\Schema\ModelSchemaRegistry;

class ModelNameResolver
{
    /**
     * Versucht aus freiem Text einen registrierten modelKey zu ermitteln.
     * 1) Synonyme (de/en), 2) exakte Keys, 3) Teilstring-Match auf label_key/name/title.
     */
    public function resolveFromText(string $text): ?string
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') return null;
        // Synonyme
        $synonyms = [
            'aufgaben' => 'planner.tasks',
            'aufgabe'  => 'planner.tasks',
            'tasks'    => 'planner.tasks',
            'projekte' => 'planner.projects',
            'projekt'  => 'planner.projects',
            'projects' => 'planner.projects',
        ];
        foreach ($synonyms as $needle => $modelKey) {
            if (str_contains($t, $needle)) {
                return $modelKey;
            }
        }
        // Registrierte Keys im Text
        foreach (ModelSchemaRegistry::keys() as $key) {
            if (str_contains($t, mb_strtolower($key))) {
                return $key;
            }
        }
        return null;
    }
}

?>

