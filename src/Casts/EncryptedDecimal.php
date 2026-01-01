<?php

namespace Platform\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

class EncryptedDecimal implements CastsAttributes
{
    protected int $decimals;

    public function __construct(int $decimals = 2)
    {
        $this->decimals = $decimals;
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $decrypted = Crypt::decryptString($value);
            return (float) $decrypted;
        } catch (\Throwable $e) {
            // Rotation: Versuche mit vorherigen Keys zu entschlüsseln
            foreach ((array) config('security.previous_encryption_keys', []) as $k) {
                try {
                    $key = str_starts_with((string) $k, 'base64:') ? base64_decode(substr((string) $k, 7)) : (string) $k;
                    $cipher = config('app.cipher', 'AES-256-CBC');
                    $plain = openssl_decrypt(base64_decode($value, true) ?: $value, $cipher, $key, OPENSSL_RAW_DATA, substr(hash('sha256', $key), 0, 16));
                    if ($plain !== false && $plain !== null && $plain !== '') {
                        return (float) $plain;
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
            return null;
        }
        
        // Konvertiere zu String mit korrekter Dezimalstellen-Anzahl
        $plainValue = number_format((float) $value, $this->decimals, '.', '');
        
        // Prüfe ob der Wert bereits verschlüsselt ist
        if ($this->isEncrypted($plainValue)) {
            // Wert ist bereits verschlüsselt, speichere Plain-Text für Hash-Berechnung
            try {
                $decrypted = Crypt::decryptString($plainValue);
                $plainKey = '_plain_' . $key;
                if (method_exists($model, 'setRawAttribute')) {
                    $model->setRawAttribute($plainKey, $decrypted);
                } else {
                    $reflection = new \ReflectionClass($model);
                    if ($reflection->hasProperty('attributes')) {
                        $attributesProperty = $reflection->getProperty('attributes');
                        $attributesProperty->setAccessible(true);
                        $currentAttributes = $attributesProperty->getValue($model);
                        $currentAttributes[$plainKey] = $decrypted;
                        $attributesProperty->setValue($model, $currentAttributes);
                    }
                }
            } catch (\Throwable $e) {
                // Entschlüsselung fehlgeschlagen, behandle als Plain-Text
            }
            
            return $plainValue;
        }
        
        // Speichere Plain-Text-Wert temporär für Hash-Berechnung
        $plainKey = '_plain_' . $key;
        
        if (method_exists($model, 'setRawAttribute')) {
            $model->setRawAttribute($plainKey, $plainValue);
        } else {
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
    
    /**
     * Prüft ob ein Wert bereits verschlüsselt ist
     */
    private function isEncrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        return strlen($decoded) > 16 && !ctype_print($decoded);
    }
}

