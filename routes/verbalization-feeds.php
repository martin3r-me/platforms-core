<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Verbalization\Feed\FeedController;

// Atom-Feed-Endpoint — opake UUID als URL-Token.
// Bewusst KEINE NoCacheHeaders-Middleware: Feeds duerfen 5 Minuten gecached werden.
Route::get('/feed/{token}', FeedController::class)
    ->where('token', '[a-f0-9-]+(\.xml)?')
    ->name('core.verbalization.feed');
