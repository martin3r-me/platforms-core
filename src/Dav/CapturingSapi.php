<?php

namespace Platform\Core\Dav;

use Sabre\HTTP\Request;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\Sapi;

/**
 * Sapi, die den Output abfängt, statt ihn direkt zu senden.
 *
 * So kann {@see \Sabre\DAV\Server::exec()} die volle Pipeline fahren — inklusive
 * Exception→DAV-XML-Konvertierung (401/403/404 statt PHP-Fatals) — ohne selbst
 * Header/Body auszugeben. Die fertige Response bleibt in `$server->httpResponse`
 * und wird vom {@see \Platform\Core\Http\Controllers\Dav\DavController} als
 * Laravel-Response zurückgegeben.
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class CapturingSapi extends Sapi
{
    public static function getRequest(): Request
    {
        // Wird im Server-Konstruktor aufgerufen und danach überschrieben —
        // hier bewusst KEIN Zugriff auf PHP-Globals/php://input.
        return new Request('GET', '/');
    }

    public static function sendResponse(ResponseInterface $response)
    {
        // no-op: die Response wird über Laravel ausgeliefert.
    }
}
