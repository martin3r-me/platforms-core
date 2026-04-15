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

// Test-Endpoint: POST echo + log to file
Route::post('/test', function (Request $request) {
    $data = [
        'timestamp' => now()->toIso8601String(),
        'method' => $request->method(),
        'content_type' => $request->header('Content-Type'),
        'accept' => $request->header('Accept'),
        'all_headers' => $request->headers->all(),
        'query' => $request->query(),
        'payload' => $request->all(),
        'raw_body' => $request->getContent(),
    ];

    $logFile = storage_path('logs/test-post.log');
    file_put_contents($logFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n", FILE_APPEND);

    return response()->json(['success' => true, 'logged_to' => $logFile, 'payload' => $request->all()]);
})->name('core.test.post');

// Test-Log lesen (letzten N Einträge, default 5)
Route::get('/test/log', function (Request $request) {
    $logFile = storage_path('logs/test-post.log');

    if (!file_exists($logFile)) {
        return response()->json(['success' => false, 'error' => 'No log file yet']);
    }

    $content = file_get_contents($logFile);
    $entries = array_filter(explode("\n---\n", $content));
    $limit = (int) $request->query('limit', 5);
    $entries = array_slice($entries, -$limit);

    $parsed = array_map(fn($e) => json_decode(trim($e), true), $entries);

    return response()->json(['success' => true, 'count' => count($parsed), 'entries' => $parsed]);
})->name('core.test.log');

// Test-Log löschen
Route::delete('/test/log', function () {
    $logFile = storage_path('logs/test-post.log');
    if (file_exists($logFile)) {
        unlink($logFile);
    }
    return response()->json(['success' => true, 'message' => 'Log cleared']);
})->name('core.test.log.clear');

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

