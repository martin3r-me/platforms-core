<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreLookup;
use Platform\Core\Models\CoreLookupValue;

class ManageLookupValuesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.lookup_values.MANAGE';
    }

    public function getDescription(): string
    {
        return 'POST /core/extra-fields/lookup-values/manage - Verwaltet Lookup-Werte: add, update, delete, toggle_active, reorder.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lookup_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Lookups.',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['add', 'update', 'delete', 'toggle_active', 'reorder'],
                    'description' => 'Auszuführende Aktion.',
                ],
                // Für add
                'label' => [
                    'type' => 'string',
                    'description' => 'Anzeige-Label (für add).',
                ],
                'value' => [
                    'type' => 'string',
                    'description' => 'Technischer Wert (optional für add, wird aus Label generiert).',
                ],
                'meta' => [
                    'type' => 'object',
                    'description' => 'Zusätzliche Metadaten (für add/update).',
                ],
                // Für update/delete/toggle_active
                'value_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Lookup-Wertes (für update/delete/toggle_active).',
                ],
                // Für update
                'new_label' => [
                    'type' => 'string',
                    'description' => 'Neues Label (für update).',
                ],
                'new_value' => [
                    'type' => 'string',
                    'description' => 'Neuer technischer Wert (für update).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Aktiv-Status setzen (für update).',
                ],
                // Für reorder
                'value_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von Value-IDs in gewünschter Reihenfolge (für reorder).',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['lookup_id', 'action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $lookupId = (int)($arguments['lookup_id'] ?? 0);
            $action = (string)($arguments['action'] ?? '');
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            if ($lookupId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'lookup_id ist erforderlich.');
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            $lookup = CoreLookup::forTeam($teamId)->where('id', $lookupId)->first();

            if (!$lookup) {
                return ToolResult::error('NOT_FOUND', "Lookup mit ID {$lookupId} nicht gefunden.");
            }

            return match ($action) {
                'add' => $this->addValue($lookup, $arguments),
                'update' => $this->updateValue($lookup, $arguments),
                'delete' => $this->deleteValue($lookup, $arguments),
                'toggle_active' => $this->toggleActive($lookup, $arguments),
                'reorder' => $this->reorder($lookup, $arguments),
                default => ToolResult::error('VALIDATION_ERROR', "Unbekannte Aktion: {$action}"),
            };
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Lookup-Werte-Verwaltung: ' . $e->getMessage());
        }
    }

    protected function addValue(CoreLookup $lookup, array $arguments): ToolResult
    {
        $label = trim((string)($arguments['label'] ?? ''));
        if ($label === '') {
            return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich für add.');
        }

        $value = trim((string)($arguments['value'] ?? '')) ?: $label;
        $meta = $arguments['meta'] ?? null;

        // Prüfen ob Value bereits existiert
        $exists = CoreLookupValue::where('lookup_id', $lookup->id)
            ->where('value', $value)
            ->exists();

        if ($exists) {
            return ToolResult::error('VALIDATION_ERROR', "Wert '{$value}' existiert bereits in diesem Lookup.");
        }

        $maxOrder = CoreLookupValue::where('lookup_id', $lookup->id)->max('order') ?? 0;

        $lookupValue = CoreLookupValue::create([
            'lookup_id' => $lookup->id,
            'value' => $value,
            'label' => $label,
            'order' => $maxOrder + 1,
            'is_active' => true,
            'meta' => is_array($meta) ? $meta : null,
        ]);

        return ToolResult::success([
            'action' => 'add',
            'value' => [
                'id' => $lookupValue->id,
                'value' => $lookupValue->value,
                'label' => $lookupValue->label,
                'order' => $lookupValue->order,
                'is_active' => $lookupValue->is_active,
                'meta' => $lookupValue->meta,
            ],
            'message' => "Wert '{$label}' hinzugefügt.",
        ]);
    }

    protected function updateValue(CoreLookup $lookup, array $arguments): ToolResult
    {
        $valueId = (int)($arguments['value_id'] ?? 0);
        if ($valueId <= 0) {
            return ToolResult::error('VALIDATION_ERROR', 'value_id ist erforderlich für update.');
        }

        $lookupValue = CoreLookupValue::where('lookup_id', $lookup->id)
            ->where('id', $valueId)
            ->first();

        if (!$lookupValue) {
            return ToolResult::error('NOT_FOUND', "Lookup-Wert mit ID {$valueId} nicht gefunden.");
        }

        $updated = [];

        if (isset($arguments['new_label'])) {
            $newLabel = trim((string)$arguments['new_label']);
            if ($newLabel !== '') {
                $lookupValue->label = $newLabel;
                $updated[] = 'label';
            }
        }

        if (isset($arguments['new_value'])) {
            $newValue = trim((string)$arguments['new_value']);
            if ($newValue !== '' && $newValue !== $lookupValue->value) {
                // Prüfen ob neuer Wert bereits existiert
                $exists = CoreLookupValue::where('lookup_id', $lookup->id)
                    ->where('value', $newValue)
                    ->where('id', '!=', $valueId)
                    ->exists();

                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Wert '{$newValue}' existiert bereits.");
                }

                $lookupValue->value = $newValue;
                $updated[] = 'value';
            }
        }

        if (isset($arguments['is_active'])) {
            $lookupValue->is_active = (bool)$arguments['is_active'];
            $updated[] = 'is_active';
        }

        if (isset($arguments['meta'])) {
            $lookupValue->meta = is_array($arguments['meta']) ? $arguments['meta'] : null;
            $updated[] = 'meta';
        }

        if (count($updated) > 0) {
            $lookupValue->save();
        }

        return ToolResult::success([
            'action' => 'update',
            'value' => [
                'id' => $lookupValue->id,
                'value' => $lookupValue->value,
                'label' => $lookupValue->label,
                'order' => $lookupValue->order,
                'is_active' => $lookupValue->is_active,
                'meta' => $lookupValue->meta,
            ],
            'updated_fields' => $updated,
            'message' => count($updated) > 0
                ? "Wert aktualisiert: " . implode(', ', $updated)
                : "Keine Änderungen vorgenommen.",
        ]);
    }

    protected function deleteValue(CoreLookup $lookup, array $arguments): ToolResult
    {
        $valueId = (int)($arguments['value_id'] ?? 0);
        if ($valueId <= 0) {
            return ToolResult::error('VALIDATION_ERROR', 'value_id ist erforderlich für delete.');
        }

        $lookupValue = CoreLookupValue::where('lookup_id', $lookup->id)
            ->where('id', $valueId)
            ->first();

        if (!$lookupValue) {
            return ToolResult::error('NOT_FOUND', "Lookup-Wert mit ID {$valueId} nicht gefunden.");
        }

        $deletedLabel = $lookupValue->label;
        $deletedValue = $lookupValue->value;
        $lookupValue->delete();

        return ToolResult::success([
            'action' => 'delete',
            'deleted_id' => $valueId,
            'deleted_value' => $deletedValue,
            'deleted_label' => $deletedLabel,
            'message' => "Wert '{$deletedLabel}' gelöscht.",
        ]);
    }

    protected function toggleActive(CoreLookup $lookup, array $arguments): ToolResult
    {
        $valueId = (int)($arguments['value_id'] ?? 0);
        if ($valueId <= 0) {
            return ToolResult::error('VALIDATION_ERROR', 'value_id ist erforderlich für toggle_active.');
        }

        $lookupValue = CoreLookupValue::where('lookup_id', $lookup->id)
            ->where('id', $valueId)
            ->first();

        if (!$lookupValue) {
            return ToolResult::error('NOT_FOUND', "Lookup-Wert mit ID {$valueId} nicht gefunden.");
        }

        $lookupValue->is_active = !$lookupValue->is_active;
        $lookupValue->save();

        return ToolResult::success([
            'action' => 'toggle_active',
            'value' => [
                'id' => $lookupValue->id,
                'value' => $lookupValue->value,
                'label' => $lookupValue->label,
                'is_active' => $lookupValue->is_active,
            ],
            'message' => "Wert '{$lookupValue->label}' " . ($lookupValue->is_active ? 'aktiviert' : 'deaktiviert') . ".",
        ]);
    }

    protected function reorder(CoreLookup $lookup, array $arguments): ToolResult
    {
        $valueIds = $arguments['value_ids'] ?? [];
        if (!is_array($valueIds) || empty($valueIds)) {
            return ToolResult::error('VALIDATION_ERROR', 'value_ids ist erforderlich für reorder.');
        }

        $order = 1;
        $reordered = [];

        foreach ($valueIds as $valueId) {
            $valueId = (int)$valueId;
            $updated = CoreLookupValue::where('lookup_id', $lookup->id)
                ->where('id', $valueId)
                ->update(['order' => $order]);

            if ($updated) {
                $reordered[] = $valueId;
                $order++;
            }
        }

        return ToolResult::success([
            'action' => 'reorder',
            'reordered_ids' => $reordered,
            'message' => count($reordered) . " Werte neu sortiert.",
        ]);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'lookups', 'values', 'manage'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
