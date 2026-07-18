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
