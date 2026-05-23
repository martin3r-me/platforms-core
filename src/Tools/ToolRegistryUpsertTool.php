<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Services\ToolRegistryService;

class ToolRegistryUpsertTool implements ToolContract
{
    public function getName(): string
    {
        return 'tool_registry.PUT';
    }

    public function getDescription(): string
    {
        return 'Erstellt oder aktualisiert einen Eintrag in der Tool-Registry. Nur für Admins. Der Tool-Name muss im ToolRegistry registriert sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Tool-Name (muss im ToolRegistry existieren, z.B. "obsidian.files.PUT").',
                ],
                'kind' => [
                    'type' => 'string',
                    'description' => 'HTTP-Methode/Typ: GET, POST, PUT, DELETE, PATCH, CRUD, SEARCH, FETCH, BULK_POST.',
                ],
                'module' => [
                    'type' => 'string',
                    'description' => 'Technisches Modul (z.B. "core", "planner").',
                ],
                'namespace' => [
                    'type' => 'string',
                    'description' => 'Semantischer Namespace (z.B. "obsidian", "planner").',
                ],
                'tier' => [
                    'type' => 'string',
                    'description' => 'Tier: always_on, common, specialized, hidden.',
                ],
                'intent' => [
                    'type' => 'string',
                    'description' => 'Kurze Beschreibung (1 Satz, max ~200 Zeichen, agentenfreundlich).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Ausführliche Beschreibung.',
                ],
                'required_params' => [
                    'type' => 'array',
                    'description' => 'Pflichtparameter [{name, type, description}].',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
                'optional_params' => [
                    'type' => 'array',
                    'description' => 'Optionale Parameter [{name, type, description}].',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                ],
                'cost_class' => [
                    'type' => 'string',
                    'description' => 'Kostenklasse: local_db, local_compute, external_api_free, external_api_paid.',
                ],
                'cost_per_call_eur' => [
                    'type' => 'number',
                    'description' => 'Kosten pro Aufruf in EUR (optional).',
                ],
                'read_only' => [
                    'type' => 'boolean',
                    'description' => 'Ist das Tool nur lesend?',
                ],
                'deprecated' => [
                    'type' => 'boolean',
                    'description' => 'Ist das Tool deprecated?',
                ],
                'successor_name' => [
                    'type' => 'string',
                    'description' => 'Name des Nachfolge-Tools.',
                ],
                'tags' => [
                    'type' => 'array',
                    'description' => 'Tags für die Suche.',
                    'items' => ['type' => 'string'],
                ],
                'requires' => [
                    'type' => 'array',
                    'description' => 'Abhängige Tools [{tool, for_param}].',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'tool' => ['type' => 'string'],
                            'for_param' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User muss authentifiziert sein.');
            }

            // Admin-Check
            $role = $context->user->pivot->role
                ?? ($context->team ? $context->user->teams()->where('teams.id', $context->team->id)->first()?->pivot?->role : null)
                ?? null;

            if ($role !== 'admin') {
                return ToolResult::error('FORBIDDEN', 'Nur Admins dürfen die Tool-Registry bearbeiten.');
            }

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            // Pflichtfelder für neue Einträge prüfen
            $service = app(ToolRegistryService::class);
            $existing = $service->get($name);

            if (!$existing) {
                // Neuer Eintrag: tier, intent und cost_class sind Pflicht
                if (empty($arguments['tier'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'tier ist erforderlich für neue Einträge.');
                }
                if (empty($arguments['intent'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'intent ist erforderlich für neue Einträge.');
                }
                if (empty($arguments['cost_class'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'cost_class ist erforderlich für neue Einträge.');
                }
            }

            $entry = $service->upsert($arguments);

            return ToolResult::success([
                'name' => $entry->name,
                'message' => $existing ? 'Tool-Registry-Eintrag aktualisiert.' : 'Tool-Registry-Eintrag erstellt.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Speichern: ' . $e->getMessage());
        }
    }
}
