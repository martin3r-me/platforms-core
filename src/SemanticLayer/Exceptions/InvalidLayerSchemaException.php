<?php

namespace Platform\Core\SemanticLayer\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein SemanticLayer-Schema nicht der Canvas-Spezifikation
 * entspricht (freie Felder, fehlende Kanäle, Längenverstöße).
 */
class InvalidLayerSchemaException extends RuntimeException
{
    /** @var array<int, string> */
    public array $errors;

    /**
     * @param array<int, string> $errors
     */
    public function __construct(array $errors, string $message = 'Layer-Schema ungültig')
    {
        $this->errors = $errors;
        parent::__construct($message . ': ' . implode('; ', $errors));
    }
}
