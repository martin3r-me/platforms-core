<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreLookup;

class UpdateLookupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.lookups.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /core/lookups/{id} - Aktualisiert einen Lookup (Label, Beschreibung). Für Werte-Verwaltung nutze core.lookup_values.*.';
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
                'label' => [
                    'type' => 'string',
                    'description' => 'Neues Anzeige-Label.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Neue Beschreibung. Leer-String zum Entfernen.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['lookup_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $lookupId = (int)($arguments['lookup_id'] ?? 0);
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

            $updated = [];

            if (isset($arguments['label'])) {
                $label = trim((string)$arguments['label']);
                if ($label !== '') {
                    $lookup->label = $label;
                    $updated[] = 'label';
                }
            }

            if (array_key_exists('description', $arguments)) {
                $description = trim((string)$arguments['description']);
                $lookup->description = $description !== '' ? $description : null;
                $updated[] = 'description';
            }

            if (count($updated) > 0) {
                $lookup->save();
            }

            return ToolResult::success([
                'id' => $lookup->id,
                'name' => $lookup->name,
                'label' => $lookup->label,
                'description' => $lookup->description,
                'updated_fields' => $updated,
                'message' => count($updated) > 0
                    ? "Lookup aktualisiert: " . implode(', ', $updated)
                    : "Keine Änderungen vorgenommen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Lookups: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'lookups', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
