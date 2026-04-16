<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * core.semantic_layer.resolved.GET
 *
 * Live-Preview: liefert den vom Resolver für eine konkrete
 * {team, module}-Kombi aufgelösten Layer (gemerged Global + Team-Extension,
 * mit gerendertem Prompt-Block). Dient als Test-Spiegel zu
 * `core.context.GET` — zeigt genau, was das LLM bei diesem Modul-Aufruf
 * tatsächlich sehen würde, ohne einen echten LLM-Call auszulösen.
 *
 * Read-only, Owner-only.
 */
class GetResolvedTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    public function __construct(
        private readonly SemanticLayerResolver $resolver,
    ) {
    }

    public function getName(): string
    {
        return 'core.semantic_layer.resolved.GET';
    }

    public function getDescription(): string
    {
        return 'Live-Preview des resolvten Semantic-Layers für eine konkrete {team, module}-Kombination. '
            . 'Zeigt den fertig gemergten Layer (Global + ggf. Team-Extension) inklusive Render-Block, '
            . 'Scope-Chain, Version-Chain und Token-Count — exakt wie das LLM ihn bei einem Tool-Call '
            . 'in diesem Modul sehen würde. Read-only, Owner-only. '
            . 'Nutze "core.modules.GET" um verfügbare Modul-Keys zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Team-ID, dessen Extension-Layer dazugemerged werden soll. '
                        . 'Wenn null/leer: nur Global-Layer (entspricht Queue-Job- / API-Kontext ohne Team).',
                ],
                'module' => [
                    'type' => ['string', 'null'],
                    'description' => 'Modul-Key (z.B. "okr"). Wenn null: Discovery-Modus (Modul-Gate wird ignoriert, '
                        . 'der Layer wird trotzdem geliefert, falls einer existiert).',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if ($denied = $this->assertOwner($context)) {
                return $denied;
            }

            $team = null;
            $teamId = $arguments['team_id'] ?? null;
            if ($teamId !== null && $teamId !== '' && $teamId !== 0) {
                $teamId = (int) $teamId;
                if ($teamId <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', 'team_id muss eine positive Zahl sein.');
                }
                $team = Team::find($teamId);
                if (!$team) {
                    return ToolResult::error('TEAM_NOT_FOUND', 'Team mit ID ' . $teamId . ' wurde nicht gefunden.');
                }
            }

            $module = $arguments['module'] ?? null;
            if ($module !== null && !is_string($module)) {
                return ToolResult::error('VALIDATION_ERROR', 'module muss ein String sein oder weggelassen werden.');
            }
            if ($module === '') {
                $module = null;
            }

            $resolved = $this->resolver->resolveFor($team, $module);

            if ($resolved->isEmpty()) {
                $reason = $this->diagnoseEmpty($team?->id, $module);
                return ToolResult::success([
                    'active' => false,
                    'reason' => $reason,
                    'team_id' => $team?->id,
                    'module' => $module,
                ]);
            }

            return ToolResult::success(array_merge(
                ['active' => true],
                $resolved->toArray(),
                [
                    'team_id' => $team?->id,
                    'module' => $module,
                ],
            ));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Auflösen des Semantic-Layers: ' . $e->getMessage());
        }
    }

    /**
     * Best-Effort-Diagnose, warum der Resolver leer geliefert hat.
     * Hilft dem Aufrufer, das Setup zu debuggen.
     */
    private function diagnoseEmpty(?int $teamId, ?string $module): string
    {
        $global = SemanticLayer::global();
        $teamLayer = $teamId ? SemanticLayer::forTeam($teamId) : null;

        if (!$global && !$teamLayer) {
            return 'no_layer_in_scope';
        }

        $candidates = array_filter([$global, $teamLayer]);
        $hasActiveStatus = false;
        $hasCurrentVersion = false;
        $moduleEnabledSomewhere = false;
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
            if ($module !== null && $layer->hasModuleEnabled($module)) {
                $moduleEnabledSomewhere = true;
            }
        }

        if (!$hasCurrentVersion) {
            return 'no_active_version';
        }
        if (!$hasActiveStatus) {
            return 'status_not_active';
        }
        if ($module !== null && !$moduleEnabledSomewhere && !$productionSomewhere) {
            return 'module_not_enabled';
        }

        return 'unknown';
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['core', 'semantic_layer', 'preview', 'resolve'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'confirmation_required' => false,
            'side_effects' => [],
            'related_tools' => [
                'core.semantic_layer.layer.GET',
                'core.semantic_layer.module.PATCH',
                'core.context.GET',
            ],
        ];
    }
}
