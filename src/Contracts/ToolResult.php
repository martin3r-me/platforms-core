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
     * Erstellt ein Fehler-Ergebnis
     */
    public static function error(string $error, ?string $code = null, array $metadata = []): self
    {
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

