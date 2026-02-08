<?php

namespace Platform\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Platform\Core\Casts\EncryptedString;

class CoreExtraFieldValue extends Model
{
    protected $table = 'core_extra_field_values';

    protected $fillable = [
        'definition_id',
        'fieldable_type',
        'fieldable_id',
        'value',
    ];

    /**
     * EncryptedString Cast Instanz (wiederverwendbar)
     */
    protected static ?EncryptedString $encryptedStringCast = null;

    protected static function getEncryptedStringCast(): EncryptedString
    {
        if (self::$encryptedStringCast === null) {
            self::$encryptedStringCast = new EncryptedString();
        }
        return self::$encryptedStringCast;
    }

    /**
     * Entferne temporäre _plain_* Attribute vor dem Speichern
     * Diese werden vom EncryptedString Cast gesetzt, existieren aber nicht in der DB
     */
    protected static function booted(): void
    {
        static::saving(function ($model) {
            foreach (array_keys($model->getAttributes()) as $key) {
                if (str_starts_with($key, '_plain_')) {
                    unset($model->attributes[$key]);
                }
            }
        });
    }

    /**
     * Beziehungen
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CoreExtraFieldDefinition::class, 'definition_id');
    }

    public function fieldable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Gibt den typisierten und ggf. entschlüsselten Wert zurück
     */
    public function getTypedValueAttribute(): mixed
    {
        $rawValue = $this->attributes['value'] ?? null;

        if ($rawValue === null) {
            return null;
        }

        // Entschlüsseln wenn nötig (nutzt EncryptedString Cast)
        $value = $this->decryptIfNeeded($rawValue);

        if ($value === null) {
            return null;
        }

        $type = $this->definition?->type ?? 'text';

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : null,
            'boolean' => in_array($value, ['1', 'true'], true),
            'text', 'textarea' => (string) $value,
            'select' => $this->decodeSelectValue($value),
            'file' => $this->decodeFileValue($value),
            default => $value,
        };
    }

    /**
     * Setzt den Wert und konvertiert/verschlüsselt ihn
     */
    public function setTypedValue(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['value'] = null;
            return;
        }

        $type = $this->definition?->type ?? 'text';

        $stringValue = match ($type) {
            'number' => is_numeric($value) ? (string) $value : null,
            'boolean' => $this->normalizeBooleanForStorage($value),
            'text', 'textarea' => (string) $value,
            'select' => is_array($value) ? json_encode($value) : (string) $value,
            'file' => is_array($value) ? json_encode($value) : (string) $value,
            default => (string) $value,
        };

        if ($stringValue === null) {
            $this->attributes['value'] = null;
            return;
        }

        // Verschlüsseln wenn Definition is_encrypted = true (nutzt EncryptedString Cast)
        $this->attributes['value'] = $this->encryptIfNeeded($stringValue);
    }

    private function normalizeBooleanForStorage(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $lower = strtolower(trim((string) $value));
        if (in_array($lower, ['1', 'true', 'ja', 'yes'], true)) {
            return '1';
        }
        return '0';
    }

    /**
     * Verschlüsselt den Wert wenn die Definition is_encrypted hat
     * Nutzt den gleichen EncryptedString Cast wie der Encryptable Trait
     */
    protected function encryptIfNeeded(string $value): string
    {
        if ($this->definition?->is_encrypted) {
            return self::getEncryptedStringCast()->set($this, 'value', $value, $this->attributes);
        }
        return $value;
    }

    /**
     * Entschlüsselt den Wert wenn die Definition is_encrypted hat
     * Nutzt den gleichen EncryptedString Cast wie der Encryptable Trait
     */
    protected function decryptIfNeeded(string $value): ?string
    {
        if (!$this->definition?->is_encrypted) {
            return $value;
        }

        return self::getEncryptedStringCast()->get($this, 'value', $value, $this->attributes);
    }

    /**
     * Dekodiert Select-Werte (können JSON-Arrays sein für Mehrfachauswahl)
     */
    protected function decodeSelectValue(string $value): mixed
    {
        // Prüfen ob es ein JSON-Array ist
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Dekodiert File-Werte (können JSON-Arrays sein für Mehrfachdateien)
     */
    protected function decodeFileValue(string $value): mixed
    {
        // Prüfen ob es ein JSON-Array ist (multiple files)
        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Einzelne File-ID
        return (int) $value;
    }

    /**
     * Prüft ob der Wert verschlüsselt ist
     */
    public function isEncrypted(): bool
    {
        return $this->definition?->is_encrypted ?? false;
    }
}
