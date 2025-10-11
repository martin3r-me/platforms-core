<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Platform\Core\Models\CheckinTodo;

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
