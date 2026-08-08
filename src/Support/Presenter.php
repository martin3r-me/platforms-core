<?php

namespace Platform\Core\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Presenter-Kanal: Echtzeit-Regie-Schritte, die als Sprechblasen-Overlay in den Browsern
 * eines Teams erscheinen (per Livewire-Polling abgeholt). Getriggert von aussen ueber das
 * MCP-Tool core.presenter.POST oder den Token-Endpoint POST /api/presenter/push.
 *
 * Ein Schritt kann NAVIGIEREN (navigate-URL) und traegt ein server-seitiges "acknowledged"-
 * Flag: die Blase bleibt ueber Seitenwechsel hinweg stehen, bis der Zuschauer "Verstanden"
 * klickt. So laesst sich eine gefuehrte Tour manuell (Schritt fuer Schritt gepusht) fahren —
 * und spaeter aus einem Regie-Modul heraus.
 *
 * Bewusst Cache-basiert (kein Broadcasting): null Infrastruktur. Voraussetzung ist ein
 * geteilter Cache-Treiber (redis/database/file), nicht "array".
 */
class Presenter
{
    private const TTL_HOURS = 6;

    /**
     * Neuen Regie-Schritt in den Kanal des Teams legen. Ersetzt den vorigen (acknowledged
     * wird zurueckgesetzt). Gibt die laufende Sequenz-ID zurueck.
     */
    public static function push(int $teamId, string $message, ?string $title = null, string $speaker = 'Claude', ?string $navigate = null): int
    {
        $id = (int) Cache::get(self::seqKey($teamId), 0) + 1;
        Cache::put(self::seqKey($teamId), $id, now()->addHours(self::TTL_HOURS));

        Cache::put(self::key($teamId), [
            'id'           => $id,
            'message'      => $message,
            'title'        => $title,
            'speaker'      => $speaker,
            'navigate'     => $navigate ?: null,
            'acknowledged' => false,
            'ts'           => now()->timestamp,
        ], now()->addHours(self::TTL_HOURS));

        return $id;
    }

    /** Aktueller Schritt des Teams (oder null). */
    public static function latest(int $teamId): ?array
    {
        return Cache::get(self::key($teamId));
    }

    /**
     * Schritt als bestaetigt markieren (Zuschauer hat "Verstanden" geklickt). Bleibt im
     * Cache, wird aber nicht mehr angezeigt und loest keine Navigation mehr aus.
     */
    public static function acknowledge(int $teamId, int $id): void
    {
        $current = self::latest($teamId);
        if ($current && (int) ($current['id'] ?? 0) === $id) {
            $current['acknowledged'] = true;
            Cache::put(self::key($teamId), $current, now()->addHours(self::TTL_HOURS));
        }
    }

    /** Kanal komplett leeren. */
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
