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
        $plainKey = '_plain_' . $key;
        $plainValue = (string) $value;
        
        // Verwende setRawAttribute wenn verfügbar, sonst setAttribute
        // setAttribute würde den Cast wieder auslösen, daher verwenden wir setRawAttribute
        if (method_exists($model, 'setRawAttribute')) {
            $model->setRawAttribute($plainKey, $plainValue);
        } elseif (method_exists($model, 'setAttribute')) {
            // Fallback: Verwende setAttribute, aber mit einem Präfix, das nicht gecastet wird
            // Da setAttribute den Cast auslöst, müssen wir einen Workaround verwenden
            // Wir speichern es in einem temporären Array, das nicht gecastet wird
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('attributes')) {
                $attributesProperty = $reflection->getProperty('attributes');
                $attributesProperty->setAccessible(true);
                $currentAttributes = $attributesProperty->getValue($model);
                $currentAttributes[$plainKey] = $plainValue;
                $attributesProperty->setValue($model, $currentAttributes);
            }
        }
        
        return Crypt::encryptString($plainValue);
    }
}


