<?php

namespace Platform\Core\Traits;

use Platform\Core\Casts\EncryptedString;
use Platform\Core\Casts\EncryptedJson;
use Platform\Core\Support\FieldHasher;

trait Encryptable
{
    // Hinweis: Modelle definieren $encryptable selbst

    public static function bootEncryptable(): void
    {
        static::saving(function ($model) {
            if (!property_exists($model, 'encryptable') || empty($model->encryptable)) {
                return;
            }
            
            // Nur geänderte Felder verarbeiten (Performance-Optimierung)
            $dirtyFields = $model->getDirty();
            $teamSalt = method_exists($model, 'team') && $model->team ? (string) $model->team->id : null;
            
            foreach ($model->encryptable as $field => $type) {
                $hashField = $field . '_hash';
                
                // Nur Hash aktualisieren, wenn das verschlüsselte Feld geändert wurde
                // oder wenn es ein neues Model ist (creating)
                if ($model->exists && !isset($dirtyFields[$field])) {
                    continue; // Feld nicht geändert, überspringen - kein Hash-Update nötig
                }
                
                // Wenn das Feld geändert wurde, ist der neue Wert bereits in $dirtyFields
                // Der Wert ist bereits verschlüsselt (durch den Cast), aber für den Hash
                // brauchen wir den Plain-Text. Da der Cast bereits angewendet wurde,
                // müssen wir getAttribute() aufrufen, was entschlüsselt.
                // ABER: Wir können prüfen, ob der Wert in den Attributes ist (vor Cast)
                // und dann direkt darauf zugreifen, wenn möglich.
                
                // Versuche zuerst, den Plain-Text-Wert aus dem temporären Attribut zu holen
                // (wurde vom Cast gespeichert, um Entschlüsselung zu vermeiden)
                $plainKey = '_plain_' . $field;
                
                // Hole Plain-Text-Wert sicher aus attributes
                $plain = null;
                if (method_exists($model, 'getAttributes')) {
                    $allAttributes = $model->getAttributes();
                    $plain = $allAttributes[$plainKey] ?? null;
                } else {
                    // Fallback: Reflection verwenden
                    $reflection = new \ReflectionClass($model);
                    if ($reflection->hasProperty('attributes')) {
                        $attributesProperty = $reflection->getProperty('attributes');
                        $attributesProperty->setAccessible(true);
                        $attributes = $attributesProperty->getValue($model);
                        $plain = $attributes[$plainKey] ?? null;
                    }
                }
                
                // Falls nicht vorhanden (z.B. bei bestehenden Records ohne Änderung),
                // müssen wir entschlüsseln (nur wenn Feld wirklich geändert wurde)
                if ($plain === null && isset($dirtyFields[$field])) {
                    // Feld wurde geändert, aber Plain-Text nicht gespeichert
                    // (kann passieren bei direkten DB-Updates oder anderen Szenarien)
                    $plain = $model->getAttribute($field);
                }
                
                // Temporäres Attribut entfernen (nicht in DB speichern)
                if (method_exists($model, 'offsetUnset')) {
                    $model->offsetUnset($plainKey);
                } elseif (method_exists($model, 'setRawAttribute')) {
                    // Versuche über setRawAttribute mit null
                    $model->setRawAttribute($plainKey, null);
                } else {
                    // Fallback: Reflection verwenden
                    $reflection = new \ReflectionClass($model);
                    if ($reflection->hasProperty('attributes')) {
                        $attributesProperty = $reflection->getProperty('attributes');
                        $attributesProperty->setAccessible(true);
                        $attributes = $attributesProperty->getValue($model);
                        unset($attributes[$plainKey]);
                        $attributesProperty->setValue($model, $attributes);
                    }
                }
                
                // Hash nur setzen, wenn Plain-Text vorhanden ist
                if ($plain !== null) {
                    $model->setAttribute($hashField, FieldHasher::hmacSha256($plain, $teamSalt));
                }
            }
        });
    }

    public function initializeEncryptable(): void
    {
        if (!property_exists($this, 'casts')) {
            $this->casts = [];
        }
        if (!property_exists($this, 'encryptable') || empty($this->encryptable)) {
            return;
        }
        foreach ($this->encryptable as $field => $type) {
            if ($type === 'json') {
                $this->casts[$field] = EncryptedJson::class;
            } else {
                $this->casts[$field] = EncryptedString::class;
            }
        }
    }
}


