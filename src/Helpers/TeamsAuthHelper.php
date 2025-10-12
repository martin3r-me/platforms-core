<?php

namespace Platform\Core\Helpers;

use Illuminate\Http\Request;

class TeamsAuthHelper
{
    /**
     * Holt die Teams User-Info aus dem Request (ohne Laravel Auth)
     */
    public static function getTeamsUser(Request $request): ?array
    {
        return $request->attributes->get('teams_user');
    }

    /**
     * Holt den Teams Context aus dem Request
     */
    public static function getTeamsContext(Request $request): ?array
    {
        return $request->attributes->get('teams_context');
    }

    /**
     * Prüft ob der Request von Teams kommt
     */
    public static function isTeamsRequest(Request $request): bool
    {
        return $request->attributes->get('teams_user') !== null;
    }

    /**
     * Holt die User-Email aus Teams Context
     */
    public static function getTeamsUserEmail(Request $request): ?string
    {
        $user = self::getTeamsUser($request);
        return $user['email'] ?? null;
    }

    /**
     * Holt den User-Namen aus Teams Context
     */
    public static function getTeamsUserName(Request $request): ?string
    {
        $user = self::getTeamsUser($request);
        return $user['name'] ?? $user['email'] ?? null;
    }
}
