<?php

namespace Platform\Core\Contracts;

/**
 * Ergebnis einer Tool-Ausführung
 * 
 * Standardisiertes Format für Tool-Ergebnisse
 */
class ToolResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly array $metadata = []
    ) {}

    /**
     * Erstellt ein erfolgreiches Ergebnis
     */
    public static function success(mixed $data = null, array $metadata = []): self
    {
        return new self(true, $data, null, null, $metadata);
    }

    /**
     * Alias für error() – Backwards-Kompatibilität
     */
    public static function failure(string $error, ?string $code = null, array $metadata = []): self
    {
        return static::error($error, $code, $metadata);
    }

    /**
     * Erstellt ein Fehler-Ergebnis
     */
    public static function error(string $error, ?string $code = null, array $metadata = []): self
    {
        // Backwards-/Usage-Kompatibilität:
        // In vielen Tools wird (fälschlich) die Reihenfolge (code, message) verwendet.
        // Erkenne diesen Fall heuristisch und swap, damit error=message und errorCode=code stimmt.
        $looksLikeCode = static function (?string $s): bool {
            return is_string($s) && $s !== '' && (bool) preg_match('/^[A-Z0-9_.-]+$/', $s);
        };
        $looksLikeMessage = static function (?string $s): bool {
            if (!is_string($s) || $s === '') return false;
            // Message enthält typischerweise Leerzeichen/Zeichen und ist länger als ein Code
            return strlen($s) > 12 || str_contains($s, ' ') || str_contains($s, ':');
        };

        if ($code !== null && $looksLikeCode($error) && $looksLikeMessage($code)) {
            // swap: ($error=code, $code=message) -> ($message, $code)
            return new self(false, null, $code, $error, $metadata);
        }

        return new self(false, null, $error, $code, $metadata);
    }

    /**
     * Konvertiert zu Array (für JSON-Response)
     */
    public function toArray(): array
    {
        $result = [
            'ok' => $this->success,
        ];

        if ($this->success) {
            $result['data'] = $this->data;
            if (!empty($this->metadata)) {
                $result['metadata'] = $this->metadata;
            }
        } else {
            $result['error'] = [
                'message' => $this->error,
            ];
            if ($this->errorCode) {
                $result['error']['code'] = $this->errorCode;
            }
            if (!empty($this->metadata)) {
                $result['error'] = array_merge($result['error'], $this->metadata);
            }
        }

        return $result;
    }
}

