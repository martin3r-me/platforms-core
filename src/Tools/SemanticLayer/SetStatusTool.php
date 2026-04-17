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
 * Setzt den Status eines Semantic-Layers. Akzeptiert `layer_id` (direkt)
 * ODER `scope + label`.
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
            . 'Akzeptiert layer_id (direkt) ODER scope + label. '
            . 'Schreibt einen Audit-Eintrag und invalidiert den Resolver-Cache. '
            . 'Bei status=production wirkt der Layer auf ALLEN Modulen, unabhängig von enabled_modules. '
            . 'Owner-only.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'layer_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Direkte Layer-ID. Alternativ scope + label verwenden.',
                ],
                'scope' => [
                    'type' => 'string',
                    'enum' => [SemanticLayer::SCOPE_GLOBAL, SemanticLayer::SCOPE_TEAM],
                    'description' => '"global" oder "team". Default: "global".',
                ],
                'team_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Team-ID — nur bei scope=team relevant.',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Layer-Label (z.B. "leitbild", "mcp"). Default: "leitbild".',
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

            $layerResult = $this->resolveLayer($arguments, $context);
            if ($layerResult instanceof ToolResult) {
                return $layerResult;
            }
            $layer = $layerResult;

            $previous = $layer->status;
            if ($previous === $status) {
                return ToolResult::success([
                    'layer_id' => $layer->id,
                    'label' => $layer->label,
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
                context: ['label' => $layer->label, 'source' => 'mcp'],
            );

            $this->resolver->forgetCache();

            $output = [
                'layer_id' => $layer->id,
                'label' => $layer->label,
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
