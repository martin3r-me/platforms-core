<?php

namespace Platform\Core\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Models\CoreLookupValue;

class CreateLookupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.lookups.POST';
    }

    public function getDescription(): string
    {
        return 'POST /core/lookups - Erstellt einen neuen Lookup (Auswahlliste). Optional können direkt initiale Werte mitgegeben werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'label' => [
                    'type' => 'string',
                    'description' => 'Anzeige-Label des Lookups (z.B. "Nationalität").',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Technischer Name/Slug. Wird aus Label generiert wenn nicht angegeben.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Lookups.',
                ],
                'values' => [
                    'type' => 'array',
                    'description' => 'Optional: Initiale Werte für den Lookup.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => [
                                'type' => 'string',
                                'description' => 'Anzeige-Label des Wertes.',
                            ],
                            'value' => [
                                'type' => 'string',
                                'description' => 'Optional: Technischer Wert. Wird aus Label generiert wenn nicht angegeben.',
                            ],
                            'meta' => [
                                'type' => 'object',
                                'description' => 'Optional: Zusätzliche Metadaten (z.B. Ländercodes).',
                            ],
                        ],
                        'required' => ['label'],
                    ],
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['label'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $label = trim((string)($arguments['label'] ?? ''));
            $name = trim((string)($arguments['name'] ?? ''));
            $description = trim((string)($arguments['description'] ?? ''));
            $values = $arguments['values'] ?? [];
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            if ($label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            // Name aus Label generieren wenn nicht angegeben
            if ($name === '') {
                $name = Str::slug($label, '_');
            }

            // Prüfen ob Name bereits existiert
            $exists = CoreLookup::forTeam($teamId)->where('name', $name)->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Ein Lookup mit dem Namen '{$name}' existiert bereits.");
            }

            // Lookup erstellen
            $lookup = CoreLookup::create([
                'team_id' => $teamId,
                'created_by_user_id' => $context->user?->id,
                'name' => $name,
                'label' => $label,
                'description' => $description ?: null,
                'is_system' => false,
            ]);

            // Werte erstellen
            $createdValues = [];
            $order = 1;
            foreach ($values as $valueData) {
                $valueLabel = trim((string)($valueData['label'] ?? ''));
                if ($valueLabel === '') {
                    continue;
                }

                $value = trim((string)($valueData['value'] ?? '')) ?: $valueLabel;
                $meta = $valueData['meta'] ?? null;

                $lookupValue = CoreLookupValue::create([
                    'lookup_id' => $lookup->id,
                    'value' => $value,
                    'label' => $valueLabel,
                    'order' => $order++,
                    'is_active' => true,
                    'meta' => is_array($meta) ? $meta : null,
                ]);

                $createdValues[] = [
                    'id' => $lookupValue->id,
                    'value' => $lookupValue->value,
                    'label' => $lookupValue->label,
                ];
            }

            return ToolResult::success([
                'id' => $lookup->id,
                'name' => $lookup->name,
                'label' => $lookup->label,
                'description' => $lookup->description,
                'team_id' => $lookup->team_id,
                'values_created' => count($createdValues),
                'values' => $createdValues,
                'message' => "Lookup '{$lookup->label}' mit " . count($createdValues) . " Wert(en) erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Lookups: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'lookups', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
