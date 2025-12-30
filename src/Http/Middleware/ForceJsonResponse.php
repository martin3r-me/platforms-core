<?php

namespace Platform\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware: Erzwingt JSON-Response für bestimmte Routes
 * 
 * Verhindert, dass Laravel HTML-Fehlerseiten zurückgibt.
 * Stattdessen wird immer JSON zurückgegeben, auch bei fatalen Fehlern.
 */
class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Setze Accept-Header auf JSON, falls nicht gesetzt
        if (!$request->wantsJson() && !$request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }
        
        try {
            $response = $next($request);
            
            // Stelle sicher, dass die Response JSON ist
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                return $response;
            }
            
            // Wenn es keine JSON-Response ist, konvertiere sie
            if (!$response->headers->get('Content-Type') || 
                !str_contains($response->headers->get('Content-Type'), 'application/json')) {
                $response->headers->set('Content-Type', 'application/json');
            }
            
            return $response;
        } catch (\Throwable $e) {
            // Gib immer JSON zurück, auch bei Exceptions
            // WICHTIG: Dies wird von Laravel's Exception Handler abgefangen,
            // aber wir stellen sicher, dass JSON zurückgegeben wird
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'error_details' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'class' => get_class($e),
                    'trace' => explode("\n", substr($e->getTraceAsString(), 0, 2000)),
                ],
            ], 500);
        }
    }
}

