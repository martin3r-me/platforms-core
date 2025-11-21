<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\ApiController;

/**
 * Core API Routes
 * 
 * Diese Datei enthält die Basis-API-Routen des Cores.
 * Module sollten ihre eigenen API-Routen über ModuleRouter::apiGroup() registrieren.
 */

// Basis-Endpunkt: Aktueller Benutzer (mit Authentifizierung)
Route::middleware('api.auth')->get('/user', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $request->user()->id,
            'name' => $request->user()->name,
            'email' => $request->user()->email,
        ],
    ]);
});

// Health Check (ohne Authentifizierung)
Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API ist erreichbar',
        'timestamp' => now()->toIso8601String(),
    ]);
});

