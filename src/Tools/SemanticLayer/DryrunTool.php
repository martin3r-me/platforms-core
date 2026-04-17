<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\Services\OpenAiService;

/**
 * core.semantic_layer.dryrun.POST
 *
 * Dünner Adapter, der serverseitig einen LLM-Call gegen OpenAiService::chat()
 * ausführt. Der Semantic Layer wird dort automatisch via CoreContextTool in den
 * System-Prompt gemischt (`with_context=true`). Das Tool liefert den Modell-
 * Output 1:1 zurück + zusätzlich `layer_active` + `layer_meta`.
 *
 * Bewusst: kein Tool-Loop, keine State-Effekte, keine Persistenz — reines
 * Test-/Iterations-Werkzeug, owner-only.
 */
class DryrunTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    private const PROMPT_MAX_LENGTH = 2000;
    private const MAX_TOKENS_HARD_CAP = 2000;
    private const MAX_TOKENS_DEFAULT = 500;
    private const TEMPERATURE_DEFAULT = 0.7;

    public function __construct(
        private readonly SemanticLayerResolver $resolver,
        private readonly OpenAiService $openAi,
    ) {
    }

    public function getName(): string
    {
        return 'core.semantic_layer.dryrun.POST';
    }

    public function getDescription(): string
    {
        return 'Dryrun-Tool: triggert serverseitig einen LLM-Call und gibt die Antwort inkl. Layer-Meta zurück. '
            . 'Der Semantic Layer wird dabei automatisch in den System-Prompt gemischt (falls aktiv) — so ist die '
            . 'Layer-Wirkung in der Server-Antwort unmittelbar verifizierbar (A/B-Vergleich mit Layer ein/aus). '
            . 'Single-Turn, kein Tool-Loop, keine Persistenz. Owner-only.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'minLength' => 1,
                    'maxLength' => self::PROMPT_MAX_LENGTH,
                    'description' => 'User-Prompt für den LLM-Call.',
                ],
                'module' => [
                    'type' => ['string', 'null'],
                    'description' => 'Kontext-Key, für den der Layer gerendert werden soll (z.B. "planner", "mcp").',
                ],
                'system' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optionaler zusätzlicher System-Prompt-Prefix.',
                ],
                'max_tokens' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Maximale Output-Tokens. Default 500, Hard-Cap ' . self::MAX_TOKENS_HARD_CAP . '.',
                ],
                'temperature' => [
                    'type' => ['number', 'null'],
                    'description' => 'Temperatur für den LLM-Call. Default 0.7.',
                ],
                'model' => [
                    'type' => ['string', 'null'],
                    'description' => 'Optional: abweichendes Modell.',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if ($denied = $this->assertOwner($context)) {
            return $denied;
        }

        // ---- Validation ----
        $prompt = $arguments['prompt'] ?? null;
        if (!is_string($prompt) || trim($prompt) === '') {
            return ToolResult::error('VALIDATION_ERROR', 'prompt ist erforderlich (nicht leer).');
        }
        if (strlen($prompt) > self::PROMPT_MAX_LENGTH) {
            return ToolResult::error(
                'VALIDATION_ERROR',
                'prompt ist zu lang (max ' . self::PROMPT_MAX_LENGTH . ' Zeichen).'
            );
        }

        $module = $arguments['module'] ?? null;
        if ($module !== null && !is_string($module)) {
            return ToolResult::error('VALIDATION_ERROR', 'module muss ein String sein oder weggelassen werden.');
        }
        if ($module === '') {
            $module = null;
        }

        $system = $arguments['system'] ?? null;
        if ($system !== null && !is_string($system)) {
            return ToolResult::error('VALIDATION_ERROR', 'system muss ein String sein oder weggelassen werden.');
        }
        if ($system === '') {
            $system = null;
        }

        $maxTokens = $arguments['max_tokens'] ?? self::MAX_TOKENS_DEFAULT;
        if (!is_int($maxTokens) && !(is_string($maxTokens) && ctype_digit($maxTokens))) {
            return ToolResult::error('VALIDATION_ERROR', 'max_tokens muss eine Ganzzahl sein.');
        }
        $maxTokens = (int) $maxTokens;
        if ($maxTokens < 1) {
            return ToolResult::error('VALIDATION_ERROR', 'max_tokens muss > 0 sein.');
        }
        if ($maxTokens > self::MAX_TOKENS_HARD_CAP) {
            $maxTokens = self::MAX_TOKENS_HARD_CAP;
        }

        $temperature = $arguments['temperature'] ?? self::TEMPERATURE_DEFAULT;
        if (!is_numeric($temperature)) {
            return ToolResult::error('VALIDATION_ERROR', 'temperature muss eine Zahl sein.');
        }
        $temperature = (float) $temperature;

        $model = $arguments['model'] ?? null;
        if ($model !== null && (!is_string($model) || $model === '')) {
            return ToolResult::error('VALIDATION_ERROR', 'model muss ein nicht-leerer String sein oder weggelassen werden.');
        }

        $team = $context->team ?? null;
        $teamId = $team?->id;

        // ---- Resolver-Probe (read-only) ----
        $resolved = $this->resolver->resolveFor($team, $module);
        $layerActive = !$resolved->isEmpty();
        $layerMeta = null;
        $reason = null;

        if ($layerActive) {
            $layerMeta = [
                'scope_chain' => $resolved->scope_chain,
                'version_chain' => $resolved->version_chain,
                'token_count' => $resolved->token_count,
                'rendered_block' => $resolved->rendered_block,
            ];
        } else {
            $reason = $this->diagnoseEmpty($teamId, $module);
        }

        // ---- Build messages ----
        $messages = [];
        if ($system !== null) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        // ---- Options ----
        $options = [
            'with_context' => true,
            'tools' => false,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];
        if ($module !== null) {
            $options['source_module'] = $module;
        }

        // ---- LLM-Call ----
        try {
            if ($model !== null) {
                $result = $this->openAi->chat($messages, $model, $options);
            } else {
                $result = $this->openAi->chat($messages, options: $options);
            }
        } catch (\Throwable $e) {
            return ToolResult::error(
                'LLM_ERROR',
                'LLM-Call fehlgeschlagen: ' . $e->getMessage()
            );
        }

        $responseData = [
            'content' => $result['content'] ?? '',
            'model' => $result['model'] ?? ($model ?? null),
            'layer_active' => $layerActive,
            'layer_meta' => $layerMeta,
            'module' => $module,
            'team_id' => $teamId,
            'usage' => $result['usage'] ?? [],
        ];
        if ($reason !== null) {
            $responseData['reason'] = $reason;
        }

        return ToolResult::success($responseData);
    }

    /**
     * Best-Effort-Diagnose, warum der Resolver leer geliefert hat.
     */
    private function diagnoseEmpty(?int $teamId, ?string $module): string
    {
        $globalLayers = SemanticLayer::globalLayers();
        $teamLayers = $teamId ? SemanticLayer::forTeamLayers($teamId) : collect();

        $candidates = $globalLayers->merge($teamLayers);

        if ($candidates->isEmpty()) {
            return 'no_layer_in_scope';
        }

        $hasActiveStatus = false;
        $hasCurrentVersion = false;
        $contextApplies = false;
        $productionSomewhere = false;

        foreach ($candidates as $layer) {
            if ($layer->isActive()) {
                $hasActiveStatus = true;
            }
            if ($layer->current_version_id !== null) {
                $hasCurrentVersion = true;
            }
            if ($layer->status === SemanticLayer::STATUS_PRODUCTION) {
                $productionSomewhere = true;
            }
            if ($layer->appliesToContext($module)) {
                $contextApplies = true;
            }
        }

        if (!$hasCurrentVersion) {
            return 'no_active_version';
        }
        if (!$hasActiveStatus) {
            return 'status_not_active';
        }
        if (!$contextApplies && !$productionSomewhere) {
            return 'module_not_enabled';
        }

        return 'unknown';
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'semantic_layer', 'dryrun', 'llm'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => false,
            'confirmation_required' => false,
            'side_effects' => ['external_llm_call'],
            'related_tools' => [
                'core.semantic_layer.resolved.GET',
                'core.semantic_layer.status.PATCH',
                'core.semantic_layer.module.PATCH',
            ],
        ];
    }
}
