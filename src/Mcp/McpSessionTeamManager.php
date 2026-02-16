<?php

namespace Platform\Core\Mcp;

use Platform\Core\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verwaltet Session-spezifische Team-Kontext-Overrides für MCP
 *
 * Ermöglicht es LLM-Clients (Claude, Agents etc.), programmatisch
 * in ein anderes Team zu wechseln, ohne den UI-Kontext zu ändern.
 *
 * Der Override wird sowohl in-memory (schneller Zugriff im selben Prozess)
 * als auch im Cache (persistiert über HTTP-Requests hinweg) gespeichert.
 *
 * Sliding Window TTL: Jede Aktivität (Tool-Call, Team-Switch) verlängert
 * die Session-Dauer automatisch, damit bei langen Tool-Sequenzen der
 * Kontext nicht still abläuft.
 */
class McpSessionTeamManager
{
    /**
     * Team-Overrides pro Session (in-memory, schneller Zugriff)
     * @var array<string, int> sessionId => team_id
     */
    private static array $sessionTeamOverrides = [];

    /**
     * Letzte Aktivitäts-Timestamps pro Session (in-memory)
     * @var array<string, float> sessionId => microtime(true)
     */
    private static array $sessionLastActivity = [];

    /**
     * Cache-Prefix für Team-Overrides
     */
    private const CACHE_PREFIX = 'mcp_team_override:';

    /**
     * Cache-Prefix für Session-Aktivität
     */
    private const ACTIVITY_CACHE_PREFIX = 'mcp_session_activity:';

    /**
     * Cache-TTL in Sekunden (8 Stunden – typische Session-Dauer)
     */
    private const CACHE_TTL = 28800;

