<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Platform\Core\Http\Controllers\CoreAiStreamController;
use Platform\Core\Http\Controllers\TeamInvitationController;
use Platform\Core\Http\Controllers\CoreToolPlaygroundController;


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

// Einladung annehmen (Token)
Route::get('/invitations/accept/{token}', [TeamInvitationController::class, 'accept'])
    ->name('team-invitations.accept');

// (Teams Tab Test-Routen entfernt)

Route::middleware([\Platform\Core\Middleware\EmbeddedHeaderAuth::class])->get('/embedded/config', function () {
    $user = Auth::user();
    $teamIds = collect();
    $teams = collect();

    if ($user) {
        try {
            if (method_exists($user, 'teams')) {
                $teams = $user->teams()->select(['teams.id','teams.name'])->orderBy('name')->get();
                $teamIds = $teams->pluck('id');
            }
            if ($user->currentTeam && !$teamIds->contains($user->currentTeam->id)) {
                $teamIds->push($user->currentTeam->id);
                $teams = $teams->push($user->currentTeam)->unique('id');
            }
        } catch (\Throwable $e) {}
    }

    // Planner-Projekte nur aus User-Teams
    $projects = \Platform\Planner\Models\PlannerProject::select(['id','name','team_id'])
        ->when($teamIds->isNotEmpty(), function($q) use ($teamIds){ $q->whereIn('team_id', $teamIds); }, function($q){ $q->whereRaw('1=0'); })
        ->orderBy('name')
        ->get();

    $response = response()->view('platform::embedded.config', [
        'teams' => $teams,
        'plannerProjects' => $projects,
    ]);
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->withoutMiddleware([\Illuminate\Http\Middleware\FrameGuard::class, \Illuminate\Auth\Middleware\Authenticate::class])->name('embedded.config');

Route::get('/embedded/config/okrs', function () {
    // Platzhalterseite – später: OKR-Auswahl (Teams-Tab-Konfiguration)
    $response = response()->view('platform::embedded.config-okrs');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->name('embedded.config.okrs');

Route::get('/embedded/config/helpdesk', function () {
    // Platzhalterseite – später: Helpdesk-Board-Auswahl
    $response = response()->view('platform::embedded.config-helpdesk');
    $response->headers->set('Content-Security-Policy', "frame-ancestors https://*.teams.microsoft.com https://teams.microsoft.com https://*.skype.com");
    return $response;
})->name('embedded.config.helpdesk');

// AI SSE Streaming (auth required)
Route::middleware(['web', 'auth'])->group(function () {
    // Minimaler Test-Endpoint direkt im Controller
    Route::get('/core/ai/stream/minimal', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response('Unauthorized', 401);
        }
        
        return new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($user) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            echo "retry: 500\n\n";
            @flush();
            echo "data: " . json_encode([
                'debug' => '✅ Minimaler Stream funktioniert',
                'user_id' => $user->id
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
            sleep(2);
            echo "data: [DONE]\n\n";
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    })->name('core.ai.stream.minimal');
    
    // Test: Vereinfachte Version des Streams direkt in der Route
    Route::get('/core/ai/stream/simple', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response('Unauthorized', 401);
        }
        
        $threadId = (int) $request->query('thread');
        if (!$threadId) {
            return new \Symfony\Component\HttpFoundation\StreamedResponse(function() {
                echo "data: " . json_encode(['error' => 'thread parameter required'], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }, 422, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }
        
        $thread = \Platform\Core\Models\CoreChatThread::find($threadId);
        if (!$thread) {
            return new \Symfony\Component\HttpFoundation\StreamedResponse(function() {
                echo "data: " . json_encode(['error' => 'Thread not found'], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }, 404, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }
        
        return new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($user, $thread) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            echo "retry: 500\n\n";
            @flush();
            echo "data: " . json_encode([
                'debug' => '✅ Vereinfachter Stream funktioniert',
                'user_id' => $user->id,
                'thread_id' => $thread->id
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
            sleep(2);
            echo "data: [DONE]\n\n";
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    })->name('core.ai.stream.simple');
    
    Route::get('/core/ai/stream', [CoreAiStreamController::class, 'stream'])->name('core.ai.stream');
    
    // Test-Endpoint für Debugging
    Route::get('/core/ai/stream/test', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return new \Symfony\Component\HttpFoundation\StreamedResponse(function() {
                echo "data: " . json_encode([
                    'error' => 'Unauthorized',
                    'debug' => 'Kein authentifizierter User'
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }, 401, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ]);
        }
        
        return new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($user) {
            // Clean buffers
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            
            echo "retry: 500\n\n";
            @flush();
            
            echo "data: " . json_encode([
                'debug' => '✅ Test-Endpoint erreicht',
                'user_id' => $user->id,
                'message' => 'SSE-Verbindung funktioniert!'
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
            
            sleep(1);
            
            echo "data: " . json_encode([
                'debug' => '✅ Zweite Nachricht gesendet'
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
            
            sleep(1);
            
            echo "data: [DONE]\n\n";
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Nginx buffering deaktivieren
        ]);
    })->name('core.ai.stream.test');
    
    // Tool Playground (MCP Testing) - Livewire-Komponente
    Route::get('/core/tools/playground', \Platform\Core\Livewire\ToolPlayground::class)->name('core.tools.playground');
    Route::post('/core/tools/playground/test', [CoreToolPlaygroundController::class, 'test'])->name('core.tools.playground.test');
    Route::get('/core/tools/playground/tools', [CoreToolPlaygroundController::class, 'tools'])->name('core.tools.playground.tools');
});

