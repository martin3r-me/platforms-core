<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\AuthAccessPolicy;

class ConfigAuthAccessPolicy implements AuthAccessPolicy
{
    public function isManualRegistrationAllowed(): bool
    {
        $cfg = config('auth-policy');
        return ($cfg['enable_manual_registration'] ?? true) && !($cfg['sso_only'] ?? false);
    }

    public function isPasswordLoginAllowed(): bool
    {
        $cfg = config('auth-policy');
        return ($cfg['enable_password_login'] ?? true) && !($cfg['sso_only'] ?? false);
    }

    public function isSsoOnly(): bool
    {
        return (bool) (config('auth-policy.sso_only') ?? false);
    }

    public function isEmailAllowed(?string $email): bool
    {
        if (! $email) {
            return false;
        }
        $allowedEmails  = config('auth-policy.allowed_emails', []);
        if (! empty($allowedEmails) && in_array(strtolower($email), array_map('strtolower', $allowedEmails), true)) {
            return true;
        }
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === '') {
            return false;
        }
        $blockedDomains = array_map('strtolower', config('auth-policy.blocked_email_domains', []));
        if (! empty($blockedDomains) && in_array($domain, $blockedDomains, true)) {
            return false;
        }
        $allowedDomains = array_map('strtolower', config('auth-policy.allowed_email_domains', []));
        if (! empty($allowedDomains)) {
            return in_array($domain, $allowedDomains, true);
        }
        return true;
    }

    public function isTenantAllowed(?string $tenant): bool
    {
        $allowedTenants = config('auth-policy.allowed_tenants', []);
        if (empty($allowedTenants)) {
            return true;
        }
        if (! $tenant) {
            return false;
        }
        return in_array((string) $tenant, $allowedTenants, true);
    }

    public function isHostAllowed(?string $host): bool
    {
        $allowedHosts = config('auth-policy.allowed_hosts', []);
        if (empty($allowedHosts)) {
            return true;
        }
        if (! $host) {
            return false;
        }
        $host = strtolower($host);
        return in_array($host, array_map('strtolower', $allowedHosts), true);
    }
}


