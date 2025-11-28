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
                // Nur Hash aktualisieren, wenn das verschlüsselte Feld geändert wurde
                // oder wenn es ein neues Model ist (creating)
                if ($model->exists && !isset($dirtyFields[$field])) {
                    continue; // Feld nicht geändert, überspringen
                }
                
                $hashField = $field . '_hash';
                // Nur getAttribute aufrufen, wenn das Feld wirklich geändert wurde
                // Das vermeidet unnötige Entschlüsselung
                $plain = $model->getAttribute($field);
                $model->setAttribute($hashField, FieldHasher::hmacSha256($plain, $teamSalt));
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


