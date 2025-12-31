<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;

/**
 * Service für Rate Limiting & Throttling von Tools
 * 
 * Verhindert Überlastung durch zu viele Tool-Aufrufe
 */
class ToolRateLimitService
{
    private const CACHE_PREFIX = 'tool_rate_limit:';
    private const DEFAULT_WINDOW = 60; // 60 Sekunden

    /**
     * Prüft ob ein Tool-Aufruf erlaubt ist
     * 
     * @return array{allowed: bool, remaining: int, reset_at: int}
     */
    public function check(
        string $toolName,
        ToolContext $context,
        ?int $limit = null
    ): array {
        // Hole Limits aus Config
        $limits = $this->getLimits($toolName, $context);
        
        // Prüfe alle Limits (Tool, User, Team)
        $checks = [
            'tool' => $this->checkLimit('tool', $toolName, $limits['tool']),
            'user' => $context->user ? $this->checkLimit('user', (string)$context->user->id, $limits['user']) : null,
            'team' => $context->team ? $this->checkLimit('team', (string)$context->team->id, $limits['team']) : null,
        ];

        // Wenn ein Limit überschritten ist, blockiere
        foreach ($checks as $type => $check) {
            if ($check && !$check['allowed']) {
                Log::warning("[RateLimit] Limit überschritten", [
                    'type' => $type,
                    'tool' => $toolName,
                    'user_id' => $context->user?->id,
                    'team_id' => $context->team?->id,
                    'limit' => $check['limit'],
                    'remaining' => $check['remaining'],
                ]);

                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => $check['reset_at'],
                    'limit_type' => $type,
                    'limit' => $check['limit'],
                ];
            }
        }

        // Alle Limits OK - erhöhe Counter
        $this->incrementCounters($toolName, $context, $checks);

        // Finde das niedrigste remaining
        $minRemaining = min(array_filter(array_map(fn($c) => $c['remaining'] ?? PHP_INT_MAX, $checks)));
        $maxResetAt = max(array_filter(array_map(fn($c) => $c['reset_at'] ?? 0, $checks)));

        return [
            'allowed' => true,
            'remaining' => $minRemaining,
            'reset_at' => $maxResetAt,
        ];
    }

    /**
     * Holt Limits für Tool, User und Team
     */
    private function getLimits(string $toolName, ToolContext $context): array
    {
        return [
            'tool' => config("tools.tools.{$toolName}.rate_limit", config('tools.rate_limiting.default_limit', 100)),
            'user' => config('tools.rate_limiting.per_user_limit', 50),
            'team' => config('tools.rate_limiting.per_team_limit', 200),
        ];
    }

    /**
     * Prüft ein einzelnes Limit
     */
    private function checkLimit(string $type, string $identifier, int $limit): array
    {
        $cacheKey = self::CACHE_PREFIX . "{$type}:{$identifier}";
        $current = (int)Cache::get($cacheKey, 0);

        return [
            'allowed' => $current < $limit,
            'remaining' => max(0, $limit - $current),
            'limit' => $limit,
            'current' => $current,
            'reset_at' => now()->addSeconds(self::DEFAULT_WINDOW)->timestamp,
        ];
    }

    /**
     * Erhöht Counter für alle relevanten Limits
     */
    private function incrementCounters(string $toolName, ToolContext $context, array $checks): void
    {
        // Tool-Limit
        $toolKey = self::CACHE_PREFIX . "tool:{$toolName}";
        Cache::increment($toolKey);
        Cache::put($toolKey, Cache::get($toolKey, 0), now()->addSeconds(self::DEFAULT_WINDOW));

        // User-Limit
        if ($context->user) {
            $userKey = self::CACHE_PREFIX . "user:{$context->user->id}";
            Cache::increment($userKey);
            Cache::put($userKey, Cache::get($userKey, 0), now()->addSeconds(self::DEFAULT_WINDOW));
        }

        // Team-Limit
        if ($context->team) {
            $teamKey = self::CACHE_PREFIX . "team:{$context->team->id}";
            Cache::increment($teamKey);
            Cache::put($teamKey, Cache::get($teamKey, 0), now()->addSeconds(self::DEFAULT_WINDOW));
        }
    }

    /**
     * Prüft ob Rate Limiting aktiviert ist
     */
    public function isEnabled(): bool
    {
        return (bool)config('tools.rate_limiting.enabled', true);
    }

    /**
     * Resetet Limits für einen User/Team (Admin-Funktion)
     */
    public function reset(?int $userId = null, ?int $teamId = null): void
    {
        if ($userId) {
            $userKey = self::CACHE_PREFIX . "user:{$userId}";
            Cache::forget($userKey);
        }

        if ($teamId) {
            $teamKey = self::CACHE_PREFIX . "team:{$teamId}";
            Cache::forget($teamKey);
        }
    }
}

