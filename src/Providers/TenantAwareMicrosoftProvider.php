<?php

namespace Platform\Core\Providers;

use SocialiteProviders\Microsoft\Provider as BaseProvider;

class TenantAwareMicrosoftProvider extends BaseProvider
{
    protected function getAuthUrl($state): string
    {
        $tenant = config('services.microsoft.tenant', 'common');

        return $this->buildAuthUrlFromBase(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize",
            $this->getCodeFields($state)
        );
    }

    protected function getTokenUrl(): string
    {
        $tenant = config('services.microsoft.tenant', 'common');

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";
    }
}


