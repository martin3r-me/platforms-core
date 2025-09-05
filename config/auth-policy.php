<?php

return [
    'enable_manual_registration' => env('AUTH_ENABLE_MANUAL_REG', true),
    'enable_password_login'      => env('AUTH_ENABLE_PASSWORD_LOGIN', true),
    'sso_only'                   => env('AUTH_SSO_ONLY', false),

    'allowed_email_domains'      => array_filter(array_map('trim', explode(',', env('AUTH_ALLOWED_EMAIL_DOMAINS', '')))),
    'allowed_emails'             => array_filter(array_map('trim', explode(',', env('AUTH_ALLOWED_EMAILS', '')))),
    'blocked_email_domains'      => array_filter(array_map('trim', explode(',', env('AUTH_BLOCKED_EMAIL_DOMAINS', '')))),

    'allowed_tenants'            => array_filter(array_map('trim', explode(',', env('AUTH_ALLOWED_TENANTS', '')))),
    'allowed_hosts'              => array_filter(array_map('trim', explode(',', env('AUTH_ALLOWED_HOSTS', '')))),
];


