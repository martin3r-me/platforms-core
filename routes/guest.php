<?php

use Illuminate\Support\Facades\Route;
use Platform\Core\Livewire\Login;
use Platform\Core\Livewire\Register;
use Platform\Core\Livewire\Dashboard;
use App\Livewire\Landing;

// OAuth Discovery am Root-Level für MCP Clients (Claude Desktop)
Route::get('/.well-known/oauth-authorization-server', function () {
    $baseUrl = config('app.url');

    return response()->json([
        'issuer' => $baseUrl,
        'authorization_endpoint' => $baseUrl . '/oauth/authorize',
        'token_endpoint' => $baseUrl . '/oauth/token',
        'registration_endpoint' => $baseUrl . '/mcp/oauth/register',
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => ['mcp:use'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]);
});

Route::get('/.well-known/oauth-protected-resource', function () {
    $baseUrl = config('app.url');

    return response()->json([
        'resource' => $baseUrl,
        'authorization_servers' => [$baseUrl],
        'scopes_supported' => ['mcp:use'],
    ]);
});

Route::get('/login', Login::class)->name('login');
Route::get('/register', Register::class)->name('register');
Route::get('/', Landing::class)->name('landing');
Route::get('/dashboard', Dashboard::class)->name('platform.dashboard');