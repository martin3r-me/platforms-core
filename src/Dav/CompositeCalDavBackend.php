<?php

namespace Platform\Core\Dav;

use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

/**
 * Aggregiert die CalDAV-Backends aller registrierten caldav-Module unter EINEM
 * CalendarRoot. So zeigt ein einziger CalDAV-Account alle Listen aller Module
 * (Planner-Aufgaben, Helpdesk-Tickets, …) zusammen.
 *
 * Collection-IDs/URIs werden per Modul-Key genamespaced, damit sabre die Objekt-
 * Zugriffe an das richtige Modul-Backend routen kann. Siehe docs/dav-core-extraction.md.
 */
class CompositeCalDavBackend extends AbstractBackend
{
    private const SEP = "\x1f"; // interner ID-Trenner (nicht URL-sichtbar)

    /**
     * @param  array<string, BackendInterface>  $backends  Modul-Key => CalDAV-Backend
     */
    public function __construct(
        private readonly array $backends,
        private readonly DavContext $context,
    ) {
    }

    /**
     * Ist ein Modul für das aktive Abo freigegeben? (module=null → alle).
     */
    private function allows(string $key): bool
    {
        $scope = $this->context->subscription()->module;

        return $scope === null || $scope === $key;
    }

    public function getCalendarsForUser($principalUri)
    {
        $calendars = [];

        foreach ($this->backends as $key => $backend) {
            if (! $this->allows($key)) {
                continue;
            }

            foreach ($backend->getCalendarsForUser($principalUri) as $calendar) {
                $calendar['id'] = $key.self::SEP.$calendar['id'];
                $calendar['uri'] = $key.'-'.$calendar['uri'];
                $calendars[] = $calendar;
            }
        }

        return $calendars;
    }

    public function getCalendarObjects($calendarId)
    {
        [$backend, $innerId] = $this->route($calendarId);

        return array_map(function (array $object) use ($calendarId) {
            // calendarid auf die zusammengesetzte ID zurücksetzen, damit
            // AbstractBackend::calendarQuery wieder über uns routet.
            $object['calendarid'] = $calendarId;

            return $object;
        }, $backend->getCalendarObjects($innerId));
    }

    public function getCalendarObject($calendarId, $objectUri)
    {
        [$backend, $innerId] = $this->route($calendarId);

        $object = $backend->getCalendarObject($innerId, $objectUri);
        if (is_array($object)) {
            $object['calendarid'] = $calendarId;
        }

        return $object;
    }

    // ---- read-only -------------------------------------------------

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        throw new Forbidden('Der Kalender ist schreibgeschützt.');
    }

    public function updateCalendar($calendarId, PropPatch $propPatch)
    {
        // Read-only.
    }

    public function deleteCalendar($calendarId)
    {
        throw new Forbidden('Der Kalender ist schreibgeschützt.');
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData)
    {
        throw new Forbidden('Der Kalender ist schreibgeschützt.');
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData)
    {
        throw new Forbidden('Der Kalender ist schreibgeschützt.');
    }

    public function deleteCalendarObject($calendarId, $objectUri)
    {
        throw new Forbidden('Der Kalender ist schreibgeschützt.');
    }

    /**
     * Zusammengesetzte Kalender-ID → [Modul-Backend, innere ID].
     *
     * @return array{0: BackendInterface, 1: string|int}
     */
    private function route($calendarId): array
    {
        $parts = explode(self::SEP, (string) $calendarId, 2);
        if (count($parts) !== 2 || ! isset($this->backends[$parts[0]]) || ! $this->allows($parts[0])) {
            throw new NotFound('Kalender nicht gefunden.');
        }

        return [$this->backends[$parts[0]], $parts[1]];
    }
}
