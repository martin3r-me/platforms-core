<?php

namespace Platform\Core\SemanticLayer\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein SemanticLayer in einem angeforderten Scope
 * nicht auflösbar ist (z.B. angeforderter Semver existiert nicht).
 */
class LayerNotResolvableException extends RuntimeException
{
}
