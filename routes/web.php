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
// Temporär ungeschützt testen (ohne FrameGuard, ohne EmbeddedHeaderAuth, ohne auth)
})->withoutMiddleware([\Illuminate\Http\Middleware\FrameGuard::class, \Platform\Core\Middleware\EmbeddedHeaderAuth::class, \Illuminate\Auth\Middleware\Authenticate::class])->name('embedded.config');

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

