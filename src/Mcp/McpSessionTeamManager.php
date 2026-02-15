<?php

namespace Platform\Core\Mcp;

use Platform\Core\Models\Team;
use Illuminate\Support\Facades\Log;

/**
 * Verwaltet Session-spezifische Team-Kontext-Overrides für MCP
 *
 * Ermöglicht es LLM-Clients (Claude, Agents etc.), programmatisch
 * in ein anderes Team zu wechseln, ohne den UI-Kontext zu ändern.
 *
 * Der Override gilt nur für die MCP-Session (in-memory, nicht persistent).
 */
class McpSessionTeamManager
{
    /**
     * Team-Overrides pro Session
     * @var array<string, int> sessionId => team_id
     */
    private static array $sessionTeamOverrides = [];

    /**
     * Setzt den Team-Override für eine Session
     */
    public static function setTeamOverride(string $sessionId, int $teamId): void
    {
        self::$sessionTeamOverrides[$sessionId] = $teamId;

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
        $teamId = self::$sessionTeamOverrides[$sessionId] ?? null;

        if ($teamId === null) {
            return null;
        }

        return Team::find($teamId);
    }

    /**
     * Gibt die Override-Team-ID für eine Session zurück (oder null)
     */
    public static function getTeamOverrideId(string $sessionId): ?int
    {
        return self::$sessionTeamOverrides[$sessionId] ?? null;
    }

    /**
     * Prüft ob eine Session einen Team-Override hat
     */
    public static function hasTeamOverride(string $sessionId): bool
    {
        return isset(self::$sessionTeamOverrides[$sessionId]);
    }

    /**
     * Entfernt den Team-Override für eine Session
     */
    public static function clearTeamOverride(string $sessionId): void
    {
        unset(self::$sessionTeamOverrides[$sessionId]);

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
