<?php

namespace Platform\Core\Services;

use Platform\Core\Models\CoreLookup;

/**
 * Evaluates visibility conditions for extra fields.
 *
 * Supports complex nested conditions with logical operators (AND/OR)
 * grouped into condition groups.
 */
class ExtraFieldConditionEvaluator
{
    /**
     * All available operators with metadata.
     */
    public const OPERATORS = [
        'equals' => [
            'label' => 'Gleich',
            'types' => ['text', 'number', 'textarea', 'boolean', 'select', 'lookup'],
            'requiresValue' => true,
        ],
        'not_equals' => [
            'label' => 'Ungleich',
            'types' => ['text', 'number', 'textarea', 'boolean', 'select', 'lookup'],
            'requiresValue' => true,
        ],
        'greater_than' => [
            'label' => '>',
            'types' => ['number'],
            'requiresValue' => true,
        ],
        'greater_or_equal' => [
            'label' => '>=',
            'types' => ['number'],
            'requiresValue' => true,
        ],
        'less_than' => [
            'label' => '<',
            'types' => ['number'],
            'requiresValue' => true,
        ],
        'less_or_equal' => [
            'label' => '<=',
            'types' => ['number'],
            'requiresValue' => true,
        ],
        'is_null' => [
            'label' => 'Ist leer',
            'types' => ['text', 'number', 'textarea', 'boolean', 'select', 'lookup', 'file'],
            'requiresValue' => false,
        ],
        'is_not_null' => [
            'label' => 'Ist nicht leer',
            'types' => ['text', 'number', 'textarea', 'boolean', 'select', 'lookup', 'file'],
            'requiresValue' => false,
        ],
        'in' => [
            'label' => 'Einer von',
            'types' => ['select', 'lookup'],
            'requiresValue' => true,
        ],
        'not_in' => [
            'label' => 'Keiner von',
            'types' => ['select', 'lookup'],
            'requiresValue' => true,
        ],
        'is_in' => [
            'label' => 'Ist in Liste',
            'types' => ['text', 'number', 'select', 'lookup'],
            'requiresValue' => true,
        ],
        'is_not_in' => [
            'label' => 'Ist nicht in Liste',
            'types' => ['text', 'number', 'select', 'lookup'],
            'requiresValue' => true,
        ],
        'contains' => [
            'label' => 'Enthält',
            'types' => ['text', 'textarea'],
            'requiresValue' => true,
        ],
        'starts_with' => [
            'label' => 'Beginnt mit',
            'types' => ['text', 'textarea'],
            'requiresValue' => true,
        ],
        'ends_with' => [
            'label' => 'Endet mit',
            'types' => ['text', 'textarea'],
            'requiresValue' => true,
        ],
        'is_true' => [
            'label' => 'Ist Ja',
            'types' => ['boolean'],
            'requiresValue' => false,
        ],
        'is_false' => [
            'label' => 'Ist Nein',
            'types' => ['boolean'],
            'requiresValue' => false,
        ],
    ];

    /**
     * Evaluate a visibility configuration against field values.
     *
     * @param array $visibilityConfig The visibility configuration from visibility_config column
     * @param array $fieldValues Current field values indexed by field name
     * @return bool Whether the field should be visible
     */
    public function evaluate(array $visibilityConfig, array $fieldValues): bool
    {
        // If visibility is not enabled or no groups, always visible
        if (!($visibilityConfig['enabled'] ?? false)) {
            return true;
        }

        $groups = $visibilityConfig['groups'] ?? [];
        if (empty($groups)) {
            return true;
        }

        $mainLogic = strtoupper($visibilityConfig['logic'] ?? 'AND');
        $groupResults = [];

        foreach ($groups as $group) {
            $groupResult = $this->evaluateGroup($group, $fieldValues);
            $groupResults[] = $groupResult;
        }

        // Apply main logic between groups
        if ($mainLogic === 'OR') {
            return in_array(true, $groupResults, true);
        }

        // AND logic: all groups must be true
        return !in_array(false, $groupResults, true);
    }

