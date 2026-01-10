<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;

/**
 * core.ai_models.GET
 *
 * Zweck: LLM soll deterministisch alle gespeicherten Models (core_ai_models) lesen können,
 * inkl. Pricing/Token-Limits, damit Updates (PUT) ohne manuelles Copy/Paste möglich sind.
 */
class ListAiModelsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'core.ai_models.GET';
    }

    public function getDescription(): string
    {
        return 'GET /core/ai-models - Listet AI-Models aus core_ai_models. Unterstützt Filter/Search/Sort/Pagination. Optional include_provider=true um Provider-Daten mitzuladen.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['provider_key', 'model_id', 'is_active', 'is_deprecated']),
            [
                'properties' => [
                    'provider_key' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Provider-Key (z.B. "openai").',
                    ],
                    'model_id' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach exaktem model_id (z.B. "gpt-5.2").',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Aktiv-Status.',
                    ],
                    'is_deprecated' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Deprecated-Status.',
                    ],
                    'include_provider' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Wenn true, liefert provider (key/name/default_model_id) mit.',
                        'default' => false,
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = CoreAiModel::query();

            if (isset($arguments['provider_key']) && is_string($arguments['provider_key']) && trim($arguments['provider_key']) !== '') {
                $providerKey = trim($arguments['provider_key']);
                $q->whereHas('provider', function ($qq) use ($providerKey) {
                    $qq->where('key', $providerKey);
                });
            }

            if (isset($arguments['model_id']) && is_string($arguments['model_id']) && trim($arguments['model_id']) !== '') {
                $q->where('model_id', trim($arguments['model_id']));
            }

            if (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (array_key_exists('is_deprecated', $arguments)) {
                $q->where('is_deprecated', (bool) $arguments['is_deprecated']);
            }

            if (($arguments['include_provider'] ?? false) === true) {
                $q->with(['provider', 'provider.defaultModel']);
            }

            $this->applyStandardSearch($q, $arguments, ['model_id', 'name', 'description', 'category']);
            $this->applyStandardSort($q, $arguments, ['model_id', 'name', 'category', 'created_at', 'updated_at'], 'model_id', 'asc');

            $pagination = $this->applyStandardPaginationResult($q, $arguments);

            $items = $pagination['data']->map(function (CoreAiModel $m) use ($arguments) {
                $row = [
                    'core_ai_model_id' => $m->id,
                    'uuid' => $m->uuid,
                    'provider_id' => $m->provider_id,
                    'model_id' => $m->model_id,
                    'name' => $m->name,
                    'description' => $m->description,
                    'category' => $m->category,
                    'is_active' => (bool) $m->is_active,
                    'is_deprecated' => (bool) $m->is_deprecated,
                    'deprecated_at' => $m->deprecated_at?->toIso8601String(),
                    'context_window' => $m->context_window,
                    'max_output_tokens' => $m->max_output_tokens,
                    'knowledge_cutoff_date' => $m->knowledge_cutoff_date?->format('Y-m-d'),
                    'supports_reasoning_tokens' => (bool) $m->supports_reasoning_tokens,
                    'supports_streaming' => $m->supports_streaming,
                    'supports_function_calling' => $m->supports_function_calling,
                    'supports_structured_outputs' => $m->supports_structured_outputs,
                    'pricing_currency' => $m->pricing_currency,
                    'price_input_per_1m' => $m->price_input_per_1m,
                    'price_cached_input_per_1m' => $m->price_cached_input_per_1m,
                    'price_output_per_1m' => $m->price_output_per_1m,
                    'modalities' => $m->modalities,
                    'endpoints' => $m->endpoints,
                    'features' => $m->features,
                    'tools' => $m->tools,
                    'last_api_check' => $m->last_api_check?->toIso8601String(),
                    'created_at' => $m->created_at?->toIso8601String(),
                    'updated_at' => $m->updated_at?->toIso8601String(),
                ];

                if (($arguments['include_provider'] ?? false) === true) {
                    $row['provider'] = $m->provider ? [
                        'id' => $m->provider->id,
                        'key' => $m->provider->key,
                        'name' => $m->provider->name,
                        'default_model_id' => $m->provider->default_model_id,
                        'default_model' => $m->provider->defaultModel ? [
                            'core_ai_model_id' => $m->provider->defaultModel->id,
                            'model_id' => $m->provider->defaultModel->model_id,
                        ] : null,
                    ] : null;
                }

                return $row;
            })->values()->all();

            return ToolResult::success([
                'models' => $items,
                'pagination' => $pagination['pagination'],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der AI-Models: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['core', 'ai', 'models', 'list', 'pricing', 'limits'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}


