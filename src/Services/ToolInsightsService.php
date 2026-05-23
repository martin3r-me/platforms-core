<?php

namespace Platform\Core\Services;

use Platform\Core\Models\ToolExecution;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ToolInsightsService
{
    private const CACHE_KEY_COOCCURRENCE = 'tool_insights:cooccurrence';
    private const CACHE_KEY_EXAMPLES = 'tool_insights:examples';
    private const CACHE_TTL = 3600; // 1 hour
    private const SESSION_GAP_MINUTES = 10;

    /**
     * Compute co-occurrence pairs from tool_executions using sliding window sessions.
     *
     * @return array<string, array<int, array{tool: string, count: int}>>
     */
    public function computeCooccurrence(int $days = 30, int $topN = 3): array
    {
        $since = now()->subDays($days);

        $executions = ToolExecution::query()
            ->where('success', true)
            ->where('created_at', '>', $since)
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->select(['tool_name', 'user_id', 'created_at'])
            ->get();

        // Build pairs using sliding window: same user, < 10 min gap
        $pairs = []; // tool_a => [tool_b => count]
        $prev = null;

        foreach ($executions as $exec) {
            if (
                $prev !== null
                && $prev->user_id === $exec->user_id
                && $prev->created_at->diffInMinutes($exec->created_at) < self::SESSION_GAP_MINUTES
            ) {
                $a = $prev->tool_name;
                $b = $exec->tool_name;
                if ($a !== $b) {
                    $pairs[$a][$b] = ($pairs[$a][$b] ?? 0) + 1;
                }
            }
            $prev = $exec;
        }

        // Keep top-N per tool
        $result = [];
        foreach ($pairs as $tool => $followers) {
            arsort($followers);
            $top = array_slice($followers, 0, $topN, true);
            $result[$tool] = [];
            foreach ($top as $follower => $count) {
                $result[$tool][] = ['tool' => $follower, 'count' => $count];
            }
        }

        return $result;
    }

    /**
     * Extract anonymized example argument sets from recent executions.
     *
     * @return array<string, array<int, array>>
     */
    public function computeExamples(int $days = 30, int $maxPerTool = 3): array
    {
        $since = now()->subDays($days);

        // Get distinct tool names with recent executions
        $toolNames = ToolExecution::query()
            ->where('success', true)
            ->where('created_at', '>', $since)
            ->whereNotNull('arguments')
            ->distinct()
            ->pluck('tool_name');

        $result = [];

        foreach ($toolNames as $toolName) {
            $executions = ToolExecution::query()
                ->where('success', true)
                ->where('tool_name', $toolName)
                ->where('created_at', '>', $since)
                ->whereNotNull('arguments')
                ->orderByDesc('created_at')
                ->limit($maxPerTool * 2) // fetch extra to deduplicate
                ->get(['arguments']);

            $seen = [];
            $examples = [];

            foreach ($executions as $exec) {
                $args = $exec->arguments;
                if (!is_array($args) || empty($args)) {
                    continue;
                }

                // Deduplicate by argument key signature
                $keySignature = implode(',', array_keys($args));
                if (isset($seen[$keySignature])) {
                    continue;
                }
                $seen[$keySignature] = true;

                $examples[] = $this->anonymizeArguments($args);

                if (count($examples) >= $maxPerTool) {
                    break;
                }
            }

            if (!empty($examples)) {
                $result[$toolName] = $examples;
            }
        }

        return $result;
    }

    /**
     * Rebuild both insights and store in cache.
     */
    public function rebuild(int $days = 30): void
    {
        try {
            $cooccurrence = $this->computeCooccurrence($days);
            Cache::put(self::CACHE_KEY_COOCCURRENCE, $cooccurrence, self::CACHE_TTL);

            $examples = $this->computeExamples($days);
            Cache::put(self::CACHE_KEY_EXAMPLES, $examples, self::CACHE_TTL);

            Log::info('[ToolInsights] Rebuild complete', [
                'cooccurrence_tools' => count($cooccurrence),
                'example_tools' => count($examples),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ToolInsights] Rebuild failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get co-occurrence data for a specific tool from cache.
     *
     * @return array<int, array{tool: string, count: int}>
     */
    public function getCooccurrence(string $toolName): array
    {
        $all = Cache::get(self::CACHE_KEY_COOCCURRENCE, []);

        return $all[$toolName] ?? [];
    }

    /**
     * Get example argument sets for a specific tool from cache.
     *
     * @return array<int, array>
     */
    public function getExamples(string $toolName): array
    {
        $all = Cache::get(self::CACHE_KEY_EXAMPLES, []);

        return $all[$toolName] ?? [];
    }

    /**
     * Anonymize argument values while preserving structure.
     */
    private function anonymizeArguments(array $args): array
    {
        $result = [];

        foreach ($args as $key => $value) {
            $result[$key] = $this->anonymizeValue($value);
        }

        return $result;
    }

    /**
     * Anonymize a single value based on its type.
     */
    private function anonymizeValue(mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value > 0 ? 123 : 0;
        }

        if (is_float($value)) {
            return 1.0;
        }

        if (is_string($value)) {
            // Email pattern
            if (str_contains($value, '@') && str_contains($value, '.')) {
                return 'user@example.com';
            }

            // UUID pattern
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $value)) {
                return '00000000-0000-0000-0000-000000000000';
            }

            // Numeric ID string
            if (ctype_digit($value) && strlen($value) > 0) {
                return '123';
            }

            // Truncate long strings, keep short ones
            if (mb_strlen($value) > 50) {
                return mb_substr($value, 0, 50) . '...';
            }

            return $value;
        }

        if (is_array($value)) {
            // Associative array: anonymize values recursively
            if (array_is_list($value)) {
                return array_map(fn($v) => $this->anonymizeValue($v), $value);
            }

            return $this->anonymizeArguments($value);
        }

        return '(redacted)';
    }
}
