<?php

namespace Platform\Core\Passport;

use Laravel\Passport\Bridge\ClientRepository as PassportClientRepository;
use Laravel\Passport\ClientRepository as PassportClientModelRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * Custom Client Repository for backwards compatibility.
 *
 * Ensures grant_types are correctly validated even when the database
 * column is empty or missing.
 */
class ClientRepository extends PassportClientRepository
{
    /**
     * Get a client by the given ID.
     */
    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $record = app(PassportClientModelRepository::class)->findActive($clientIdentifier);

        if (! $record) {
            return null;
        }

        return $this->fromClientModel($record);
    }

    /**
     * Validate a client.
     *
     * Override to handle empty grant_types column.
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $record = app(PassportClientModelRepository::class)->findActive($clientIdentifier);

        if (! $record || ! $this->handlesGrant($record, $grantType)) {
            return false;
        }

        return ! $record->confidential() || $this->verifySecret((string) $clientSecret, $record->secret);
    }

    /**
     * Determine if the given client can handle the given grant type.
     */
    protected function handlesGrant($record, $grantType): bool
    {
        // If client has hasGrantType method (our custom model), use it
        if (method_exists($record, 'hasGrantType')) {
            return $record->hasGrantType($grantType);
        }

        // Get grant_types from the record
        $grantTypes = $record->grant_types ?? [];

        // If grant_types is a string, try to decode it
        if (is_string($grantTypes)) {
            if (str_starts_with(trim($grantTypes), '[')) {
                $grantTypes = json_decode($grantTypes, true) ?? [];
            } else {
                $grantTypes = !empty($grantTypes) ? [$grantTypes] : [];
            }
        }

        // If still empty, allow all common grant types for backwards compatibility
        if (empty($grantTypes)) {
            $grantTypes = [
                'authorization_code',
                'refresh_token',
                'personal_access',
                'password',
                'client_credentials',
            ];
        }

        return in_array($grantType, $grantTypes);
    }
}
