<?php

namespace Platform\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

class EncryptedString implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Rotation: Versuche mit vorherigen Keys zu entschlüsseln
            foreach ((array) config('security.previous_encryption_keys', []) as $k) {
                try {
                    $key = str_starts_with((string) $k, 'base64:') ? base64_decode(substr((string) $k, 7)) : (string) $k;
                    $cipher = config('app.cipher', 'AES-256-CBC');
                    $plain = openssl_decrypt(base64_decode($value, true) ?: $value, $cipher, $key, OPENSSL_RAW_DATA, substr(hash('sha256', $key), 0, 16));
                    if ($plain !== false && $plain !== null && $plain !== '') {
                        return $plain;
                    }
                } catch (\Throwable $e2) {
                    // ignore and continue
                }
            }
            return null;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        
        // Speichere Plain-Text-Wert temporär für Hash-Berechnung
        // (wird in bootEncryptable verwendet und dann entfernt)
        // Verwende setRawAttribute um es nicht in die DB zu schreiben
        $plainKey = '_plain_' . $key;
        if (method_exists($model, 'setRawAttribute')) {
            $model->setRawAttribute($plainKey, (string) $value);
        } else {
            // Fallback: direkt in attributes array
            $model->attributes[$plainKey] = (string) $value;
        }
        
        return Crypt::encryptString((string) $value);
    }
}


