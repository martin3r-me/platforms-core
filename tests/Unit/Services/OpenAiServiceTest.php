<?php

namespace Platform\Core\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Platform\Core\Services\OpenAiService;

class OpenAiServiceTest extends TestCase
{
    private function buildResponsesInput(array $messages): array
    {
        $service = new OpenAiService();
        $method = new \ReflectionMethod(OpenAiService::class, 'buildResponsesInput');
        $method->setAccessible(true);

        return $method->invoke($service, $messages);
    }

    public function test_plain_string_content_is_unchanged(): void
    {
        $input = $this->buildResponsesInput([
            ['role' => 'user', 'content' => 'Hallo Welt'],
        ]);

        $this->assertSame('Hallo Welt', $input[0]['content']);
    }

    public function test_responses_shape_array_content_is_passed_through(): void
    {
        $content = [
            ['type' => 'input_text', 'text' => 'Was ist auf dem Bild?'],
            ['type' => 'input_image', 'image_url' => 'https://example.test/foto.jpg'],
        ];

        $input = $this->buildResponsesInput([
            ['role' => 'user', 'content' => $content],
        ]);

        $this->assertSame($content, $input[0]['content']);
    }

    public function test_legacy_chat_completions_image_content_is_normalized(): void
    {
        // Format, das VerifyExtraFieldValueJob::handle() verwendet
        $legacyContent = [
            ['type' => 'text', 'text' => 'Pruefe das Dokument.'],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'https://example.test/scan.jpg',
                    'detail' => 'high',
                ],
            ],
        ];

        $input = $this->buildResponsesInput([
            ['role' => 'user', 'content' => $legacyContent],
        ]);

        $this->assertSame([
            ['type' => 'input_text', 'text' => 'Pruefe das Dokument.'],
            [
                'type' => 'input_image',
                'image_url' => 'https://example.test/scan.jpg',
                'detail' => 'high',
            ],
        ], $input[0]['content']);
    }
}
