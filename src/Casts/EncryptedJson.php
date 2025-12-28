<?php

namespace Platform\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

class EncryptedJson implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $json = Crypt::decryptString($value);
            return json_decode($json, true);
        } catch (\Throwable $e) {
            // Rotation: Versuche mit vorherigen Keys
            foreach ((array) config('security.previous_encryption_keys', []) as $k) {
                try {
                    $key = str_starts_with((string) $k, 'base64:') ? base64_decode(substr((string) $k, 7)) : (string) $k;
                    $cipher = config('app.cipher', 'AES-256-CBC');
                    $plain = openssl_decrypt(base64_decode($value, true) ?: $value, $cipher, $key, OPENSSL_RAW_DATA, substr(hash('sha256', $key), 0, 16));
                    if ($plain !== false && $plain !== null && $plain !== '') {
                        return json_decode($plain, true);
                    }
                } catch (\Throwable $e2) {
                    // ignore
                }
            }
            return null;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }
        
        // Speichere Plain-Text-Wert temporär für Hash-Berechnung
        $json = is_string($value) ? $value : json_encode($value);
        $plainKey = '_plain_' . $key;
        
        // Verwende setRawAttribute wenn verfügbar, sonst Reflection
        if (method_exists($model, 'setRawAttribute')) {
            $model->setRawAttribute($plainKey, $json);
        } elseif (method_exists($model, 'setAttribute')) {
            // Fallback: Verwende Reflection um direkt auf attributes zuzugreifen
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('attributes')) {
                $attributesProperty = $reflection->getProperty('attributes');
                $attributesProperty->setAccessible(true);
                $currentAttributes = $attributesProperty->getValue($model);
                $currentAttributes[$plainKey] = $json;
                $attributesProperty->setValue($model, $currentAttributes);
            }
        }
        
        return Crypt::encryptString($json);
    }
}


