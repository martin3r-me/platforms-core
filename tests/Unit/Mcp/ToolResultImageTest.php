<?php

namespace Platform\Core\Tests\Unit\Mcp;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Mcp\Adapters\ToolContractAdapter;
use Platform\Core\Tests\TestCase;

class ToolResultImageTest extends TestCase
{
    private function convert(ToolResult $result): Response|ResponseFactory
    {
        $adapter = new ToolContractAdapter($this->createMock(ToolContract::class));
        $m = new \ReflectionMethod($adapter, 'convertToolResult');
        $m->setAccessible(true);

        return $m->invoke($adapter, $result);
    }

    private function png(int $w = 4, int $h = 4): string
    {
        $im = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($im);

        return ob_get_clean();
    }

    public function test_without_images_result_is_plain_text_response(): void
    {
        $out = $this->convert(ToolResult::success(['a' => 1]));

        $this->assertInstanceOf(Response::class, $out);
        $this->assertSame('text', $out->content()->toArray()['type']);
    }

    public function test_with_image_returns_text_plus_image_parts(): void
    {
        $png = $this->png();
        $out = $this->convert(ToolResult::success(['ok' => true])->withImage($png));

        $this->assertInstanceOf(ResponseFactory::class, $out);
        $parts = $out->responses()->map(fn ($r) => $r->content()->toArray())->all();
        $this->assertSame('text', $parts[0]['type']);
        $this->assertSame('image', $parts[1]['type']);
        $this->assertSame('image/png', $parts[1]['mimeType']);
        $this->assertSame(base64_encode($png), $parts[1]['data']);
    }

    public function test_limits_and_mime_whitelist(): void
    {
        $r = ToolResult::success('x');
        for ($i = 0; $i < 8; $i++) {
            $r = $r->withImage($this->png());
        }
        $r = $r->withImage('GIF', 'image/gif');
        $parts = $this->convert($r)->responses()->map(fn ($x) => $x->content()->toArray()['type'])->all();

        $this->assertSame(6, count(array_filter($parts, fn ($t) => $t === 'image')));
        $this->assertSame('text', end($parts)); // Hinweis über übersprungene Bilder
    }

    public function test_oversized_image_is_downscaled(): void
    {
        $im = imagecreatetruecolor(3000, 3000);
        for ($i = 0; $i < 20000; $i++) {
            imagesetpixel($im, mt_rand(0, 2999), mt_rand(0, 2999), mt_rand(0, 0xFFFFFF));
        }
        ob_start();
        imagepng($im, null, 0);
        $big = ob_get_clean();
        $this->assertGreaterThan(1572864, strlen($big));

        $parts = $this->convert(ToolResult::success('x')->withImage($big))->responses()
            ->map(fn ($x) => $x->content()->toArray())->all();

        $this->assertSame('image', $parts[1]['type']);
        $this->assertLessThanOrEqual(1572864, strlen(base64_decode($parts[1]['data'])));
    }
}
