<?php

namespace Platform\Core\Contracts;

interface AuthAccessPolicy
{
    public function isManualRegistrationAllowed(): bool;
    public function isPasswordLoginAllowed(): bool;
    public function isSsoOnly(): bool;

    public function isEmailAllowed(?string $email): bool;
    public function isTenantAllowed(?string $tenant): bool;
    public function isHostAllowed(?string $host): bool;
}


