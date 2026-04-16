<?php

namespace Platform\Core\Tools\SemanticLayer;

use RuntimeException;

/**
 * Wird intern in CreateVersionTool geworfen, wenn der gewünschte SemVer
 * im Scope bereits existiert. Wird vom Tool gefangen und als
 * `ToolResult::error('VERSION_EXISTS', …)` zurückgegeben.
 */
class VersionExistsException extends RuntimeException
{
}
