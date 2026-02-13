<?php

namespace Platform\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

/**
 * Service für KI-Bildgenerierung via OpenAI DALL-E
 *
 * Kapselt die OpenAI Images API und ermöglicht:
 * - Bildgenerierung mit verschiedenen Parametern
 * - Optionale direkte Speicherung an Context-Objekte
 */
class ImageGenerationService
{
    private const API_URL = 'https://api.openai.com/v1/images/generations';
    private const DEFAULT_MODEL = 'gpt-image-1.5';
    private const DEFAULT_SIZE = '1024x1024';
    private const DEFAULT_QUALITY = 'standard';
    private const DEFAULT_STYLE = 'vivid';

    /**
     * Valid sizes for gpt-image-1
     */
    private const VALID_SIZES = [
        '1024x1024',
        '1536x1024',  // Landscape
        '1024x1536',  // Portrait
        'auto',       // Let model decide
    ];

    /**
     * Valid quality options
     */
    private const VALID_QUALITIES = ['low', 'standard', 'high'];

    /**
     * Valid style options (gpt-image-1 may not use this, but keep for compatibility)
     */
    private const VALID_STYLES = ['vivid', 'natural'];

    /**
     * Generate an image using OpenAI DALL-E
     *
     * @param string $prompt The image description
     * @param array $options [size, quality, style]
     * @return array ['url' => string, 'revised_prompt' => string]
     * @throws \Exception
     */
    public function generate(string $prompt, array $options = []): array
    {
        $apiKey = $this->getApiKey();

        $size = $options['size'] ?? self::DEFAULT_SIZE;
        $quality = $options['quality'] ?? self::DEFAULT_QUALITY;
        $style = $options['style'] ?? self::DEFAULT_STYLE;

        // Validate parameters
        if (!in_array($size, self::VALID_SIZES, true)) {
            throw new \InvalidArgumentException(
                "Ungültige Größe: {$size}. Erlaubt: " . implode(', ', self::VALID_SIZES)
            );
        }
        if (!in_array($quality, self::VALID_QUALITIES, true)) {
            throw new \InvalidArgumentException(
                "Ungültige Qualität: {$quality}. Erlaubt: " . implode(', ', self::VALID_QUALITIES)
            );
        }
        if (!in_array($style, self::VALID_STYLES, true)) {
            throw new \InvalidArgumentException(
                "Ungültiger Stil: {$style}. Erlaubt: " . implode(', ', self::VALID_STYLES)
            );
        }

        Log::info('[ImageGenerationService] Generating image', [
            'prompt_length' => strlen($prompt),
            'size' => $size,
            'quality' => $quality,
            'style' => $style,
        ]);

        $payload = [
            'model' => self::DEFAULT_MODEL,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality,
        ];

        // Style is optional for gpt-image-1
        if ($style && $style !== 'vivid') {
            $payload['style'] = $style;
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post(self::API_URL, $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? $response->body();
            Log::error('[ImageGenerationService] API error', [
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \Exception("OpenAI API Fehler: {$error}");
        }

        $data = $response->json('data.0');
        if (!$data) {
            throw new \Exception('Keine Bilddaten in der API-Antwort');
        }

        // gpt-image-1 can return either URL or base64
        $imageUrl = $data['url'] ?? null;
        $b64Data = $data['b64_json'] ?? null;

        if (!$imageUrl && !$b64Data) {
            throw new \Exception('Weder URL noch Base64-Daten in der API-Antwort');
        }

        Log::info('[ImageGenerationService] Image generated successfully', [
            'revised_prompt' => substr($data['revised_prompt'] ?? '', 0, 100),
            'format' => $imageUrl ? 'url' : 'base64',
        ]);

        return [
            'url' => $imageUrl,
            'b64_json' => $b64Data,
            'revised_prompt' => $data['revised_prompt'] ?? $prompt,
        ];
    }

    /**
     * Generate an image and store it in a context
     *
     * @param string $prompt The image description
     * @param string $contextType Model class name
     * @param int $contextId Model ID
     * @param int $userId User ID
     * @param int $teamId Team ID
     * @param array $options [size, quality, style]
     * @return array ['id', 'token', 'url', 'variants_count', 'revised_prompt']
     */
    public function generateAndStore(
        string $prompt,
        string $contextType,
        int $contextId,
        int $userId,
        int $teamId,
        array $options = []
    ): array {
        // Generate the image
        $result = $this->generate($prompt, $options);
        $revisedPrompt = $result['revised_prompt'];

        // Get image content (either from URL or base64)
        if ($result['url']) {
            $imageResponse = Http::timeout(60)->get($result['url']);
            if (!$imageResponse->successful()) {
                throw new \Exception('Fehler beim Herunterladen des generierten Bildes');
            }
            $imageContent = $imageResponse->body();
            $mimeType = $imageResponse->header('Content-Type') ?? 'image/png';
        } elseif ($result['b64_json']) {
            $imageContent = base64_decode($result['b64_json']);
            $mimeType = 'image/png';
        } else {
            throw new \Exception('Keine Bilddaten verfügbar');
        }

        // Create temp file for ContextFileService
        $tempPath = tempnam(sys_get_temp_dir(), 'dalle_');
        file_put_contents($tempPath, $imageContent);

        try {
            // Determine filename from size
            $size = $options['size'] ?? self::DEFAULT_SIZE;
            $fileName = "generated_{$size}.png";

            $uploadedFile = new UploadedFile(
                $tempPath,
                $fileName,
                $mimeType,
                null,
                true // test mode
            );

            // Use ContextFileService for WebP + variants
            $contextFileService = app(ContextFileService::class);
            $fileResult = $contextFileService->uploadForContext(
                $uploadedFile,
                $contextType,
                $contextId,
                [
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'generate_variants' => true,
                ]
            );

            @unlink($tempPath);

            return [
                'id' => $fileResult['id'],
                'token' => $fileResult['token'],
                'url' => $fileResult['url'],
                'width' => $fileResult['width'],
                'height' => $fileResult['height'],
                'mime_type' => $fileResult['mime_type'],
                'file_size' => $fileResult['file_size'],
                'variants_count' => count($fileResult['variants'] ?? []),
                'revised_prompt' => $revisedPrompt,
                'context_type' => $contextType,
                'context_id' => $contextId,
            ];
        } catch (\Exception $e) {
            @unlink($tempPath);
            throw $e;
        }
    }

    /**
     * Get OpenAI API key
     */
    private function getApiKey(): string
    {
        $key = config('services.openai.api_key');
        if (!is_string($key) || $key === '') {
            $key = config('services.openai.key') ?? '';
        }
        if ($key === '') {
            $key = env('OPENAI_API_KEY') ?? '';
        }
        if ($key === '') {
            throw new \RuntimeException('OPENAI_API_KEY fehlt oder ist leer.');
        }
        return $key;
    }
}
