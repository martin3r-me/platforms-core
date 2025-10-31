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
            $teamSalt = method_exists($model, 'team') && $model->team ? (string) $model->team->id : null;
            foreach ($model->encryptable as $field => $type) {
                $hashField = $field . '_hash';
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


