<?php

namespace Platform\Core\Http\Controllers\Dav;

use Illuminate\Http\Request;
use Platform\Core\Dav\DavServerFactory;
use Sabre\HTTP\Request as SabreRequest;
use Sabre\HTTP\Response as SabreResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridge zwischen Laravel und dem Sabre-DAV-Server.
 *
 * Statt sabre direkt in den Output schreiben zu lassen (headers-already-sent),
 * fahren wir `exec()` mit einer {@see \Platform\Core\Dav\CapturingSapi} und mappen
 * die Sabre-Response zurück auf eine Laravel-/Symfony-Response. `exec()` liefert
 * dabei die volle Pipeline inkl. Exception→DAV-XML (401/403/404).
 *
 * Siehe modules/crm/docs/dav-core-extraction.md.
 */
class DavController
{
    public function __invoke(Request $request, string $handle): Response
    {
        $path = trim((string) config('dav.path', 'dav'), '/');
        // Jedes Abo hat seine eigene URL: /dav/{handle}/… -> eigener iOS-Account.
        $baseUri = '/'.$path.'/'.$handle.'/';

        $server = app(DavServerFactory::class)->make($baseUri, $handle);

        $server->httpRequest = new SabreRequest(
            $request->getMethod(),
            $request->getRequestUri(),
            $this->headers($request),
            $request->getContent(),
        );
        $server->httpResponse = new SabreResponse();

        $server->exec();

        $sabreResponse = $server->httpResponse;

        // TEMP-Diagnose: welche Requests fährt der Client (iOS/Erinnerungen)?
        // Abrufbar über /dav-debug/{token}. Nach der iOS-Fehlersuche entfernen.
        $line = sprintf(
            '%s  %-9s %s -> %d | UA: %s',
            now()->format('H:i:s'),
            $request->getMethod(),
            $request->getRequestUri(),
            $sabreResponse->getStatus(),
            (string) $request->userAgent(),
        );
        \Illuminate\Support\Facades\Log::info('[DAV] '.$line);
        $debugFile = storage_path('logs/dav-debug.log');
        if (is_file($debugFile) && filesize($debugFile) > 300000) {
            @file_put_contents($debugFile, '');
        }
        @file_put_contents($debugFile, $line."\n", FILE_APPEND | LOCK_EX);

        return response(
            $sabreResponse->getBodyAsString(),
            $sabreResponse->getStatus(),
            $sabreResponse->getHeaders(),
        );
    }

    /**
     * @return array<string, string[]>
     */
    private function headers(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = $values;
        }

        return $headers;
    }
}
