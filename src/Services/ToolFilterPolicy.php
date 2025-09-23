<?php

namespace Platform\Core\Services;

use Platform\Core\Registry\CommandRegistry;

class ToolFilterPolicy
{
    public function filter(array $schemas, string $userText = ''): array
    {
        $mode = config('agent.mode', 'read_only');
        $include = (array) config('agent.include', []);
        $exclude = (array) config('agent.exclude', []);
        $minScore = (float) config('agent.min_score', 0.0);
        $scopesByRole = (array) config('agent.scopes', []);

        $text = mb_strtolower($userText ?? '');
        $weightsCfg = (array) config('agent.weights', []);

        $filtered = [];
        foreach ($schemas as $schema) {
            $toolName = (string) ($schema['name'] ?? '');
            $commandKey = CommandRegistry::resolveKeyFromToolName($toolName) ?? $toolName;
            // include/exclude
            if (!$this->matchesAny($commandKey, $include)) {
                continue;
            }
            if ($this->matchesAny($commandKey, $exclude)) {
                continue;
            }
            // Scope-Prüfung (optional): user->role -> erlaubte Scopes
            $requiredScope = (string) ($schema['x-scope'] ?? '');
            if ($requiredScope !== '' && !$this->isScopeAllowed($requiredScope, $scopesByRole)) {
                continue;
            }
            // Modus: navigation_only → nur *show und exportierte GET-Routen (Heuristik)
            if ($mode === 'navigation_only' && !$this->looksLikeNavigation($commandKey)) {
                continue;
            }
            // read_only → keine create/update/delete
            if ($mode === 'read_only' && $this->looksLikeMutation($commandKey)) {
                continue;
            }
            // Score durch Keyword-Gewichte
            $score = 1.0;
            foreach ($weightsCfg as $kw => $cfg) {
                $kw = (string) $kw;
                $boosts = (array) ($cfg[0] ?? []);
                $weight = (float) ($cfg[1] ?? 1.0);
                if ($kw !== '' && str_contains($text, mb_strtolower($kw))) {
                    foreach ($boosts as $prefix) {
                        if (str_contains(mb_strtolower($commandKey), mb_strtolower($prefix))) {
                            $score *= $weight;
                            break;
                        }
                    }
                }
            }
            if ($score < $minScore) {
                continue;
            }
            $schema['x-score'] = $score;
            $filtered[] = $schema;
        }

        // Re-Ranking nach Score
        usort($filtered, function($a, $b){
            $sa = (float) ($a['x-score'] ?? 1.0);
            $sb = (float) ($b['x-score'] ?? 1.0);
            return $sb <=> $sa;
        });

        return $filtered;
    }

    protected function matchesAny(string $key, array $patterns): bool
    {
        if (empty($patterns)) return true;
        foreach ($patterns as $p) {
            $regex = '/^' . str_replace(['*','?'], ['.*','.?'], preg_quote((string) $p, '/')) . '$/i';
            if (preg_match($regex, $key)) {
                return true;
            }
        }
        return false;
    }

    protected function looksLikeNavigation(string $key): bool
    {
        $k = mb_strtolower($key);
        foreach (['open','show','dashboard'] as $n) {
            if (str_contains($k, $n)) return true;
        }
        return false;
    }

    protected function looksLikeMutation(string $key): bool
    {
        $k = mb_strtolower($key);
        foreach (['create','update','store','destroy','delete','patch','put','post'] as $m) {
            if (str_contains($k, $m)) return true;
        }
        return false;
    }

    protected function isScopeAllowed(string $required, array $scopesByRole): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        $roles = method_exists($user, 'getRoleNames') ? (array) $user->getRoleNames() : [(string) ($user->role ?? 'user')];
        $allowed = [];
        foreach ($roles as $role) {
            $allowed = array_merge($allowed, (array) ($scopesByRole[$role] ?? []));
        }
        if (in_array('*', $allowed, true)) return true;
        // Wildcard-Matching für Scopes: read:* etc.
        return $this->matchesAny($required, $allowed);
    }
}

?>

