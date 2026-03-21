<?php

namespace Platform\Core\Services;

use Platform\Core\Models\Team;

/**
 * Request-scoped Team-Override für MCP/Playground-Kontext.
 *
 * Erlaubt es, das aktive Team temporär zu überschreiben, ohne
 * current_team_id in der DB zu ändern. User::currentTeam prüft
 * diesen Override automatisch.
 *
 * Wird vom ToolContractAdapter gesetzt (MCP) und vom
 * SimpleToolController (Playground).
 */
class TeamContext
{
    private static ?int $overrideTeamId = null;
    private static ?Team $overrideTeam = null;

    /**
     * Setzt den Team-Override (in-memory, kein DB-Write).
     */
    public static function set(?int $teamId): void
    {
        self::$overrideTeamId = $teamId;
        self::$overrideTeam = null; // Lazy-load bei Bedarf
    }

    /**
     * Setzt den Team-Override mit Team-Objekt (vermeidet extra Query).
     */
    public static function setTeam(?Team $team): void
    {
        self::$overrideTeamId = $team?->id;
        self::$overrideTeam = $team;
    }

    /**
     * Gibt die Override-Team-ID zurück (oder null wenn kein Override).
     */
    public static function getOverrideId(): ?int
    {
        return self::$overrideTeamId;
    }

    /**
     * Gibt das Override-Team zurück (oder null wenn kein Override).
     */
    public static function getOverrideTeam(): ?Team
    {
        if (self::$overrideTeamId === null) {
            return null;
        }

        if (self::$overrideTeam === null || self::$overrideTeam->id !== self::$overrideTeamId) {
            self::$overrideTeam = Team::find(self::$overrideTeamId);
        }

        return self::$overrideTeam;
    }

    /**
     * Entfernt den Override.
     */
    public static function clear(): void
    {
        self::$overrideTeamId = null;
        self::$overrideTeam = null;
    }

    /**
     * Prüft ob ein Override aktiv ist.
     */
    public static function hasOverride(): bool
    {
        return self::$overrideTeamId !== null;
    }
}
