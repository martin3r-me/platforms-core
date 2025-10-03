<?php

namespace Platform\Core\Support;

class FieldHasher
{
    public static function hmacSha256(?string $value, ?string $teamSalt = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Bevorzugt eigener HASH_KEY; fallback APP_KEY
        $key = config('security.hash_key') ?: config('app.key');
        if (str_starts_with((string) $key, 'base64:')) {
            $key = base64_decode(substr((string) $key, 7));
        }
        $salt = $teamSalt ? (string) $teamSalt : '';
        return hash_hmac('sha256', $salt . '|' . (string) $value, (string) $key);
    }

    public static function matchesAny(?string $value, ?string $teamSalt, array $previousKeys = []): array
    {
        $hashes = [];
        $current = self::hmacSha256($value, $teamSalt);
        if ($current) { $hashes[] = $current; }

        foreach ($previousKeys as $k) {
            $key = $k;
            if (str_starts_with((string) $key, 'base64:')) {
                $key = base64_decode(substr((string) $key, 7));
            }
            $salt = $teamSalt ? (string) $teamSalt : '';
            $hashes[] = hash_hmac('sha256', $salt . '|' . (string) $value, (string) $key);
        }
        return array_values(array_unique(array_filter($hashes)));
    }
}


