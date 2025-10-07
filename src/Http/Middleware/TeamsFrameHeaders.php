<?php

namespace Platform\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TeamsFrameHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Nur setzen, wenn explizit embed=1 angefordert ist
        if ($request->boolean('embed')) {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Content-Security-Policy',
                "frame-ancestors https://*.teams.microsoft.com https://*.office.com https://*.skype.com", false);
        }

        return $response;
    }
}


