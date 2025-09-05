<?php

return [
    'client_id'     => env('AZURE_AD_CLIENT_ID'),
    'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
    'redirect'      => env('AZURE_AD_REDIRECT_URI'),
    'tenant_id'     => env('AZURE_AD_TENANT_ID'),

    'post_login_redirect'  => env('AZURE_SSO_POST_LOGIN_REDIRECT', '/home'),
    'logout_url'           => env('AZURE_AD_LOGOUT_URL'),
    'post_logout_redirect' => env('AZURE_SSO_POST_LOGOUT_REDIRECT', '/'),

    'tenant'        => env('AZURE_AD_TENANT_ID', 'common'),

    'tenants' => [
        // Multi-Tenant-Profile können hier optional konfiguriert werden
    ],
];


