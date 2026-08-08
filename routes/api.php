<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\AgentAssistantController;
use Platform\Core\Http\Controllers\AgentInboxController;
use Platform\Core\Http\Controllers\AgentUsersController;
use Platform\Core\Http\Controllers\ApiController;

/**
 * Core API Routes
 * 
 * Diese Datei enthält die Basis-API-Routen des Cores.
 * Module sollten ihre eigenen API-Routen über ModuleRouter::apiGroup() registrieren.
 */

// Presenter-Kanal: Token-geschuetzter Push fuer externe curl-Clients (gefuehrte Demos/
// Screencasts). Bewusst OHNE api.auth — statischer Header X-Presenter-Token gegen
// config('core.presenter.token'). Ohne konfigurierten Token bleibt der Endpoint zu (401).
Route::post('/presenter/push', function (Request $request) {
    $expected = (string) config('core.presenter.token', '');
    $given    = (string) $request->header('X-Presenter-Token', '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $teamId  = (int) $request->input('team_id', 0);
    $message = trim((string) $request->input('message', ''));
    if ($teamId <= 0 || $message === '') {
        return response()->json(['error' => 'team_id and message are required'], 422);
    }

    $id = \Platform\Core\Support\Presenter::push(
        $teamId,
        $message,
        $request->filled('title') ? (string) $request->input('title') : null,
        (string) $request->input('speaker', 'Claude'),
        $request->filled('duration') ? max(2, (int) $request->input('duration')) : 9,
    );

    return response()->json(['ok' => true, 'id' => $id, 'team_id' => $teamId]);
})->middleware('throttle:120,1')->name('core.presenter.push');

// Agent-Inbox: token-freier Delta-Kanal für die Chat-Konsumption im Worker-Loop
// (kontextlose DMs/Threads lesen, acken, antworten — Kontext-Threads sind der Resume-Pfad).
Route::middleware('api.auth')->prefix('terminal/agent')->name('core.terminal.agent.')->group(function () {
    Route::get('/inbox', [AgentInboxController::class, 'inbox'])->name('inbox');
    Route::post('/inbox/ack', [AgentInboxController::class, 'ack'])->name('inbox.ack');
    Route::post('/reply', [AgentInboxController::class, 'reply'])->name('reply');
});

// Agent-Verzeichnis: Auswahl-Pool an Kollegen (betreuter User eines Assistenten), über ALLE
// Teams des Token-Users. Konvention wie dev/planner/org: {modul}/agent.
Route::middleware('api.auth')->prefix('core/agent')->name('core.agent.')->group(function () {
    Route::get('/users', [AgentUsersController::class, 'index'])->name('users');
    // Assistent-Claim (Delegations-DM): Chef-DMs mit ungelesenen Nachrichten + Verlauf.
    Route::get('/assistant/inbox', [AgentAssistantController::class, 'inbox'])->name('assistant.inbox');
});

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

