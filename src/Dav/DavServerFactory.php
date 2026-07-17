<?php

namespace Platform\Core\Dav;

use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Server;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Baut einen konfigurierten, read-only DAV-`Server` aus allen registrierten
 * {@see \Platform\Core\Contracts\DavModuleInterface}-Modulen.
 *
 * Auth-, Principal- und Modul-Backends teilen sich einen {@see DavContext}: die
 * Auth schreibt das Abo hinein, die Backends lesen es und scopen darüber (ein
 * carddav-Abo sieht nur Adressbücher, caldav-Collections bleiben leer usw.).
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class DavServerFactory
{
    public function __construct(
        private readonly DavModuleRegistry $registry,
    ) {
    }

    public function make(string $baseUri): Server
    {
        $context = new DavContext();
        $authBackend = new TokenAuthBackend($context);
        $principalBackend = new PrincipalBackend($context);

        $tree = [new PrincipalCollection($principalBackend)];
        $plugins = [];

        foreach ($this->registry->all() as $module) {
            $tree[] = $module->rootNode($context, $principalBackend);
            foreach ($module->plugins() as $plugin) {
                // Nach Klasse deduplizieren (mehrere Module könnten dasselbe
                // Protokoll-Plugin beisteuern, z. B. zwei CardDAV-Module).
                $plugins[$plugin::class] = $plugin;
            }
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
