<?php

namespace Platform\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\ContextFile;
use Platform\Core\Models\CoreExtraFieldValue;
use Platform\Core\Services\OpenAiService;

class VerifyExtraFieldValueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 2;

    public function __construct(
        private int $fieldValueId
    ) {}

    public function handle(OpenAiService $openAi): void
    {
        $fieldValue = CoreExtraFieldValue::with('definition')->find($this->fieldValueId);

        if (!$fieldValue) {
            Log::warning('VerifyExtraFieldValueJob: FieldValue not found', [
                'field_value_id' => $this->fieldValueId,
            ]);
            return;
        }

        $definition = $fieldValue->definition;

        if (!$definition || !$definition->verify_by_llm) {
            Log::info('VerifyExtraFieldValueJob: LLM verification not enabled', [
                'field_value_id' => $this->fieldValueId,
                'definition_id' => $definition?->id,
            ]);
            return;
        }

        // Set status to "verifying"
        $fieldValue->update(['verification_status' => 'verifying']);

        try {
            // Get file IDs from the value
            $fileIds = $fieldValue->typed_value;

            if (empty($fileIds)) {
                $fieldValue->update([
                    'verification_status' => 'error',
                    'verification_result' => ['error' => 'Keine Dateien vorhanden'],
                ]);
                return;
            }

            // Normalize to array
            $fileIds = is_array($fileIds) ? $fileIds : [$fileIds];
            $files = ContextFile::whereIn('id', $fileIds)->get();

            if ($files->isEmpty()) {
                $fieldValue->update([
                    'verification_status' => 'error',
                    'verification_result' => ['error' => 'Dateien nicht gefunden'],
                ]);
                return;
            }

            // Build content array with images
            $content = [
                ['type' => 'text', 'text' => $definition->verify_instructions ?? 'Prüfe ob das Dokument gültig ist.'],
            ];

            $hasImages = false;
            foreach ($files as $file) {
                if ($file->isImage()) {
                    $hasImages = true;
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $file->url,
                            'detail' => 'high',
                        ],
                    ];
                }
            }

            if (!$hasImages) {
                $fieldValue->update([
                    'verification_status' => 'error',
                    'verification_result' => ['error' => 'Keine Bilder zur Verifikation gefunden'],
                ]);
                return;
            }

            Log::info('VerifyExtraFieldValueJob: Calling LLM', [
                'field_value_id' => $this->fieldValueId,
                'files_count' => $files->count(),
                'instructions' => $definition->verify_instructions,
            ]);

            // Call LLM with Vision
            $response = $openAi->chat([
                [
                    'role' => 'system',
                    'content' => 'Du bist ein Dokument-Verifizierer. Analysiere das Bild und antworte NUR mit einem JSON-Objekt in diesem Format (kein Markdown, keine Code-Blöcke, nur JSON): {"verified": true/false, "confidence": 0-100, "reason": "Kurze Begründung auf Deutsch"}',
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ], 'gpt-4o', [
                'max_tokens' => 500,
                'tools' => false, // Disable tools for this request
            ]);

            Log::info('VerifyExtraFieldValueJob: LLM Response received', [
                'field_value_id' => $this->fieldValueId,
                'response' => $response,
            ]);

            // Parse the response
            $result = $this->parseVerificationResponse($response);

            $fieldValue->update([
                'verification_status' => $result['verified'] ? 'verified' : 'rejected',
                'verification_result' => $result,
                'verified_at' => now(),
            ]);

            Log::info('VerifyExtraFieldValueJob: Verification completed', [
                'field_value_id' => $this->fieldValueId,
                'status' => $result['verified'] ? 'verified' : 'rejected',
                'confidence' => $result['confidence'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('VerifyExtraFieldValueJob: Error during verification', [
                'field_value_id' => $this->fieldValueId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $fieldValue->update([
                'verification_status' => 'error',
                'verification_result' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * Parse the LLM response into a structured result
     */
    private function parseVerificationResponse(array $response): array
    {
        // Extract text content from the response
        $text = '';

        if (isset($response['content'])) {
            $text = $response['content'];
        } elseif (isset($response['output']) && is_array($response['output'])) {
            foreach ($response['output'] as $output) {
                if (isset($output['content']) && is_array($output['content'])) {
                    foreach ($output['content'] as $content) {
                        if (isset($content['text'])) {
                            $text .= $content['text'];
                        }
                    }
                }
            }
        } elseif (isset($response['choices'][0]['message']['content'])) {
            $text = $response['choices'][0]['message']['content'];
        }

        // Try to parse JSON from the response
        $text = trim($text);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $result = json_decode($text, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
            return [
                'verified' => (bool) ($result['verified'] ?? false),
                'confidence' => (int) ($result['confidence'] ?? 0),
                'reason' => (string) ($result['reason'] ?? 'Keine Begründung angegeben'),
                'raw_response' => $text,
            ];
        }

        // If JSON parsing fails, try to extract meaning from text
        $isVerified = str_contains(strtolower($text), 'verifiziert')
            || str_contains(strtolower($text), 'gültig')
            || str_contains(strtolower($text), 'valid')
            || str_contains(strtolower($text), '"verified": true')
            || str_contains(strtolower($text), '"verified":true');

        return [
            'verified' => $isVerified,
            'confidence' => 50, // Unknown confidence
            'reason' => 'Konnte JSON-Antwort nicht parsen: ' . substr($text, 0, 200),
            'raw_response' => $text,
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('VerifyExtraFieldValueJob: Job failed permanently', [
            'field_value_id' => $this->fieldValueId,
            'error' => $e->getMessage(),
        ]);

        CoreExtraFieldValue::where('id', $this->fieldValueId)->update([
            'verification_status' => 'error',
            'verification_result' => ['error' => $e->getMessage()],
        ]);
    }
}
