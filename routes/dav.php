<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\Dav\DavController;

// DAV-Server (CardDAV/CalDAV). MUSS ohne Session-/Modul-Guard laufen — sabre
// bringt eigene HTTP-Basic-Auth mit (siehe Platform\Core\Dav\TokenAuthBackend).

$path = trim((string) config('dav.path', 'crm/dav'), '/');

// WICHTIG: Route::any() deckt KEINE WebDAV-Methoden ab. PROPFIND/REPORT/... müssen
// explizit gelistet werden, sonst antwortet Laravel mit 405, bevor der Controller
// erreicht wird. Schreibmethoden sind bewusst dabei, damit sabre sie sauber mit
// 403 ablehnt (statt Laravel-405-HTML).
$davMethods = [
    'GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS',
    'PROPFIND', 'PROPPATCH', 'REPORT', 'MKCOL', 'MOVE', 'COPY', 'LOCK', 'UNLOCK', 'ACL',
];

Route::match($davMethods, $path.'/{any?}', DavController::class)
    ->where('any', '.*')
    ->name('core.dav');

// Autodiscovery.
Route::get('.well-known/carddav', fn () => redirect('/'.$path, 301))->name('core.dav.wellknown.carddav');
Route::get('.well-known/caldav', fn () => redirect('/'.$path, 301))->name('core.dav.wellknown.caldav');
