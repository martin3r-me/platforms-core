<?php

namespace Platform\Core\Models;

use Laravel\Passport\Client;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Custom Passport Client Model for backwards compatibility.
 *
 * Handles both legacy string format and new array format for redirect URIs.
 * Legacy: 'redirect' column as string
 * New: 'redirect_uris' column as JSON array
 */
class PassportClient extends Client
{
    /**
     * The table associated with the model.
     */
    protected $table = 'oauth_clients';

    /**
     * Get the redirect URIs as an array.
     * Handles both legacy string format and new array format.
     */
    protected function redirectUris(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                // Try redirect_uris first (new format), then fall back to redirect (legacy)
                $rawValue = $value ?? $attributes['redirect_uris'] ?? $attributes['redirect'] ?? null;

                // If it's already an array, return it
                if (is_array($rawValue)) {
                    return $rawValue;
                }

                // If it's a JSON string, decode it
                if (is_string($rawValue) && str_starts_with(trim($rawValue), '[')) {
                    $decoded = json_decode($rawValue, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }

                // Legacy: single redirect URI as string
                if (is_string($rawValue) && !empty($rawValue)) {
                    return [$rawValue];
                }

                return [];
            },
            set: function ($value) {
                // Store as JSON array
                if (is_array($value)) {
                    return json_encode($value);
                }

                // If string, wrap in array
                if (is_string($value) && !empty($value)) {
                    return json_encode([$value]);
                }

                return json_encode([]);
            }
        );
    }

    /**
     * Legacy getter for 'redirect' attribute.
     * Returns first redirect URI for backwards compatibility.
     */
    public function getRedirectAttribute(): string
    {
        $uris = $this->redirect_uris;
        return is_array($uris) && count($uris) > 0 ? $uris[0] : '';
    }

    /**
     * Legacy setter for 'redirect' attribute.
     */
    public function setRedirectAttribute($value): void
    {
        $this->attributes['redirect'] = is_array($value)
            ? json_encode($value)
            : $value;
    }

    /**
     * Get the grant types as an array.
     * Handles both legacy string format and new array format.
     */
    protected function grantTypes(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $rawValue = $value ?? $attributes['grant_types'] ?? null;

                // If it's already an array, return it
                if (is_array($rawValue) && count($rawValue) > 0) {
                    return $rawValue;
                }

                // If it's a JSON string, decode it
                if (is_string($rawValue) && str_starts_with(trim($rawValue), '[')) {
                    $decoded = json_decode($rawValue, true);
                    if (is_array($decoded) && count($decoded) > 0) {
                        return $decoded;
                    }
                }

                // Legacy: single grant type as string
                if (is_string($rawValue) && !empty($rawValue) && !str_starts_with(trim($rawValue), '[')) {
                    return [$rawValue];
                }

                // Default grant types for backwards compatibility
                // Wenn keine grant_types gesetzt sind, erlaube die gängigsten
                return [
                    'authorization_code',
                    'refresh_token',
                    'personal_access',
                    'password',
                ];
            },
            set: function ($value) {
                if (is_array($value)) {
                    return json_encode($value);
                }

                if (is_string($value) && !empty($value)) {
                    return json_encode([$value]);
                }

                return json_encode([]);
            }
        );
    }
}
