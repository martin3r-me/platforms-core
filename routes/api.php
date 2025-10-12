<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Platform\Core\Models\CheckinTodo;
use Platform\Core\Models\PomodoroSession;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API Route für offene Check-in Todos
Route::middleware('auth:sanctum')->get('/checkin/open-todos', function (Request $request) {
    $today = now()->format('Y-m-d');
    
    $openTodosCount = CheckinTodo::whereHas('checkin', function ($query) use ($today) {
        $query->where('user_id', auth()->id())
              ->where('date', $today);
    })->where('done', false)->count();
    
    return response()->json(['count' => $openTodosCount]);
});

// API Route für Pomodoro-Status
Route::middleware('auth:sanctum')->get('/pomodoro/status', function (Request $request) {
    $activeSession = PomodoroSession::where('user_id', auth()->id())
        ->where('is_active', true)
        ->first();
    
    if (!$activeSession) {
        return response()->json([
            'active' => false,
            'progress' => 0
        ]);
    }
    
    // Check if session is expired
    if ($activeSession->is_expired) {
        $activeSession->complete();
        return response()->json([
            'active' => false,
            'progress' => 0
        ]);
    }
    
    return response()->json([
        'active' => true,
        'progress' => $activeSession->progress_percentage,
        'type' => $activeSession->type,
        'remaining_seconds' => $activeSession->remaining_seconds
    ]);
});
