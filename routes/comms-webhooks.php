<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Http\Controllers\Comms\InboundPostmarkController;
use Platform\Core\Http\Controllers\Comms\WhatsAppWebhookController;

// Webhooks must NOT require auth / module guard.
Route::post('/postmark/inbound', InboundPostmarkController::class)
    ->name('core.comms.postmark.inbound');

// WhatsApp Meta webhook (GET for verification, POST for messages/status updates)
Route::match(['get', 'post'], '/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
    ->name('core.comms.whatsapp.webhook');

