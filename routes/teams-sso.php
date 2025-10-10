<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\TeamsSsoController;

// Teams SSO Routes - ohne Auth-Middleware (für automatische Anmeldung)
Route::middleware('web')->group(function () {
    Route::post('teams/sso/authenticate', [TeamsSsoController::class, 'authenticate'])
         ->name('teams-sso.authenticate');
    
    Route::get('teams/sso/status', [TeamsSsoController::class, 'getAuthStatus'])
         ->name('teams-sso.status');
});
