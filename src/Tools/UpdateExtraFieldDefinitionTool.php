<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreExtraFieldDefinition;

class UpdateExtraFieldDefinitionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'core.extra_fields.definitions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /core/extra-fields/definitions/{id} - Aktualisiert eine Extra-Field-Definition (Label, Pflichtfeld, Optionen, Reihenfolge). Der technische Name bleibt stabil.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'definition_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Definition.',
                ],
                'label' => [
                    'type' => 'string',
                    'description' => 'Neues Anzeige-Label. Der technische Name (Slug) bleibt unverändert.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Beschreibungstext / Hilfetext für das Feld. Leerer String löscht die Beschreibung.',
                ],
                'is_required' => [
                    'type' => 'boolean',
                    'description' => 'Pflichtfeld?',
                ],
                'is_mandatory' => [
                    'type' => 'boolean',
                    'description' => 'Muss-Feld (nicht löschbar)?',
                ],
                'placeholder' => [
                    'type' => 'string',
                    'description' => 'Platzhaltertext für das Eingabefeld. Nur für text, number, textarea, regex. Leerer String entfernt den Placeholder.',
                ],
                'options' => [
                    'type' => 'object',
                    'description' => 'Typ-spezifische Optionen (überschreibt komplett). Select: {choices, multiple}. Lookup: {lookup_id, multiple}. File: {multiple}.',
                ],
                'order' => [
                    'type' => 'integer',
                    'description' => 'Neue Sortierreihenfolge.',
                ],
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (Default: aus Kontext).',
                ],
            ],
            'required' => ['definition_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $definitionId = (int)($arguments['definition_id'] ?? 0);
            $teamId = (int)($arguments['team_id'] ?? $context->team?->id ?? 0);

            if ($definitionId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'definition_id ist erforderlich.');
            }

            if ($teamId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine gültige Team-ID verfügbar.');
            }

            $definition = CoreExtraFieldDefinition::query()
                ->forTeam($teamId)
                ->where('id', $definitionId)
                ->first();

            if (!$definition) {
                return ToolResult::error('NOT_FOUND', "Definition mit ID {$definitionId} nicht gefunden.");
            }

            $updated = [];

            if (isset($arguments['label'])) {
                $label = trim((string)$arguments['label']);
                if ($label !== '') {
                    $definition->label = $label;
                    $updated[] = 'label';
                }
            }

            if (array_key_exists('description', $arguments)) {
                $desc = trim((string)($arguments['description'] ?? ''));
                $definition->description = $desc !== '' ? $desc : null;
                $updated[] = 'description';
            }

            if (isset($arguments['is_required'])) {
                $definition->is_required = (bool)$arguments['is_required'];
                $updated[] = 'is_required';
            }

            if (isset($arguments['is_mandatory'])) {
                $definition->is_mandatory = (bool)$arguments['is_mandatory'];
                $updated[] = 'is_mandatory';
            }

            if (isset($arguments['options'])) {
                $definition->options = $arguments['options'];
                $updated[] = 'options';
            }

            if (array_key_exists('placeholder', $arguments)) {
                $placeholder = trim((string)($arguments['placeholder'] ?? ''));
                $currentOptions = $definition->options ?? [];
                if ($placeholder !== '') {
                    $currentOptions['placeholder'] = $placeholder;
                } else {
                    unset($currentOptions['placeholder']);
                }
                $definition->options = $currentOptions ?: null;
                $updated[] = 'placeholder';
            }

            if (isset($arguments['order'])) {
                $definition->order = (int)$arguments['order'];
                $updated[] = 'order';
            }

            if (count($updated) > 0) {
                $definition->save();
            }

            return ToolResult::success([
                'id' => $definition->id,
                'name' => $definition->name,
                'label' => $definition->label,
                'type' => $definition->type,
                'context_type' => $definition->context_type,
                'context_id' => $definition->context_id,
                'is_required' => $definition->is_required,
                'is_mandatory' => $definition->is_mandatory,
                'order' => $definition->order,
                'options' => $definition->options,
                'updated_fields' => $updated,
                'message' => count($updated) > 0
                    ? "Definition aktualisiert: " . implode(', ', $updated)
                    : "Keine Änderungen vorgenommen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['core', 'extra_fields', 'definitions', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
