<?php

namespace Platform\Core\Dav;

use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\Plugin as CalDavPlugin;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin as CardDavPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Server;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Baut einen read-only DAV-`Server` aus allen registrierten Modulen.
 *
 * Module werden pro Protokoll-Typ zusammengefasst: ALLE carddav-Module unter EINEM
 * {@see CompositeCardDavBackend}/AddressBookRoot, ALLE caldav-Module unter EINEM
 * {@see CompositeCalDavBackend}/CalendarRoot. So bedient ein einziger Account alle
 * Listen aller Module. Siehe modules/crm/docs/dav-core-extraction.md.
 */
class DavServerFactory
{
    public function __construct(
        private readonly DavModuleRegistry $registry,
    ) {
    }

    public function make(string $baseUri, ?string $handle = null): Server
    {
        $context = new DavContext();
        $authBackend = new TokenAuthBackend($context, $handle);
        $principalBackend = new PrincipalBackend($context);

        // Modul-Backends nach Typ sammeln (Key => Backend).
        $cardBackends = [];
        $calBackends = [];
        foreach ($this->registry->all() as $module) {
            if ($module->type() === 'carddav') {
                $cardBackends[$module->key()] = $module->backend($context);
            } elseif ($module->type() === 'caldav') {
                $calBackends[$module->key()] = $module->backend($context);
            }
        }

        $tree = [new PrincipalCollection($principalBackend)];
        $plugins = [];

        if ($cardBackends !== []) {
            $tree[] = new AddressBookRoot($principalBackend, new CompositeCardDavBackend($cardBackends, $context));
            $plugins[] = new CardDavPlugin();
        }

        if ($calBackends !== []) {
            $tree[] = new CalendarRoot($principalBackend, new CompositeCalDavBackend($calBackends, $context));
            $plugins[] = new CalDavPlugin();
        }

        // CapturingSapi fängt den Output ab -> exec() im Controller liefert eine
        // Laravel-Response statt direkt zu schreiben.
        $server = new Server($tree, new CapturingSapi());
        $server->setBaseUri($baseUri);

        // Auth zuerst — erzwingt gültiges Abo-Secret (401 sonst).
        $server->addPlugin(new AuthPlugin($authBackend));
        foreach ($plugins as $plugin) {
            $server->addPlugin($plugin);
        }

        $aclPlugin = new AclPlugin();
        $aclPlugin->allowUnauthenticatedAccess = false;
        $server->addPlugin($aclPlugin);

        return $server;
    }
}
