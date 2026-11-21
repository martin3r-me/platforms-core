<?php

namespace Platform\Core\Mcp\Content;

use Laravel\Mcp\Response;

/**
 * Response mit Image-Content (Response-Konstruktor ist protected → Subklasse).
 */
class ImageResponse extends Response
{
    public static function fromBase64(string $base64Data, string $mimeType = 'image/png'): static
    {
        return new static(new ImageContent($base64Data, $mimeType));
    }
}
