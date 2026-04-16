<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\Models\SemanticLayer;

/**
 * core.semantic_layer.layers.GET
 *
 * Listet alle für den aktuellen Team-Owner sichtbaren Semantic-Layer
 * (global + Team-Scope des aktuellen Teams). Enthält Status, aktive
 * SemVer + Token-Count, Modul-Flags und Versionsanzahl pro Layer.
 *
 * Owner-only.
 */
class ListLayersTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    public function getName(): string
    {
        return 'core.semantic_layer.layers.GET';
    }

    public function getDescription(): string
    {
        return 'Listet alle Semantic-Layer auf, die für den aktuellen Team-Owner sichtbar sind '
            . '(global + Team-Scope des aktuellen Teams). Enthält Status, aktive SemVer mit Token-Count, '
            . 'die enabled_modules-Liste und die Anzahl Versionen pro Layer. Read-only, Owner-only. '
            . 'Verwende anschließend "core.semantic_layer.layer.GET" um den vollen Content der current_version eines Layers zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if ($denied = $this->assertOwner($context)) {
                return $denied;
            }

            $currentTeam = $context->team ?? $context->user->currentTeamRelation ?? null;
            $currentTeamId = $currentTeam?->id;

            $rows = SemanticLayer::query()
                ->with('currentVersion')
                ->where(function ($q) use ($currentTeamId) {
                    $q->where('scope_type', SemanticLayer::SCOPE_GLOBAL);
                    if ($currentTeamId) {
                        $q->orWhere(function ($qq) use ($currentTeamId) {
                            $qq->where('scope_type', SemanticLayer::SCOPE_TEAM)
                                ->where('scope_id', $currentTeamId);
                        });
                    }
                })
                ->orderBy('scope_type')
                ->orderBy('scope_id')
                ->get();

            $layers = $rows->map(function (SemanticLayer $layer) {
                $v = $layer->currentVersion;
                return [
                    'id' => $layer->id,
                    'scope_type' => $layer->scope_type,
                    'scope_id' => $layer->scope_id,
                    'status' => $layer->status,
                    'enabled_modules' => $layer->enabled_modules ?? [],
                    'current_version' => $v ? [
                        'id' => $v->id,
                        'semver' => $v->semver,
                        'token_count' => $v->token_count,
                    ] : null,
                    'version_count' => $layer->versions()->count(),
                    'updated_at' => $layer->updated_at?->toIso8601String(),
                ];
            })->values()->all();

            return ToolResult::success([
                'layers' => $layers,
                'count' => count($layers),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Auflisten der Semantic-Layer: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['core', 'semantic_layer', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
            'confirmation_required' => false,
            'side_effects' => [],
            'related_tools' => [
                'core.semantic_layer.layer.GET',
                'core.semantic_layer.versions.POST',
                'core.semantic_layer.resolved.GET',
            ],
        ];
    }
}
