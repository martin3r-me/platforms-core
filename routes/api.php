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

// Datawarehouse-Endpunkte für Teams, Users, Check-ins und Team Counter
Route::middleware('api.auth')->group(function () {
    Route::get('/teams/datawarehouse', [\Platform\Core\Http\Controllers\Api\TeamDatawarehouseController::class, 'index']);
    Route::get('/teams/datawarehouse/health', [\Platform\Core\Http\Controllers\Api\TeamDatawarehouseController::class, 'health']);
    Route::get('/users/datawarehouse', [\Platform\Core\Http\Controllers\Api\UserDatawarehouseController::class, 'index']);
    Route::get('/users/datawarehouse/health', [\Platform\Core\Http\Controllers\Api\UserDatawarehouseController::class, 'health']);
    Route::get('/checkins/datawarehouse', [\Platform\Core\Http\Controllers\Api\CheckinDatawarehouseController::class, 'index']);
    Route::get('/checkins/datawarehouse/health', [\Platform\Core\Http\Controllers\Api\CheckinDatawarehouseController::class, 'health']);
    Route::get('/team-counter-definitions/datawarehouse', [\Platform\Core\Http\Controllers\Api\TeamCounterDatawarehouseController::class, 'index']);
    Route::get('/team-counter-definitions/datawarehouse/health', [\Platform\Core\Http\Controllers\Api\TeamCounterDatawarehouseController::class, 'health']);
    Route::get('/team-counter-events/datawarehouse', [\Platform\Core\Http\Controllers\Api\TeamCounterEventDatawarehouseController::class, 'index']);
    Route::get('/team-counter-events/datawarehouse/health', [\Platform\Core\Http\Controllers\Api\TeamCounterEventDatawarehouseController::class, 'health']);
});

