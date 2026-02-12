<?php

namespace Platform\Core\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Traits\HasExtraFields;

class ListExtraFieldEntitiesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.LIST_ENTITIES';
    }

    public function getDescription(): string
    {
        return 'GET /core/extra-fields/entities - Listet alle Model-Typen auf, die Extra-Fields unterstützen. Gibt die morph_key Namen zurück, die für core.extra_fields.GET/PUT verwendet werden können.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $morphMap = Relation::morphMap();
            $supportedEntities = [];

            foreach ($morphMap as $alias => $className) {
                if (!class_exists($className)) {
                    continue;
                }

                // Prüfe ob Klasse den HasExtraFields Trait nutzt
                if (in_array(HasExtraFields::class, class_uses_recursive($className))) {
                    $supportedEntities[] = [
                        'morph_key' => $alias,
                        'class' => $className,
                        'module' => $this->extractModule($alias),
                    ];
                }
            }

            // Nach Modul gruppieren
            $byModule = [];
            foreach ($supportedEntities as $entity) {
                $module = $entity['module'];
                if (!isset($byModule[$module])) {
                    $byModule[$module] = [];
                }
                $byModule[$module][] = $entity['morph_key'];
            }

            return ToolResult::success([
                'entities' => $supportedEntities,
                'by_module' => $byModule,
                'total' => count($supportedEntities),
                'usage_hint' => 'Nutze den morph_key als model_type Parameter für core.extra_fields.GET und core.extra_fields.PUT',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Entities: ' . $e->getMessage());
        }
    }

    protected function extractModule(string $morphKey): string
    {
        $parts = explode('_', $morphKey);
        return $parts[0] ?? 'unknown';
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['core', 'extra_fields', 'entities', 'discovery'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
