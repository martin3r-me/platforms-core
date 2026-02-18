<?php

namespace Platform\Core\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for resolving contacts from communication identifiers (phone, email).
 *
 * Modules like CRM, HCM can implement this to link inbound communications
 * to their contact entities (CrmContact, Applicant, etc.)
 */
interface CommsContactResolverInterface
{
    /**
     * Get the priority of this resolver. Higher = checked first.
     * Suggested ranges: CRM = 100, HCM = 80, Fallback = 10
     */
    public function priority(): int;

    /**
     * Resolve a contact by phone number.
     *
     * @param string $phone E.164 format phone number (e.g., "+4915112345678")
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function resolveByPhone(string $phone): ?array;

    /**
     * Resolve a contact by email address.
     *
     * @param string $email Email address
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function resolveByEmail(string $email): ?array;

    /**
     * Create a new contact from phone number if resolution is desired.
     *
     * @param string $phone E.164 format phone number
     * @param array $meta Additional metadata (source, channel_id, etc.)
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function createFromPhone(string $phone, array $meta = []): ?array;

    /**
     * Create a new contact from email address if resolution is desired.
     *
     * @param string $email Email address
     * @param array $meta Additional metadata (source, channel_id, etc.)
     * @return array|null ['model' => Model, 'type' => string, 'id' => int, 'display_name' => string]
     */
    public function createFromEmail(string $email, array $meta = []): ?array;

    /**
     * Check if this resolver supports auto-creation of contacts.
     */
    public function supportsAutoCreate(): bool;
}
