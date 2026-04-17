<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\SemanticLayer\ContextKeyRegistry;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * core.semantic_layer.module.PATCH
 *
 * Toggelt einen Kontext-Key in der `enabled_modules`-Liste eines
 * Semantic-Layers (enable oder disable). Validiert den Key gegen
 * die ContextKeyRegistry (entkoppelt von ModuleRegistry).
 *
 * Owner-only.
 */
class ToggleModuleTool implements ToolContract, ToolMetadataContract
{
    use AssertsOwnerAccess;

    public function __construct(
        private readonly SemanticLayerResolver $resolver,
    ) {
    }

    public function getName(): string
    {
        return 'core.semantic_layer.module.PATCH';
    }

    public function getDescription(): string
    {
        return 'Aktiviert oder deaktiviert einen Kontext-Key in der enabled_modules-Liste eines Semantic-Layers. '
            . 'Steuert das Cold-Start-Gate: Solange der Layer auf status=pilot steht, wirkt er nur in den hier eingetragenen Kontexten. '
            . 'Bei status=production wirkt der Layer ohnehin überall (das Gate wird ignoriert). '
            . 'Key wird gegen die ContextKeyRegistry geprüft (Module + builtins wie mcp, api, webhook). '
            . 'Akzeptiert layer_id (direkt) ODER scope + label. '
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
                    'description' => '"global" oder "team". Default: "global". Nur relevant wenn keine layer_id angegeben.',
                ],
                'team_id' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Team-ID — nur bei scope=team relevant.',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Layer-Label (z.B. "leitbild", "mcp"). Default: "leitbild". Nur relevant wenn keine layer_id angegeben.',
                ],
                'module' => [
                    'type' => 'string',
                    'description' => 'Kontext-Key (z.B. "okr", "canvas", "mcp", "api"). Muss ein registrierter Key sein.',
                ],
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'true = Key in enabled_modules aufnehmen, false = entfernen.',
                ],
            ],
            'required' => ['module', 'enabled'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if ($denied = $this->assertOwner($context)) {
                return $denied;
            }

            $module = $arguments['module'] ?? null;
            if (!is_string($module) || $module === '') {
                return ToolResult::error('VALIDATION_ERROR', 'module ist erforderlich (Kontext-Key als String).');
            }

            if (!array_key_exists('enabled', $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', 'enabled ist erforderlich (true oder false).');
            }
            $enabled = (bool) $arguments['enabled'];

            // Key-Validierung gegen ContextKeyRegistry
            if (!ContextKeyRegistry::has($module)) {
                return ToolResult::error(
                    'UNKNOWN_MODULE',
                    'Unbekannter Kontext-Key "' . $module . '". '
                    . 'Registrierte Keys: ' . implode(', ', array_keys(ContextKeyRegistry::all())) . '.'
                );
            }

            $layerResult = $this->resolveLayer($arguments, $context);
            if ($layerResult instanceof ToolResult) {
                return $layerResult;
            }
            $layer = $layerResult;

            $current = $layer->enabled_modules ?? [];
            $wasEnabled = in_array($module, $current, true);

            if ($enabled) {
                if (!$wasEnabled) {
                    $current[] = $module;
                }
                $action = 'enabled_module';
            } else {
                if ($wasEnabled) {
                    $current = array_values(array_filter($current, fn ($m) => $m !== $module));
                }
                $action = 'disabled_module';
            }

            $layer->enabled_modules = array_values(array_unique($current));
            $changed = $wasEnabled !== $enabled;
            if ($changed) {
                $layer->save();

                SemanticLayerAudit::record(
                    layerId: $layer->id,
                    action: $action,
                    versionId: $layer->current_version_id,
                    diff: null,
                    userId: $context->user->id ?? null,
                    context: ['module' => $module, 'label' => $layer->label, 'source' => 'mcp'],
                );

                $this->resolver->forgetCache();
            }

            return ToolResult::success([
                'layer_id' => $layer->id,
                'label' => $layer->label,
                'module' => $module,
                'enabled' => $enabled,
                'changed' => $changed,
                'enabled_modules' => $layer->enabled_modules ?? [],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Toggle des Moduls: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'semantic_layer', 'module', 'toggle'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
            'confirmation_required' => false,
            'side_effects' => ['updates'],
            'related_tools' => [
                'core.semantic_layer.layer.GET',
                'core.semantic_layer.status.PATCH',
                'core.modules.GET',
            ],
        ];
    }
}