    /**
     * Setzt den Team-Override für eine Session
     */
    public static function setTeamOverride(string $sessionId, int $teamId): void
    {
        // In-memory für schnellen Zugriff im selben Prozess
        self::$sessionTeamOverrides[$sessionId] = $teamId;

        // Im Cache persistieren für Request-übergreifende Persistenz
        try {
            Cache::put(self::CACHE_PREFIX . $sessionId, $teamId, self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning('[MCP Session] Cache-Write für Team-Override fehlgeschlagen', [
                'session_id' => substr($sessionId, 0, 12) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        // Aktivität tracken (Sliding Window)
        self::touchSession($sessionId);

        Log::info('[MCP Session] Team-Kontext gewechselt', [
            'session_id' => substr($sessionId, 0, 12) . '...',
            'team_id' => $teamId,
        ]);
    }

    /**
     * Gibt das Override-Team für eine Session zurück (oder null)
     */
    public static function getTeamOverride(string $sessionId): ?Team
    {
        $teamId = self::getTeamOverrideId($sessionId);

        if ($teamId === null) {
            return null;
        }

        return Team::find($teamId);
    }

    /**
     * Gibt die Override-Team-ID für eine Session zurück (oder null)
     *
     * Prüft zuerst in-memory, dann im Cache (Request-übergreifend).
     */
    public static function getTeamOverrideId(string $sessionId): ?int
    {
        // 1. In-memory (schnell, selber Prozess)
        if (isset(self::$sessionTeamOverrides[$sessionId])) {
            return self::$sessionTeamOverrides[$sessionId];
        }

        // 2. Cache (persistiert über Requests hinweg)
        try {
            $teamId = Cache::get(self::CACHE_PREFIX . $sessionId);
            if ($teamId !== null) {
                // In-memory hydratisieren für schnellere Folge-Zugriffe
                self::$sessionTeamOverrides[$sessionId] = (int) $teamId;
                return (int) $teamId;
            }
        } catch (\Throwable $e) {
            Log::warning('[MCP Session] Cache-Read für Team-Override fehlgeschlagen', [
                'session_id' => substr($sessionId, 0, 12) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Prüft ob eine Session einen Team-Override hat
     *
     * Prüft zuerst in-memory, dann im Cache (Request-übergreifend).
     */
    public static function hasTeamOverride(string $sessionId): bool
    {
        // 1. In-memory (schnell)
        if (isset(self::$sessionTeamOverrides[$sessionId])) {
            return true;
        }

        // 2. Cache (persistiert über Requests)
        try {
            if (Cache::has(self::CACHE_PREFIX . $sessionId)) {
                // In-memory hydratisieren
                $teamId = Cache::get(self::CACHE_PREFIX . $sessionId);
                if ($teamId !== null) {
                    self::$sessionTeamOverrides[$sessionId] = (int) $teamId;
                }
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('[MCP Session] Cache-Check für Team-Override fehlgeschlagen', [
                'session_id' => substr($sessionId, 0, 12) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Entfernt den Team-Override für eine Session
     */
    public static function clearTeamOverride(string $sessionId): void
    {
        unset(self::$sessionTeamOverrides[$sessionId]);

        // Auch aus dem Cache entfernen
        try {
            Cache::forget(self::CACHE_PREFIX . $sessionId);
        } catch (\Throwable $e) {
            Log::warning('[MCP Session] Cache-Delete für Team-Override fehlgeschlagen', [
                'session_id' => substr($sessionId, 0, 12) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug('[MCP Session] Team-Override entfernt', [
            'session_id' => substr($sessionId, 0, 12) . '...',
        ]);
    }

    /**
     * Entfernt alle Daten für eine Session (bei Disconnect)
     */
    public static function clearSession(string $sessionId): void
    {
        self::clearTeamOverride($sessionId);
        unset(self::$sessionLastActivity[$sessionId]);

        try {
            Cache::forget(self::ACTIVITY_CACHE_PREFIX . $sessionId);
        } catch (\Throwable $e) {
            // Silent – Session wird ohnehin aufgeräumt
        }
    }

    /**
     * Aktualisiert den Aktivitäts-Timestamp für eine Session (Sliding Window)
     *
     * Wird bei jedem Tool-Call aufgerufen, um die Session am Leben zu halten.
     * Verlängert den Cache-TTL für den Team-Override, damit bei langen
     * Tool-Sequenzen der Kontext nicht still abläuft.
     */
    public static function touchSession(string $sessionId): void
    {
        $now = microtime(true);
        self::$sessionLastActivity[$sessionId] = $now;

        try {
            // Aktivitäts-Timestamp im Cache speichern
            Cache::put(self::ACTIVITY_CACHE_PREFIX . $sessionId, $now, self::CACHE_TTL);

            // Team-Override TTL erneuern (Sliding Window)
            if (isset(self::$sessionTeamOverrides[$sessionId])) {
                Cache::put(
                    self::CACHE_PREFIX . $sessionId,
                    self::$sessionTeamOverrides[$sessionId],
                    self::CACHE_TTL
                );
            }
        } catch (\Throwable $e) {
            // Silent – Aktivitäts-Tracking ist nicht kritisch
        }
    }

    /**
     * Gibt die Sekunden seit der letzten Aktivität zurück
     *
     * @return float|null Sekunden seit letzter Aktivität, null wenn keine Aktivität bekannt
     */
    public static function getSecondsSinceLastActivity(string $sessionId): ?float
    {
        $now = microtime(true);

        // 1. In-memory
        if (isset(self::$sessionLastActivity[$sessionId])) {
            return $now - self::$sessionLastActivity[$sessionId];
        }

        // 2. Cache
        try {
            $lastActivity = Cache::get(self::ACTIVITY_CACHE_PREFIX . $sessionId);
            if ($lastActivity !== null) {
                self::$sessionLastActivity[$sessionId] = (float) $lastActivity;
                return $now - (float) $lastActivity;
            }
        } catch (\Throwable $e) {
            // Silent
        }

        return null;
    }

    /**
     * Prüft ob eine Session bald abläuft
     *
     * @param int $warningThresholdSeconds Schwelle in Sekunden vor Ablauf (Standard: 30 Min)
     * @return bool true wenn die Session in weniger als $warningThresholdSeconds abläuft
     */
    public static function isSessionExpiringSoon(string $sessionId, int $warningThresholdSeconds = 1800): bool
    {
        $secondsSinceActivity = self::getSecondsSinceLastActivity($sessionId);
        if ($secondsSinceActivity === null) {
            return false;
        }

        $remainingSeconds = self::CACHE_TTL - $secondsSinceActivity;
        return $remainingSeconds > 0 && $remainingSeconds < $warningThresholdSeconds;
    }

    /**
     * Ermittelt die Session-ID aus dem aktuellen Auth-Context
     *
     * Verwendet dieselbe Logik wie DiscoveryMcpServer::resolveSessionId()
     */
    public static function resolveSessionId(): ?string
    {
        try {
            $user = auth()->user();
            if ($user && $user->id) {
                return 'mcp_user_' . $user->id;
            }
        } catch (\Throwable $e) {
            // Auth nicht verfügbar
        }

        return null;
    }
}
