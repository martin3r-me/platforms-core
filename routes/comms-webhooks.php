<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\Comms\InboundPostmarkController;

// Webhooks must NOT require auth / module guard.
Route::post('/postmark/inbound', InboundPostmarkController::class)
    ->name('core.comms.postmark.inbound');

