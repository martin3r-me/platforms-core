<?php

namespace Platform\Core\Contracts;

use Platform\Core\Dav\DavContext;

/**
 * Ein Modul, das WebDAV-Collections über die Core-DAV-Infrastruktur bereitstellt
 * (z. B. CRM → CardDAV/Adressbücher, Planner/Helpdesk → CalDAV/Aufgaben+Tickets).
 *
 * Der Core aggregiert ALLE Module eines Typs unter EINEM Wurzelknoten (ein
 * AddressBookRoot für alle carddav, ein CalendarRoot für alle caldav) — so zeigt
 * ein einziger Account alle Listen aller Module. Das Modul liefert nur seinen
 * Protokoll-Backend (welche Collections/Objekte, samt Sichtbarkeit).
 *
 * Module registrieren sich via {@see \Platform\Core\Dav\DavModuleRegistry}.
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
interface DavModuleInterface
{
    /** Modul-Diskriminator, dient als Namespace für Collection-IDs/URIs (z. B. 'crm'). */
    public function key(): string;

    /** Protokoll-Typ: 'carddav' | 'caldav'. */
    public function type(): string;

    /**
     * Der Protokoll-Backend des Moduls:
     * - type 'carddav' → \Sabre\CardDAV\Backend\BackendInterface
     * - type 'caldav'  → \Sabre\CalDAV\Backend\BackendInterface
     *
     * Bekommt den geteilten {@see DavContext} (enthält das authentifizierte Abo).
     */
    public function backend(DavContext $context): object;
}
