<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\Models\SemanticLayer;

/**
 * core.semantic_layer.layer.GET
 *
 * Liefert einen einzelnen Semantic-Layer (global oder team-scoped) mit
 * vollem Content der current_version (Perspektive, Ton, Heuristiken,
 * Negativ-Raum, Notes, Token-Count, SemVer).
 *
 * Owner-only.
 */
class GetLayerTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    public function getName(): string
    {
        return 'core.semantic_layer.layer.GET';
    }

    public function getDescription(): string
    {
        return 'Liefert einen einzelnen Semantic-Layer (global oder team-scoped) mit vollem Content der aktiven Version. '
            . 'Enthält die vier Kanäle (perspektive, ton, heuristiken, negativ_raum), Notes, Token-Count, SemVer und Status. '
            . 'Read-only, Owner-only. Nutze "core.semantic_layer.layers.GET" um zu sehen, welche Layer existieren.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scope' => [
                    'type' => 'string',
                    'enum' => [SemanticLayer::SCOPE_GLOBAL, SemanticLayer::SCOPE_TEAM],
                    'description' => '"global" für den BHG-Core-Layer, "team" für den Venture-Extension-Layer. Default: "global".',
                ],
                'team_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Team-ID — nur bei scope=team relevant. Wenn nicht angegeben, wird der aktive Team-Kontext verwendet.',
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

            $scopeResult = $this->resolveScope($arguments, $context);
            if ($scopeResult instanceof ToolResult) {
                return $scopeResult;
            }
            [$scope, $teamId] = $scopeResult;

            $layer = SemanticLayer::where('scope_type', $scope)
                ->where('scope_id', $teamId)
                ->with('currentVersion')
                ->first();

            if (!$layer) {
                return ToolResult::error(
                    'LAYER_NOT_FOUND',
                    'Es existiert noch kein Semantic-Layer für scope=' . $scope
                    . ($scope === SemanticLayer::SCOPE_TEAM ? ', team_id=' . $teamId : '')
                    . '. Lege einen mit "core.semantic_layer.versions.POST" an.'
                );
            }

            $v = $layer->currentVersion;

            return ToolResult::success([
                'layer' => [
                    'id' => $layer->id,
                    'scope_type' => $layer->scope_type,
                    'scope_id' => $layer->scope_id,
                    'status' => $layer->status,
                    'enabled_modules' => $layer->enabled_modules ?? [],
                    'current_version' => $v ? [
                        'id' => $v->id,
                        'semver' => $v->semver,
                        'version_type' => $v->version_type,
                        'perspektive' => $v->perspektive,
                        'ton' => $v->ton ?? [],
                        'heuristiken' => $v->heuristiken ?? [],
                        'negativ_raum' => $v->negativ_raum ?? [],
                        'token_count' => $v->token_count,
                        'notes' => $v->notes,
                        'created_at' => $v->created_at?->toIso8601String(),
                    ] : null,
                    'version_count' => $layer->versions()->count(),
                    'updated_at' => $layer->updated_at?->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Lesen des Semantic-Layers: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['core', 'semantic_layer', 'read'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'confirmation_required' => false,
            'side_effects' => [],
            'related_tools' => [
                'core.semantic_layer.layers.GET',
                'core.semantic_layer.versions.POST',
                'core.semantic_layer.resolved.GET',
            ],
        ];
    }
}
