<?php

namespace Platform\Core\Mcp\Content;

use InvalidArgumentException;
use Laravel\Mcp\Server\Concerns\HasMeta;
use Laravel\Mcp\Server\Contracts\Content;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

/**
 * MCP-Image-Content ({type: "image", data: <base64>, mimeType}).
 *
 * laravel/mcp (0.5.x) wirft bei Response::image() NotImplementedException –
 * daher eigene Content-Klasse für Tool-Ergebnisse.
 */
class ImageContent implements Content
{
    use HasMeta;

    public function __construct(
        protected string $base64Data,
        protected string $mimeType = 'image/png',
    ) {}

    public function toTool(Tool $tool): array
    {
        return $this->toArray();
    }

    public function toPrompt(Prompt $prompt): array
    {
        return $this->toArray();
    }

    public function toResource(Resource $resource): array
    {
        throw new InvalidArgumentException('Image content may not be used in resources.');
    }

    public function __toString(): string
    {
        return '[image ' . $this->mimeType . ']';
    }

    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'image',
            'data' => $this->base64Data,
            'mimeType' => $this->mimeType,
        ]);
    }
}