    /**
     * Evaluate a single condition group.
     */
    protected function evaluateGroup(array $group, array $fieldValues): bool
    {
        $conditions = $group['conditions'] ?? [];
        if (empty($conditions)) {
            return true;
        }

        $groupLogic = strtoupper($group['logic'] ?? 'AND');
        $conditionResults = [];

        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition, $fieldValues);
            $conditionResults[] = $result;
        }

        if ($groupLogic === 'OR') {
            return in_array(true, $conditionResults, true);
        }

        // AND logic: all conditions must be true
        return !in_array(false, $conditionResults, true);
    }

    /**
     * Evaluate a single condition.
     */
    protected function evaluateCondition(array $condition, array $fieldValues): bool
    {
        $fieldName = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? null;

        if (!$fieldName) {
            return true;
        }

        $actualValue = $fieldValues[$fieldName] ?? null;

        // For is_in/is_not_in operators, resolve the comparison list
        if (in_array($operator, ['is_in', 'is_not_in'])) {
            $comparisonList = $this->resolveComparisonList($condition);
            return $this->compareValues($actualValue, $operator, $comparisonList);
        }

        return $this->compareValues($actualValue, $operator, $expectedValue);
    }

    /**
     * Compare values using the specified operator.
     */
    protected function compareValues(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $this->isEqual($actual, $expected),
            'not_equals' => !$this->isEqual($actual, $expected),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'greater_or_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'less_or_equal' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'is_null' => $this->isEmpty($actual),
            'is_not_null' => !$this->isEmpty($actual),
            'in' => $this->isIn($actual, $expected),
            'not_in' => !$this->isIn($actual, $expected),
            'is_in' => $this->isIn($actual, $expected),
            'is_not_in' => !$this->isIn($actual, $expected),
            'contains' => is_string($actual) && is_string($expected) && str_contains(strtolower($actual), strtolower($expected)),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with(strtolower($actual), strtolower($expected)),
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with(strtolower($actual), strtolower($expected)),
            'is_true' => $this->isTrue($actual),
            'is_false' => $this->isFalse($actual),
            default => true,
        };
    }

    /**
     * Check if two values are equal (with type coercion).
     */
    protected function isEqual(mixed $actual, mixed $expected): bool
    {
        // Handle null cases
        if ($actual === null && $expected === null) {
            return true;
        }

        // Handle boolean comparison
        if (is_bool($expected)) {
            return $this->isTrue($actual) === $expected;
        }

        // Handle numeric comparison
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        // String comparison (case-insensitive)
        if (is_string($actual) && is_string($expected)) {
            return strtolower($actual) === strtolower($expected);
        }

        return $actual == $expected;
    }

    /**
     * Check if a value is empty/null.
     */
    protected function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a value is in an array of expected values.
     */
    protected function isIn(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected)) {
            $expected = [$expected];
        }

        // Handle array actual values (multiple select)
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (in_array($item, $expected, false)) {
                    return true;
                }
            }
            return false;
        }

        return in_array($actual, $expected, false);
    }

    /**
     * Check if a value is truthy.
     */
    protected function isTrue(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1' || strtolower((string) $value) === 'true' || strtolower((string) $value) === 'ja') {
            return true;
        }

        return false;
    }

    /**
     * Check if a value is falsy.
     */
    protected function isFalse(mixed $value): bool
    {
        if ($value === false || $value === 0 || $value === '0' || strtolower((string) $value) === 'false' || strtolower((string) $value) === 'nein') {
            return true;
        }

        // Also consider null/empty as false for boolean fields
        if ($value === null || $value === '') {
            return true;
        }

        return false;
    }

    /**
     * Resolve the comparison list for is_in/is_not_in operators.
     *
     * Supports two sources:
     * - 'lookup': Values from a CoreLookup reference
     * - 'manual': Manually entered list of values
     */
    protected function resolveComparisonList(array $condition): array
    {
        $source = $condition['list_source'] ?? 'manual';
        $values = $condition['value'] ?? [];

        if ($source === 'lookup') {
            $lookupId = $condition['list_lookup_id'] ?? null;
            if ($lookupId) {
                $lookup = CoreLookup::with('activeValues')->find($lookupId);
                if ($lookup) {
                    return $lookup->activeValues->pluck('value')->all();
                }
            }
            return [];
        }

        // Manual values
        if (is_array($values)) {
            return $values;
        }

        return $values ? [$values] : [];
    }

    /**
     * Convert a visibility configuration to a human-readable string.
     *
     * @param array $visibilityConfig The visibility configuration
     * @param array $fieldLabels Field labels indexed by field name
     * @return string Human-readable condition string
     */
    public function toHumanReadable(array $visibilityConfig, array $fieldLabels = []): string
    {
        if (!($visibilityConfig['enabled'] ?? false)) {
            return 'Immer sichtbar';
        }

        $groups = $visibilityConfig['groups'] ?? [];
        if (empty($groups)) {
            return 'Immer sichtbar';
        }

        $mainLogic = strtoupper($visibilityConfig['logic'] ?? 'AND');
        $mainConnector = $mainLogic === 'OR' ? ' ODER ' : ' UND ';

        $groupStrings = [];
        foreach ($groups as $group) {
            $groupString = $this->groupToHumanReadable($group, $fieldLabels);
            if ($groupString) {
                $groupStrings[] = $groupString;
            }
        }

        if (empty($groupStrings)) {
            return 'Immer sichtbar';
        }

        if (count($groupStrings) === 1) {
            return $groupStrings[0];
        }

        return '(' . implode(')' . $mainConnector . '(', $groupStrings) . ')';
    }

    /**
     * Convert a group to human-readable string.
     */
    protected function groupToHumanReadable(array $group, array $fieldLabels): string
    {
        $conditions = $group['conditions'] ?? [];
        if (empty($conditions)) {
            return '';
        }

        $groupLogic = strtoupper($group['logic'] ?? 'AND');
        $connector = $groupLogic === 'OR' ? ' ODER ' : ' UND ';

        $conditionStrings = [];
        foreach ($conditions as $condition) {
            $conditionString = $this->conditionToHumanReadable($condition, $fieldLabels);
            if ($conditionString) {
                $conditionStrings[] = $conditionString;
            }
        }

        if (empty($conditionStrings)) {
            return '';
        }

        return implode($connector, $conditionStrings);
    }

    /**
     * Convert a condition to human-readable string.
     */
    protected function conditionToHumanReadable(array $condition, array $fieldLabels): string
    {
        $fieldName = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        if (!$fieldName) {
            return '';
        }

        $fieldLabel = $fieldLabels[$fieldName] ?? $fieldName;
        $operatorLabel = self::OPERATORS[$operator]['label'] ?? $operator;
        $requiresValue = self::OPERATORS[$operator]['requiresValue'] ?? true;

        if (!$requiresValue) {
            return "\"{$fieldLabel}\" {$operatorLabel}";
        }

        // Special handling for is_in/is_not_in operators
        if (in_array($operator, ['is_in', 'is_not_in'])) {
            $source = $condition['list_source'] ?? 'manual';
            if ($source === 'lookup') {
                $lookupId = $condition['list_lookup_id'] ?? null;
                $lookupName = 'Lookup';
                if ($lookupId) {
                    $lookup = CoreLookup::find($lookupId);
                    if ($lookup) {
                        $lookupName = $lookup->label;
                    }
                }
                return "\"{$fieldLabel}\" {$operatorLabel} \"{$lookupName}\"";
            }
            // Manual list
            if (is_array($value)) {
                $valueStr = '[' . implode(', ', $value) . ']';
            } else {
                $valueStr = (string) ($value ?? '');
            }
            return "\"{$fieldLabel}\" {$operatorLabel} \"{$valueStr}\"";
        }

        if (is_array($value)) {
            $valueStr = '[' . implode(', ', $value) . ']';
        } elseif (is_bool($value)) {
            $valueStr = $value ? 'Ja' : 'Nein';
        } else {
            $valueStr = (string) $value;
        }

        return "\"{$fieldLabel}\" {$operatorLabel} \"{$valueStr}\"";
    }

    /**
     * Get operators available for a specific field type.
     *
     * @param string $type The field type (text, number, select, etc.)
     * @return array Array of operators with their metadata
     */
    public static function getOperatorsForType(string $type): array
    {
        $result = [];

        foreach (self::OPERATORS as $key => $operator) {
            if (in_array($type, $operator['types'], true)) {
                $result[$key] = $operator;
            }
        }

        return $result;
    }

    /**
     * Get all operators.
     */
    public static function getAllOperators(): array
    {
        return self::OPERATORS;
    }

    /**
     * Create an empty visibility configuration.
     */
    public static function createEmptyConfig(): array
    {
        return [
            'enabled' => false,
            'logic' => 'AND',
            'groups' => [],
        ];
    }

    /**
     * Create a new empty group.
     */
    public static function createEmptyGroup(): array
    {
        return [
            'id' => 'group_' . uniqid(),
            'logic' => 'AND',
            'conditions' => [],
        ];
    }

    /**
     * Create a new empty condition.
     */
    public static function createEmptyCondition(): array
    {
        return [
            'id' => 'cond_' . uniqid(),
            'field' => '',
            'operator' => 'equals',
            'value' => null,
        ];
    }
}
