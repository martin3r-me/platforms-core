<?php

namespace Platform\Core\Contracts;

use Platform\Core\Dav\DavContext;
use Platform\Core\Dav\PrincipalBackend;
use Sabre\DAV\ICollection;

/**
 * Ein Modul, das WebDAV-Collections über die Core-DAV-Infrastruktur bereitstellt
 * (z. B. CRM → CardDAV/Adressbücher, Planner → CalDAV/Aufgaben).
 *
 * Module registrieren ihre Implementierung via {@see \Platform\Core\Dav\DavModuleRegistry}.
 * Der Core übernimmt Protokoll, Auth, Routing und Team-Kontext; das Modul liefert
 * nur den Wurzelknoten (welche Collections/Objekte) samt Sichtbarkeits-Scoping.
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
interface DavModuleInterface
{
    /** Modul-Diskriminator, muss zu DavSubscription.module passen (z. B. 'crm'). */
    public function key(): string;

    /** Protokoll-Typ, muss zu DavSubscription.type passen ('carddav' | 'caldav'). */
    public function type(): string;

    /**
     * Der Wurzelknoten des Moduls (z. B. AddressBookRoot / CalendarRoot),
     * verdrahtet mit dem Modul-eigenen Backend.
     */
    public function rootNode(DavContext $context, PrincipalBackend $principals): ICollection;

    /**
     * Protokoll-Plugins, die der Server braucht, z. B. [new \Sabre\CardDAV\Plugin()].
     *
     * @return array<int, \Sabre\DAV\ServerPlugin>
     */
    public function plugins(): array;
}
