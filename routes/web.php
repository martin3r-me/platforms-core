<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


Route::post('/logout', function () {
    $guard = Auth::getDefaultDriver();

    Log::info('Logout triggered', [
        'guard' => $guard,
        'user'  => Auth::guard($guard)->user()?->id,
    ]);

    Auth::guard($guard)->logout();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

// (Teams Tab Test-Routen entfernt)

Route::get('/embedded/config', function () {
    return view('platform::embedded.config');
})->name('embedded.config');

Route::get('/embedded/config/okrs', function () {
    // Platzhalterseite – später: OKR-Auswahl (Teams-Tab-Konfiguration)
    return view('platform::embedded.config-okrs');
})->name('embedded.config.okrs');

Route::get('/embedded/config/helpdesk', function () {
    // Platzhalterseite – später: Helpdesk-Board-Auswahl
    return view('platform::embedded.config-helpdesk');
})->name('embedded.config.helpdesk');

