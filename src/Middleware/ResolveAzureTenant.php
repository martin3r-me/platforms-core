<?php

namespace Platform\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class ResolveAzureTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenantKey = $request->query('tenant');

        $profiles = config('azure-sso.tenants', []);

        $cfg = ($tenantKey && isset($profiles[$tenantKey]))
            ? $profiles[$tenantKey]
            : config('azure-sso');

        Config::set('services.microsoft.client_id',     $cfg['client_id'] ?? null);
        Config::set('services.microsoft.client_secret', $cfg['client_secret'] ?? null);
        Config::set('services.microsoft.redirect',      $cfg['redirect'] ?? null);
        Config::set('services.microsoft.tenant',        $cfg['tenant'] ?? ($cfg['tenant_id'] ?? 'common'));

        return $next($request);
    }
}


