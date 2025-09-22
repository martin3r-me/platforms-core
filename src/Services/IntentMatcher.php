<?php

namespace Platform\Core\Services;

use Platform\Core\Registry\CommandRegistry;

class IntentMatcher
{
    public function match(string $text): ?array
    {
        $textNorm = $this->normalize($text);
        $best = null;
        foreach (CommandRegistry::all() as $moduleKey => $commands) {
            foreach ($commands as $cmd) {
                $phrases = $cmd['phrases'] ?? [];
                foreach ($phrases as $phrase) {
                    $pattern = $this->buildPattern($phrase, $slots);
                    if (preg_match($pattern, $textNorm, $m)) {
                        $extracted = [];
                        foreach ($slots as $slotName) {
                            $extracted[$slotName] = $m[$slotName] ?? null;
                        }
                        $best = [
                            'module' => $moduleKey,
                            'command' => $cmd,
                            'slots' => $extracted,
                            'score' => 1.0,
                        ];
                        return $best; // MVP: first match
                    }
                }
            }
        }
        return $best;
    }

    protected function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = trim($s);
        return $s;
    }

    /**
     * Baut ein Regex für eine Phrase mit {slot}-Platzhaltern.
     * @param string $phrase
     * @param array $outSlots Referenz: befüllte Slot-Namen
     */
    protected function buildPattern(string $phrase, ?array &$outSlots = []): string
    {
        $outSlots = [];
        $quoted = preg_quote(mb_strtolower($phrase), '/');
        // Ersetze \{slot\} zurück zu {slot}
        $quoted = preg_replace('/\\\\\{(.*?)\\\\\}/', '{$1}', $quoted);
        // Ersetze {name} → (?P<name>.+)
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($m) use (&$outSlots) {
            $outSlots[] = $m[1];
            return '(?P<' . $m[1] . '>.+)';
        }, $quoted);
        return '/^' . $pattern . '$/u';
    }
}


