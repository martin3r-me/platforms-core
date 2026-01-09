<?php

namespace Platform\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Models\CoreAiModel;

class SyncAiModelsCommand extends Command
{
    protected $signature = 'core:ai-models:sync
        {--provider=openai : Provider key (openai)}
        {--dry-run : Show what would change without writing to DB}';

    protected $description = 'Synchronisiert AI-Provider-Modelle (z.B. OpenAI) in core_ai_models inkl. Pricing/Features Overlay';

    public function handle(): int
    {
        $providerKey = (string) $this->option('provider');
        $dryRun = (bool) $this->option('dry-run');

        if ($providerKey !== 'openai') {
            $this->error("Unbekannter provider: {$providerKey} (aktuell unterstützt: openai)");
            return 1;
        }

        $apiKey = (string) (config('services.openai.api_key') ?: (env('OPENAI_API_KEY') ?? ''));
        if ($apiKey === '') {
            $this->error('OPENAI_API_KEY fehlt (services.openai.api_key oder ENV OPENAI_API_KEY).');
            return 1;
        }

        $baseUrl = (string) (config('services.openai.base_url') ?: 'https://api.openai.com/v1');

        $providerDefaults = [
            'key' => 'openai',
            'name' => 'OpenAI',
            'base_url' => $baseUrl,
            'is_active' => true,
        ];

        $provider = CoreAiProvider::where('key', 'openai')->first();
        if (!$provider) {
            if ($dryRun) {
                $this->info("DRY RUN: würde core_ai_provider anlegen: openai ({$baseUrl})");
                $provider = new CoreAiProvider($providerDefaults);
            } else {
                $provider = CoreAiProvider::create($providerDefaults);
            }
        } else {
            if (!$dryRun) {
                $provider->update([
                    'name' => $providerDefaults['name'],
                    'base_url' => $baseUrl,
                    'is_active' => true,
                ]);
            }
        }

        // Static overlay (source of truth for pricing/cutoff/limits/features).
        // OpenAI /models does NOT expose these reliably.
        $catalog = $this->openAiStaticCatalog();

        $this->info("Sync provider=openai base_url={$baseUrl}" . ($dryRun ? ' (DRY RUN)' : ''));

        $resp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->get(rtrim($baseUrl, '/') . '/models');

        if (!$resp->successful()) {
            $this->error('OpenAI /models failed: ' . $resp->status());
            Log::error('[core:ai-models:sync] OpenAI /models failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);
            return 1;
        }

        $apiModels = $resp->json('data', []);
        if (!is_array($apiModels)) $apiModels = [];

        $apiIds = [];
        $synced = 0;
        $updated = 0;
        $created = 0;

        foreach ($apiModels as $m) {
            $id = $m['id'] ?? null;
            if (!is_string($id) || $id === '') continue;
            $apiIds[] = $id;

            $overlay = $catalog[$id] ?? [];

            $attrs = [
                'provider_id' => $provider->id,
                'model_id' => $id,
                'name' => (string) ($overlay['name'] ?? $id),
                'description' => $overlay['description'] ?? null,
                'category' => $overlay['category'] ?? null,
                'is_active' => true,
                'is_deprecated' => false,
                'deprecated_at' => null,
                'context_window' => $overlay['context_window'] ?? null,
                'max_output_tokens' => $overlay['max_output_tokens'] ?? null,
                'knowledge_cutoff_date' => $overlay['knowledge_cutoff_date'] ?? null,
                'supports_reasoning_tokens' => (bool) ($overlay['supports_reasoning_tokens'] ?? false),
                'supports_streaming' => $overlay['supports_streaming'] ?? null,
                'supports_function_calling' => $overlay['supports_function_calling'] ?? null,
                'supports_structured_outputs' => $overlay['supports_structured_outputs'] ?? null,
                'pricing_currency' => $overlay['pricing_currency'] ?? 'USD',
                'price_input_per_1m' => $overlay['price_input_per_1m'] ?? null,
                'price_cached_input_per_1m' => $overlay['price_cached_input_per_1m'] ?? null,
                'price_output_per_1m' => $overlay['price_output_per_1m'] ?? null,
                'modalities' => $overlay['modalities'] ?? null,
                'endpoints' => $overlay['endpoints'] ?? null,
                'features' => $overlay['features'] ?? null,
                'tools' => $overlay['tools'] ?? null,
                'api_metadata' => $m,
                'last_api_check' => now(),
            ];

            $existing = CoreAiModel::where('provider_id', $provider->id)->where('model_id', $id)->first();
            if (!$existing) {
                $created++;
                $synced++;
                if ($dryRun) continue;
                CoreAiModel::create($attrs);
            } else {
                $updated++;
                $synced++;
                if ($dryRun) continue;
                $existing->update($attrs);
            }
        }

        // Deprecate models that vanished from API
        $apiIds = array_values(array_unique($apiIds));
        if (!$dryRun) {
            CoreAiModel::where('provider_id', $provider->id)
                ->where('is_deprecated', false)
                ->whereNotIn('model_id', $apiIds)
                ->update([
                    'is_deprecated' => true,
                    'deprecated_at' => now(),
                ]);
        }

        $this->info("Done. synced={$synced} created={$created} updated={$updated}" . ($dryRun ? ' (dry-run)' : ''));

        // Hint for pricing gaps
        $missingPricing = CoreAiModel::where('provider_id', $provider->id)
            ->whereNull('price_input_per_1m')
            ->where('is_deprecated', false)
            ->count();
        if ($missingPricing > 0) {
            $this->warn("Hinweis: {$missingPricing} aktive Modelle haben noch keine Pricing-Felder (nur Overlay füllt Preise).");
        }

        return 0;
    }

    /**
     * Minimaler statischer Katalog (erweiterbar).
     * Keys = provider model_id
     */
    private function openAiStaticCatalog(): array
    {
        return [
            'gpt-5.2' => [
                'name' => 'GPT-5.2',
                'category' => 'GPT-5',
                'description' => 'Flagship model for coding and agentic tasks across industries.',
                'context_window' => 400000,
                'max_output_tokens' => 128000,
                'knowledge_cutoff_date' => '2025-08-31',
                'supports_reasoning_tokens' => true,
                'supports_streaming' => true,
                'supports_function_calling' => true,
                'supports_structured_outputs' => true,
                'pricing_currency' => 'USD',
                'price_input_per_1m' => 1.75,
                'price_cached_input_per_1m' => 0.175,
                'price_output_per_1m' => 14.00,
                'modalities' => [
                    'text' => ['input' => true, 'output' => true],
                    'image' => ['input' => true, 'output' => false],
                    'audio' => ['input' => false, 'output' => false],
                    'video' => ['input' => false, 'output' => false],
                ],
                'endpoints' => [
                    'chat_completions' => '/v1/chat/completions',
                    'responses' => '/v1/responses',
                    'realtime' => '/v1/realtime',
                    'assistants' => '/v1/assistants',
                    'batch' => '/v1/batch',
                    'fine_tuning' => '/v1/fine-tuning',
                    'embeddings' => '/v1/embeddings',
                    'image_generation' => '/v1/images/generations',
                    'videos' => '/v1/videos',
                    'image_edit' => '/v1/images/edits',
                    'speech' => '/v1/audio/speech',
                    'transcription' => '/v1/audio/transcriptions',
                    'translation' => '/v1/audio/translations',
                    'moderation' => '/v1/moderations',
                    'completions_legacy' => '/v1/completions',
                ],
                'features' => [
                    'streaming' => true,
                    'function_calling' => true,
                    'structured_outputs' => true,
                    'fine_tuning' => false,
                    'distillation' => true,
                ],
                'tools' => [
                    'web_search' => true,
                    'file_search' => true,
                    'image_generation' => true,
                    'code_interpreter' => true,
                    'computer_use' => false,
                    'mcp' => true,
                ],
            ],
            // Pricing-only quick comparison (extend later)
            'gpt-5' => [
                'name' => 'GPT-5',
                'category' => 'GPT-5',
                'pricing_currency' => 'USD',
                'price_input_per_1m' => 1.25,
            ],
            'gpt-5-mini' => [
                'name' => 'GPT-5 mini',
                'category' => 'GPT-5',
                'pricing_currency' => 'USD',
                'price_input_per_1m' => 0.25,
            ],
        ];
    }
}


