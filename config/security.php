<?php

return [
    // Verschlüsselung: zusätzliche alte Schlüssel für Rotation (base64 oder plain)
    'previous_encryption_keys' => array_values(array_filter(array_map('trim', explode(',', env('PREVIOUS_ENCRYPTION_KEYS', ''))))),

    // Hashing: eigener stabiler Schlüssel (fällt zurück auf APP_KEY), plus alte Keys für Rotation
    'hash_key' => env('HASH_KEY', null),
    'previous_hash_keys' => array_values(array_filter(array_map('trim', explode(',', env('PREVIOUS_HASH_KEYS', ''))))),
];


