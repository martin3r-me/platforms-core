<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * core.semantic_layer.status.PATCH
 *
 * Setzt den Status eines Semantic-Layers auf draft / pilot / production / archived.
 * Schreibt einen Audit-Eintrag mit `from` und `to`. Invalidiert den Resolver-Cache.
 *
 * Hinweis bei status=production: Der Layer wirkt dann auf ALLEN Modulen,
 * unabhängig von enabled_modules. Wir geben einen `warning_production_broadens_scope`
 * im Output zurück (informativer Hint, kein Block).
 *
 * Owner-only.
 */
class SetStatusTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    public function __construct(
        private readonly SemanticLayerResolver $resolver,
    ) {
    }

    public function getName(): string
    {
        return 'core.semantic_layer.status.PATCH';
    }

    public function getDescription(): string
    {
        return 'Setzt den Status eines Semantic-Layers (draft, pilot, production, archived). '
            . 'Schreibt einen Audit-Eintrag und invalidiert den Resolver-Cache. '
            . 'Bei status=production wirkt der Layer auf ALLEN Modulen, unabhängig von enabled_modules — '
            . 'das Tool liefert dann einen informativen Hint im Output zurück. '
            . 'Owner-only. Zum Archivieren eines Layers status=archived setzen (es gibt kein Delete-Tool).';
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
                'status' => [
                    'type' => 'string',
                    'enum' => [
                        SemanticLayer::STATUS_DRAFT,
                        SemanticLayer::STATUS_PILOT,
                        SemanticLayer::STATUS_PRODUCTION,
                        SemanticLayer::STATUS_ARCHIVED,
                    ],
                    'description' => 'Neuer Status für den Layer.',
                ],
            ],
            'required' => ['status'],
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

            $status = $arguments['status'] ?? null;
            $allowed = [
                SemanticLayer::STATUS_DRAFT,
                SemanticLayer::STATUS_PILOT,
                SemanticLayer::STATUS_PRODUCTION,
                SemanticLayer::STATUS_ARCHIVED,
            ];
            if (!in_array($status, $allowed, true)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'Ungültiger status: "' . (string) $status . '". Erlaubt: ' . implode(', ', $allowed) . '.'
                );
            }

            $layer = SemanticLayer::where('scope_type', $scope)
                ->where('scope_id', $teamId)
                ->first();

            if (!$layer) {
                return ToolResult::error(
                    'LAYER_NOT_FOUND',
                    'Es existiert noch kein Semantic-Layer für scope=' . $scope
                    . ($scope === SemanticLayer::SCOPE_TEAM ? ', team_id=' . $teamId : '')
                    . '. Lege einen mit "core.semantic_layer.versions.POST" an.'
                );
            }

            $previous = $layer->status;
            if ($previous === $status) {
                return ToolResult::success([
                    'layer_id' => $layer->id,
                    'status' => $status,
                    'previous_status' => $previous,
                    'changed' => false,
                    'message' => 'Status bereits "' . $status . '" — keine Änderung.',
                ]);
            }

            $layer->status = $status;
            $layer->save();

            SemanticLayerAudit::record(
                layerId: $layer->id,
                action: 'status_changed',
                versionId: $layer->current_version_id,
                diff: [[
                    'field' => 'status',
                    'op' => 'changed',
                    'from' => $previous,
                    'to' => $status,
                ]],
                userId: $context->user->id ?? null,
                context: ['source' => 'mcp'],
            );

            $this->resolver->forgetCache();

            $output = [
                'layer_id' => $layer->id,
                'status' => $status,
                'previous_status' => $previous,
                'changed' => true,
            ];
            if ($status === SemanticLayer::STATUS_PRODUCTION) {
                $output['warning_production_broadens_scope'] =
                    'Der Layer wirkt ab jetzt auf ALLEN Modulen, unabhängig von enabled_modules.';
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Setzen des Status: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'semantic_layer', 'status'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
            'confirmation_required' => false,
            'side_effects' => ['updates'],
            'related_tools' => [
                'core.semantic_layer.layer.GET',
                'core.semantic_layer.module.PATCH',
            ],
        ];
    }
}
