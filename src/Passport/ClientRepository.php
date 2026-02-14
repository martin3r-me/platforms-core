<?php

namespace Platform\Core\Passport;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
            Log::debug('OAuth: Client not found', ['client_id' => $clientIdentifier]);
            return null;
        }

        return $this->fromClientModel($record);
    }

    /**
     * Validate a client.
     *
     * Override to handle empty grant_types column and provide better error handling.
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $record = app(PassportClientModelRepository::class)->findActive($clientIdentifier);

        Log::debug('OAuth: Validating client', [
            'client_id' => $clientIdentifier,
            'grant_type' => $grantType,
            'client_found' => $record !== null,
            'is_confidential' => $record?->confidential(),
            'has_secret_in_request' => !empty($clientSecret),
        ]);

        if (! $record) {
            Log::warning('OAuth: Client not found during validation', ['client_id' => $clientIdentifier]);
            return false;
        }

        if (! $this->handlesGrant($record, $grantType)) {
            Log::warning('OAuth: Client does not handle grant type', [
                'client_id' => $clientIdentifier,
                'grant_type' => $grantType,
            ]);
            return false;
        }

        // Public clients (non-confidential) don't need secret verification
        if (! $record->confidential()) {
            Log::debug('OAuth: Public client validated successfully', ['client_id' => $clientIdentifier]);
            return true;
        }

        // Confidential client - verify secret
        return $this->verifyClientSecret((string) $clientSecret, $record->secret, $clientIdentifier);
    }

    /**
     * Verify the client secret with proper error handling.
     */
    protected function verifyClientSecret(string $clientSecret, ?string $storedSecret, string $clientIdentifier): bool
    {
        // Empty stored secret for confidential client is invalid
        if (empty($storedSecret)) {
            Log::error('OAuth: Confidential client has no stored secret', ['client_id' => $clientIdentifier]);
            return false;
        }

        // Empty request secret for confidential client is invalid
        if (empty($clientSecret)) {
            Log::warning('OAuth: No secret provided for confidential client', ['client_id' => $clientIdentifier]);
            return false;
        }

        try {
            $valid = Hash::check($clientSecret, $storedSecret);

            Log::debug('OAuth: Secret verification result', [
                'client_id' => $clientIdentifier,
                'valid' => $valid,
            ]);

            return $valid;
        } catch (\Exception $e) {
            Log::error('OAuth: Secret verification failed with exception', [
                'client_id' => $clientIdentifier,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
