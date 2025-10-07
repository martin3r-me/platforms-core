<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Http\Middleware\TeamsFrameHeaders;


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

// Teams Test-Route: leitet auf Planner-Projekt mit embed=1 weiter (ohne App-Chrome)
Route::middleware([TeamsFrameHeaders::class])->group(function () {
    Route::get('/teams/planner/projects/{plannerProject}', function ($plannerProject) {
        $url = route('planner.projects.show', ['plannerProject' => $plannerProject]);
        return redirect()->away($url.'?embed=1');
    })->name('teams.planner.projects.show');
});

