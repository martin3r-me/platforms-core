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
        {--catalog-url= : Optional catalog JSON endpoint (overrides provider.catalog_url)}
        {--no-catalog : Do not fetch catalog overlay (only /models)}
        {--dry-run : Show what would change without writing to DB}';

    protected $description = 'Synchronisiert AI-Provider-Modelle (z.B. OpenAI) in core_ai_models inkl. Pricing/Features Overlay';

    public function handle(): int
    {
        $providerKey = (string) $this->option('provider');
        $dryRun = (bool) $this->option('dry-run');
        $catalogUrlOpt = $this->option('catalog-url');
        $noCatalog = (bool) $this->option('no-catalog');

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

        // Provider catalog overlay (pricing/cutoff/limits/features) comes from a catalog API endpoint.
        // OpenAI /models does NOT expose these reliably, so "per API" means: provider-specific catalog URL.
        $catalog = [];
        $catalogUrl = null;
        if (!$noCatalog) {
            if (is_string($catalogUrlOpt) && trim($catalogUrlOpt) !== '') {
                $catalogUrl = trim($catalogUrlOpt);
            } elseif (!empty($provider->catalog_url)) {
                $catalogUrl = (string) $provider->catalog_url;
            } elseif (is_array($provider->metadata) && !empty($provider->metadata['catalog_url'])) {
                $catalogUrl = (string) $provider->metadata['catalog_url'];
            }

            if (is_string($catalogUrl) && $catalogUrl !== '') {
                $catalog = $this->fetchCatalogOverlay($catalogUrl);
                $this->info("Catalog overlay: " . (count($catalog) ? ("loaded " . count($catalog) . " models") : "empty") . " ({$catalogUrl})");
            } else {
                $this->warn("Catalog overlay: keine catalog_url gesetzt (Provider). Preise/Features bleiben leer. "
                    . "Setze core_ai_providers.catalog_url oder nutze --catalog-url.");
            }
        }

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
                'supports_temperature' => $overlay['supports_temperature'] ?? null,
                'supports_top_p' => $overlay['supports_top_p'] ?? null,
                'supports_presence_penalty' => $overlay['supports_presence_penalty'] ?? null,
                'supports_frequency_penalty' => $overlay['supports_frequency_penalty'] ?? null,
                // Pricing: only set when overlay provides it; otherwise keep DB values (manual edits).
                'modalities' => $overlay['modalities'] ?? null,
                'endpoints' => $overlay['endpoints'] ?? null,
                'features' => $overlay['features'] ?? null,
                'tools' => $overlay['tools'] ?? null,
                'api_metadata' => $m,
                'last_api_check' => now(),
            ];

            // Heuristics (only when catalog overlay doesn't provide flags):
            // OpenAI /models doesn't expose supported request parameters. We therefore set conservative defaults:
            // - gpt-5* (incl. gpt-5.2*) rejects temperature/top_p/presence_penalty/frequency_penalty in Responses API.
            // Everything else remains NULL (= unknown) unless catalog overlay provides it.
            if (!array_key_exists('supports_temperature', $overlay)) {
                if (str_starts_with($id, 'gpt-5')) {
                    $attrs['supports_temperature'] = false;
                }
            }
            if (!array_key_exists('supports_top_p', $overlay)) {
                if (str_starts_with($id, 'gpt-5')) {
                    $attrs['supports_top_p'] = false;
                }
            }
            if (!array_key_exists('supports_presence_penalty', $overlay)) {
                if (str_starts_with($id, 'gpt-5')) {
                    $attrs['supports_presence_penalty'] = false;
                }
            }
            if (!array_key_exists('supports_frequency_penalty', $overlay)) {
                if (str_starts_with($id, 'gpt-5')) {
                    $attrs['supports_frequency_penalty'] = false;
                }
            }

            $hasAnyPricing = array_key_exists('price_input_per_1m', $overlay)
                || array_key_exists('price_cached_input_per_1m', $overlay)
                || array_key_exists('price_output_per_1m', $overlay)
                || array_key_exists('pricing_currency', $overlay);
            if ($hasAnyPricing) {
                $attrs['pricing_currency'] = $overlay['pricing_currency'] ?? 'USD';
                $attrs['price_input_per_1m'] = $overlay['price_input_per_1m'] ?? null;
                $attrs['price_cached_input_per_1m'] = $overlay['price_cached_input_per_1m'] ?? null;
                $attrs['price_output_per_1m'] = $overlay['price_output_per_1m'] ?? null;
            }

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
                // Don't wipe manual prices if overlay has no pricing.
                if (!$hasAnyPricing) {
                    unset($attrs['pricing_currency'], $attrs['price_input_per_1m'], $attrs['price_cached_input_per_1m'], $attrs['price_output_per_1m']);
                }
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

            // Default model bootstrap: if none set yet, use gpt-5.2 when available.
            $provider->refresh();
            if (empty($provider->default_model_id)) {
                $default = CoreAiModel::where('provider_id', $provider->id)
                    ->where('model_id', 'gpt-5.2')
                    ->where('is_active', true)
                    ->where('is_deprecated', false)
                    ->first();
                if ($default) {
                    $provider->update(['default_model_id' => $default->id]);
                }
            }
        }

        $this->info("Done. synced={$synced} created={$created} updated={$updated}" . ($dryRun ? ' (dry-run)' : ''));

        // Hint for pricing gaps
        if (!$dryRun) {
            $missingPricing = CoreAiModel::where('provider_id', $provider->id)
                ->whereNull('price_input_per_1m')
                ->where('is_deprecated', false)
                ->count();
            if ($missingPricing > 0) {
                $this->warn("Hinweis: {$missingPricing} aktive Modelle haben noch keine Pricing-Felder. "
                    . "Das ist erwartbar, wenn catalog_url nicht gesetzt ist oder der Catalog unvollständig ist.");
            }
        }

        return 0;
    }

    /**
     * Fetch catalog overlay JSON from a URL.
     *
     * Expected formats (either is OK):
     * 1) { "models": { "gpt-5.2": { ... }, "gpt-5": { ... } } }
     * 2) [ { "model_id": "gpt-5.2", ... }, ... ]
     *
     * Returns: [model_id => overlayArray]
     */
    private function fetchCatalogOverlay(string $url): array
    {
        try {
            $resp = Http::timeout(15)->get($url);
            if (!$resp->successful()) {
                $this->warn("Catalog fetch failed: HTTP {$resp->status()}");
                return [];
            }
            $json = $resp->json();

            if (is_array($json) && isset($json['models']) && is_array($json['models'])) {
                return $json['models'];
            }

            // list format
            if (is_array($json) && array_keys($json) === range(0, count($json) - 1)) {
                $out = [];
                foreach ($json as $row) {
                    if (!is_array($row)) continue;
                    $id = $row['model_id'] ?? $row['id'] ?? null;
                    if (is_string($id) && $id !== '') {
                        $out[$id] = $row;
                    }
                }
                return $out;
            }

            return [];
        } catch (\Throwable $e) {
            $this->warn('Catalog fetch exception: ' . $e->getMessage());
            return [];
        }
    }
}


