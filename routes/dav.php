<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\Dav\DavController;

// DAV-Server (CardDAV/CalDAV). MUSS ohne Session-/Modul-Guard laufen — sabre
// bringt eigene HTTP-Basic-Auth mit (siehe Platform\Core\Dav\TokenAuthBackend).

$path = trim((string) config('dav.path', 'dav'), '/');

// WICHTIG: Route::any() deckt KEINE WebDAV-Methoden ab. PROPFIND/REPORT/... müssen
// explizit gelistet werden. Schreibmethoden sind bewusst dabei, damit sabre sie
// sauber mit 403 ablehnt (statt Laravel-405-HTML).
$davMethods = [
    'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS',
    'PROPFIND', 'PROPPATCH', 'REPORT', 'MKCOL', 'MOVE', 'COPY', 'LOCK', 'UNLOCK', 'ACL',
];

// {handle} = öffentlicher Identifier je Abo -> eigene URL -> eigener iOS-Account.
Route::match($davMethods, $path.'/{handle}/{any?}', DavController::class)
    ->where('handle', '[A-Za-z0-9]+')
    ->where('any', '.*')
    ->name('core.dav');

// TEMP-Diagnose (iOS-Erinnerungen): token-geschützter Zugriff auf die letzten
// DAV-Requests. Nach der Fehlersuche wieder entfernen.
Route::get('dav-debug/{token}', function (string $token) {
    if (! hash_equals('z9Kq3Wp7Lm2Rt5xB', $token)) {
        abort(404);
    }

    $file = storage_path('logs/dav-debug.log');

    if (request()->boolean('clear')) {
        @file_put_contents($file, '');

        return response('cleared', 200, ['Content-Type' => 'text/plain']);
    }

    return response(
        is_file($file) ? (file_get_contents($file) ?: '(leer)') : '(noch nichts)',
        200,
        ['Content-Type' => 'text/plain; charset=utf-8'],
    );
})->name('core.dav.debug');
