<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Enums\TeamRole;
use Platform\Core\Models\CoreAiModel;
use Platform\Core\Models\CoreAiProvider;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;

/**
 * core.ai_models.PUT
 *
 * Ziel: Modelle/Preise/Limits ohne "DB Handarbeit" per LLM anpassen können.
 * Hinweis: Core-Tools sind grundsätzlich sichtbar → daher strikte Berechtigungsprüfung im Tool.
 */
class UpdateAiModelTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'core.ai_models.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /core/ai-models - Aktualisiert ein AI-Model in core_ai_models (z.B. max_output_tokens, pricing). ERFORDERLICH: core_ai_model_id ODER (provider_key + model_id).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'core_ai_model_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Primärschlüssel in core_ai_models. Empfohlen: erst core.ai_models.GET nutzen und dann per ID updaten.',
                ],
                'provider_key' => [
                    'type' => 'string',
                    'description' => 'Optional: Provider-Key (z.B. "openai"). Wenn core_ai_model_id nicht gesetzt ist, zusammen mit model_id erforderlich.',
                ],
                'model_id' => [
                    'type' => 'string',
                    'description' => 'Optional: Provider-Model-ID (z.B. "gpt-5.2"). Wenn core_ai_model_id nicht gesetzt ist, zusammen mit provider_key erforderlich.',
                ],

                // Editable fields (subset)
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
                'is_deprecated' => ['type' => 'boolean'],
                'context_window' => ['type' => 'integer'],
                'max_output_tokens' => ['type' => 'integer', 'description' => 'Max Output Tokens für dieses Modell. NULL erlaubt (unbekannt).'],
                'knowledge_cutoff_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD oder null.'],
                'supports_reasoning_tokens' => ['type' => 'boolean'],
                'supports_streaming' => ['type' => 'boolean'],
                'supports_function_calling' => ['type' => 'boolean'],
                'supports_structured_outputs' => ['type' => 'boolean'],
                'supports_temperature' => ['type' => 'boolean', 'description' => 'Ob das Modell temperature im Responses API akzeptiert. NULL = unbekannt.'],
                'supports_top_p' => ['type' => 'boolean', 'description' => 'Ob das Modell top_p akzeptiert. NULL = unbekannt.'],
                'supports_presence_penalty' => ['type' => 'boolean', 'description' => 'Ob das Modell presence_penalty akzeptiert. NULL = unbekannt.'],
                'supports_frequency_penalty' => ['type' => 'boolean', 'description' => 'Ob das Modell frequency_penalty akzeptiert. NULL = unbekannt.'],

                // Pricing
                'pricing_currency' => ['type' => 'string', 'description' => 'z.B. USD, EUR'],
                'price_input_per_1m' => ['type' => 'number'],
                'price_cached_input_per_1m' => ['type' => 'number'],
                'price_output_per_1m' => ['type' => 'number'],

                // JSON fields
                'modalities' => ['type' => 'array', 'items' => ['type' => 'string']],
                'endpoints' => ['type' => 'array', 'items' => ['type' => 'string']],
                'features' => ['type' => 'array', 'items' => ['type' => 'string']],
                'tools' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => [],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$this->canManageAiModels($context)) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst AI-Models nicht bearbeiten (nur Owner des Root/Eltern-Teams).');
            }

            $model = $this->findTargetModel($arguments);
            if (!$model) {
                return ToolResult::error(
                    'MODEL_NOT_FOUND',
                    'AI-Model nicht gefunden. Nutze core.ai_models.GET und verwende core_ai_model_id, oder gib provider_key + model_id an.'
                );
            }

            $update = [];
            $allowed = [
                'name',
                'description',
                'category',
                'is_active',
                'is_deprecated',
                'context_window',
                'max_output_tokens',
                'knowledge_cutoff_date',
                'supports_reasoning_tokens',
                'supports_streaming',
                'supports_function_calling',
                'supports_structured_outputs',
                'supports_temperature',
                'supports_top_p',
                'supports_presence_penalty',
                'supports_frequency_penalty',
                'pricing_currency',
                'price_input_per_1m',
                'price_cached_input_per_1m',
                'price_output_per_1m',
                'modalities',
                'endpoints',
                'features',
                'tools',
            ];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = $arguments[$field];
                }
            }

            if (empty($update)) {
                return ToolResult::success([
                    'core_ai_model_id' => $model->id,
                    'model_id' => $model->model_id,
                    'message' => 'Keine Änderungen übergeben.',
                ]);
            }

            // Normalize
            if (array_key_exists('pricing_currency', $update) && is_string($update['pricing_currency'])) {
                $update['pricing_currency'] = strtoupper(trim($update['pricing_currency']));
            }
            if (array_key_exists('knowledge_cutoff_date', $update)) {
                $v = $update['knowledge_cutoff_date'];
                if ($v === '' || $v === 0 || $v === '0') {
                    $update['knowledge_cutoff_date'] = null;
                }
            }
            if (array_key_exists('max_output_tokens', $update)) {
                $v = $update['max_output_tokens'];
                if ($v === '' || $v === 0 || $v === '0') {
                    // 0 wird häufig "unset" verwendet → NULL (unknown)
                    $update['max_output_tokens'] = null;
                }
            }

            $model->fill($update);
            $model->save();

            return ToolResult::success([
                'core_ai_model_id' => $model->id,
                'provider_id' => $model->provider_id,
                'model_id' => $model->model_id,
                'name' => $model->name,
                'max_output_tokens' => $model->max_output_tokens,
                'supports_temperature' => $model->supports_temperature,
                'supports_top_p' => $model->supports_top_p,
                'supports_presence_penalty' => $model->supports_presence_penalty,
                'supports_frequency_penalty' => $model->supports_frequency_penalty,
                'pricing_currency' => $model->pricing_currency,
                'price_input_per_1m' => $model->price_input_per_1m,
                'price_cached_input_per_1m' => $model->price_cached_input_per_1m,
                'price_output_per_1m' => $model->price_output_per_1m,
                'is_active' => (bool)$model->is_active,
                'is_deprecated' => (bool)$model->is_deprecated,
                'updated_at' => $model->updated_at?->toIso8601String(),
                'message' => 'AI-Model erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des AI-Models: ' . $e->getMessage());
        }
    }

    private function findTargetModel(array $arguments): ?CoreAiModel
    {
        if (!empty($arguments['core_ai_model_id'])) {
            $id = (int) $arguments['core_ai_model_id'];
            if ($id > 0) {
                return CoreAiModel::query()->with('provider')->find($id);
            }
        }

        $providerKey = isset($arguments['provider_key']) ? trim((string)$arguments['provider_key']) : '';
        $modelId = isset($arguments['model_id']) ? trim((string)$arguments['model_id']) : '';
        if ($providerKey === '' || $modelId === '') {
            return null;
        }

        $provider = CoreAiProvider::query()->where('key', $providerKey)->first();
        if (!$provider) {
            return null;
        }

        return CoreAiModel::query()
            ->where('provider_id', $provider->id)
            ->where('model_id', $modelId)
            ->first();
    }

    private function canManageAiModels(ToolContext $context): bool
    {
        // Core-Settings sind global; wir binden das an den Owner des Root/Eltern-Teams
        // (also "Parent-Team Owner" bzw. Root-Team Owner).
        $team = $context->team;
        if (!$team || !$context->user) {
            return false;
        }

        // resolve root team (falls Kind-Team gewählt ist)
        $rootTeamId = method_exists($team, 'getRootTeam') ? $team->getRootTeam()->id : ($team->id ?? null);
        if (!$rootTeamId) {
            return false;
        }

        return $context->user->teams()
            ->where('teams.id', $rootTeamId)
            ->wherePivot('role', TeamRole::OWNER->value)
            ->exists();
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'ai', 'models', 'update', 'pricing', 'limits'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}


