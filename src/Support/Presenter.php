<?php

namespace Platform\Core\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Presenter-Kanal: Echtzeit-Kommentare, die als Sprechblasen-Overlay in den Browsern
 * eines Teams erscheinen (per Livewire-Polling abgeholt). Getriggert von aussen ueber
 * das MCP-Tool core.presenter.POST oder den Token-Endpoint POST /api/presenter/push.
 *
 * Bewusst Cache-basiert (kein Broadcasting/WebSocket): null Infrastruktur. Das Overlay
 * pollt alle ~1,5s — fuer eine gefuehrte Demo / einen Screencast voellig ausreichend.
 * Voraussetzung: ein geteilter Cache-Treiber (redis/database/file), nicht "array".
 */
class Presenter
{
    private const TTL_HOURS = 6;

    /**
     * Neuen Kommentar in den Kanal des Teams legen. Gibt die laufende Sequenz-ID zurueck
     * (das Overlay zeigt nur Nachrichten mit id > zuletzt-gesehener id).
     */
    public static function push(int $teamId, string $message, ?string $title = null, string $speaker = 'Claude', int $duration = 9): int
    {
        $id = (int) Cache::get(self::seqKey($teamId), 0) + 1;
        Cache::put(self::seqKey($teamId), $id, now()->addHours(self::TTL_HOURS));

        Cache::put(self::key($teamId), [
            'id'       => $id,
            'message'  => $message,
            'title'    => $title,
            'speaker'  => $speaker,
            'duration' => $duration,
            'ts'       => now()->timestamp,
        ], now()->addHours(self::TTL_HOURS));

        return $id;
    }

    /** Aktuellster Kommentar des Teams (oder null). */
    public static function latest(int $teamId): ?array
    {
        return Cache::get(self::key($teamId));
    }

    /** Kanal leeren (Overlay verschwindet beim naechsten Poll nicht — nur keine neue Nachricht mehr). */
    public static function clear(int $teamId): void
    {
        Cache::forget(self::key($teamId));
    }

    private static function key(int $teamId): string
    {
        return "presenter:{$teamId}:current";
    }

    private static function seqKey(int $teamId): string
    {
        return "presenter:{$teamId}:seq";
    }
}
