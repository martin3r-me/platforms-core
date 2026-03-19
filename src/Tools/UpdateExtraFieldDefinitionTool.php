<?php

namespace Platform\Core\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\CoreExtraFieldDefinition;
use Platform\Core\Services\ExtraFieldCircularDependencyDetector;
use Platform\Core\Services\ExtraFieldConditionEvaluator;

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
                'visibility_config' => [
                    'type' => 'object',
                    'description' => 'Bedingte Sichtbarkeit. EXAKTES Format (kein anderes wird akzeptiert): '
                        . '{"enabled": true, "logic": "AND", "groups": [{"logic": "AND", "conditions": [{"field": "feldname_slug", "operator": "is_false", "value": null}]}]}. '
                        . 'Beispiel: Feld nur sichtbar wenn "eu_burger" = Nein: {"enabled": true, "logic": "AND", "groups": [{"logic": "AND", "conditions": [{"field": "eu_burger", "operator": "is_false", "value": null}]}]}. '
                        . 'Operatoren: equals/not_equals (value: string), greater_than/greater_or_equal/less_than/less_or_equal (value: number), '
                        . 'is_null/is_not_null/is_true/is_false (value: null), contains/starts_with/ends_with (value: string), '
                        . 'is_in/is_not_in (value: ["a","b"], optional list_source: "manual"|"lookup", list_lookup_id: int). '
                        . 'field = technischer Name (Slug) eines ANDEREN Extra-Fields im selben Kontext. '
                        . '{"enabled": false} entfernt alle Bedingungen.',
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

            if (isset($arguments['visibility_config']) && is_array($arguments['visibility_config'])) {
                $vc = $arguments['visibility_config'];
                if ($vc['enabled'] ?? false) {
                    // Validate structure
                    $validationError = $this->validateVisibilityConfig($vc, $definition);
                    if ($validationError) {
                        return ToolResult::error('VALIDATION_ERROR', $validationError);
                    }

                    // Check for circular dependencies
                    $allDefs = CoreExtraFieldDefinition::query()
                        ->forTeam($teamId)
                        ->forContext($definition->context_type, $definition->context_id)
                        ->get()
                        ->map(fn($d) => [
                            'name' => $d->name,
                            'label' => $d->label,
                            'visibility_config' => $d->visibility_config,
                        ])
                        ->toArray();

                    $detector = new ExtraFieldCircularDependencyDetector();
                    $cycle = $detector->detectCycle($definition->name, $vc, $allDefs);
                    if ($cycle !== null) {
                        $fieldLabels = collect($allDefs)->pluck('label', 'name')->all();
                        $cycleDescription = $detector->describeCycle($cycle, $fieldLabels);
                        return ToolResult::error('VALIDATION_ERROR', "Zirkuläre Abhängigkeit erkannt: {$cycleDescription}");
                    }

                    $definition->visibility_config = $vc;
                } else {
                    $definition->visibility_config = null;
                }
                $updated[] = 'visibility_config';
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
                'visibility_config' => $definition->visibility_config,
                'updated_fields' => $updated,
                'message' => count($updated) > 0
                    ? "Definition aktualisiert: " . implode(', ', $updated)
                    : "Keine Änderungen vorgenommen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Definition: ' . $e->getMessage());
        }
    }

    /**
     * Validate a visibility config structure.
     */
    protected function validateVisibilityConfig(array $config, CoreExtraFieldDefinition $definition): ?string
    {
        $logic = $config['logic'] ?? 'AND';
        if (!in_array(strtoupper($logic), ['AND', 'OR'])) {
            return "Ungültige Hauptlogik: '{$logic}'. Erlaubt: AND, OR.";
        }

        $groups = $config['groups'] ?? [];
        if (empty($groups)) {
            return 'visibility_config.groups darf nicht leer sein wenn enabled=true.';
        }

        // Lade existierende Feld-Namen im Kontext (ohne das Feld selbst)
        $existingFields = CoreExtraFieldDefinition::query()
            ->forTeam($definition->team_id)
            ->forContext($definition->context_type, $definition->context_id)
            ->where('id', '!=', $definition->id)
            ->pluck('name')
            ->all();

        $validOperators = array_keys(ExtraFieldConditionEvaluator::OPERATORS);

        foreach ($groups as $gi => $group) {
            $groupLogic = $group['logic'] ?? 'AND';
            if (!in_array(strtoupper($groupLogic), ['AND', 'OR'])) {
                return "Ungültige Gruppenlogik in Gruppe {$gi}: '{$groupLogic}'.";
            }

            $conditions = $group['conditions'] ?? [];
            if (empty($conditions)) {
                return "Gruppe {$gi} hat keine Bedingungen.";
            }

            foreach ($conditions as $ci => $condition) {
                $field = $condition['field'] ?? null;
                if (empty($field)) {
                    return "Bedingung {$ci} in Gruppe {$gi}: field ist erforderlich.";
                }

                if ($field === $definition->name) {
                    return "Bedingung {$ci} in Gruppe {$gi}: Ein Feld kann nicht von sich selbst abhängen.";
                }

                if (!in_array($field, $existingFields)) {
                    return "Bedingung {$ci} in Gruppe {$gi}: Unbekanntes Feld '{$field}'. Verfügbar: " . implode(', ', $existingFields);
                }

                $operator = $condition['operator'] ?? 'equals';
                if (!in_array($operator, $validOperators)) {
                    return "Bedingung {$ci} in Gruppe {$gi}: Ungültiger Operator '{$operator}'. Erlaubt: " . implode(', ', $validOperators);
                }

                $requiresValue = ExtraFieldConditionEvaluator::OPERATORS[$operator]['requiresValue'] ?? true;
                if ($requiresValue && !array_key_exists('value', $condition)) {
                    return "Bedingung {$ci} in Gruppe {$gi}: Operator '{$operator}' benötigt einen value.";
                }
            }
        }

        return null;
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
