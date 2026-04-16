<?php

namespace Platform\Core\Tools\SemanticLayer;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Module;
use Platform\Core\Registry\ModuleRegistry;
use Platform\Core\SemanticLayer\Models\SemanticLayer;
use Platform\Core\SemanticLayer\Models\SemanticLayerAudit;
use Platform\Core\SemanticLayer\Services\SemanticLayerResolver;

/**
 * core.semantic_layer.module.PATCH
 *
 * Toggelt einen Modul-Key in der `enabled_modules`-Liste eines
 * Semantic-Layers (enable oder disable). Validiert den Modul-Key gegen
 * die existierenden Module (DB + registrierter In-Memory-Registry),
 * damit keine toten Keys in der Liste landen.
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
        return 'Aktiviert oder deaktiviert einen Modul-Eintrag in der enabled_modules-Liste eines Semantic-Layers. '
            . 'Steuert das Cold-Start-Gate: Solange der Layer auf status=pilot steht, wirkt er nur in den hier eingetragenen Modulen. '
            . 'Bei status=production wirkt der Layer ohnehin überall (das Gate wird ignoriert). '
            . 'Modul-Key wird gegen die existierenden Module geprüft (verhindert Typos). '
            . 'Owner-only.';
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
                'module' => [
                    'type' => 'string',
                    'description' => 'Modul-Key (z.B. "okr", "canvas", "mcp"). Muss ein registriertes Modul sein.',
                ],
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'true = Modul in enabled_modules aufnehmen, false = entfernen.',
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

            $scopeResult = $this->resolveScope($arguments, $context);
            if ($scopeResult instanceof ToolResult) {
                return $scopeResult;
            }
            [$scope, $teamId] = $scopeResult;

            $module = $arguments['module'] ?? null;
            if (!is_string($module) || $module === '') {
                return ToolResult::error('VALIDATION_ERROR', 'module ist erforderlich (Modul-Key als String).');
            }

            if (!array_key_exists('enabled', $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', 'enabled ist erforderlich (true oder false).');
            }
            $enabled = (bool) $arguments['enabled'];

            // Modul-Key-Validierung: erst DB-Module, dann in-memory ModuleRegistry.
            // (DB-Module ist die persistente Wahrheit auf der Plattform; ModuleRegistry
            // wird zur Laufzeit registriert und kann je nach Boot-Pfad leer sein.)
            $isKnown = Module::where('key', $module)->exists()
                || array_key_exists($module, ModuleRegistry::all());

            if (!$isKnown) {
                return ToolResult::error(
                    'UNKNOWN_MODULE',
                    'Unbekannter Modul-Key "' . $module . '". '
                    . 'Nutze "core.modules.GET" um verfügbare Module zu sehen.'
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
                    context: ['module' => $module, 'source' => 'mcp'],
                );

                $this->resolver->forgetCache();
            }

            return ToolResult::success([
                'layer_id' => $layer->id,
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
