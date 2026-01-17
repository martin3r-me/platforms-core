<?php

namespace Platform\Core\Services;

use Platform\Core\Contracts\ToolContract;
use Illuminate\Support\Facades\Log;

/**
 * Service für vollständige JSON-Schema-Validierung
 * 
 * Validiert Tool-Argumente gegen vollständiges JSON-Schema
 */
class ToolValidationService
{
    /**
     * Validiert Tool-Argumente gegen JSON-Schema
     * 
     * @return array{valid: bool, data: array, errors: array}
     */
    public function validate(ToolContract $tool, array $arguments): array
    {
        $schema = $tool->getSchema();
        $errors = [];
        $validatedData = [];

        // Prüfe required fields
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (!isset($arguments[$field])) {
                $errors[] = "Feld '{$field}' ist erforderlich";
            }
        }

        // Validiere alle Properties
        $properties = $schema['properties'] ?? [];
        foreach ($arguments as $key => $value) {
            if (!isset($properties[$key])) {
                // Unbekanntes Feld: Prüfe ob additionalProperties erlaubt ist
                $additionalProperties = $schema['additionalProperties'] ?? true;
                if (!$additionalProperties) {
                    $errors[] = "Unbekanntes Feld '{$key}'";
                } else {
                    $validatedData[$key] = $value;
                }
                continue;
            }

            $property = $properties[$key];
            $validationResult = $this->validateProperty($key, $value, $property);
            
            if ($validationResult['valid']) {
                $validatedData[$key] = $validationResult['value'];
            } else {
                $errors = array_merge($errors, $validationResult['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'data' => $validatedData,
            'errors' => $errors,
        ];
    }

    /**
     * Validiert eine einzelne Property
     * 
     * @return array{valid: bool, value: mixed, errors: array}
     */
    private function validateProperty(string $key, mixed $value, array $property): array
    {
        $errors = [];
        $validatedValue = $value;

        // Prüfe Type
        $type = $property['type'] ?? null;
        if ($type && !$this->validateType($value, $type)) {
            $errors[] = "Feld '{$key}' hat falschen Typ (erwartet: {$type}, erhalten: " . gettype($value) . ")";
            return ['valid' => false, 'value' => $value, 'errors' => $errors];
        }

        // Prüfe Enum (falls vorhanden)
        if (isset($property['enum'])) {
            // Für String-Enum-Werte: trimmen vor Validierung
            $valueToCheck = ($type === 'string' && is_string($value)) ? trim($value) : $value;
            if (!in_array($valueToCheck, $property['enum'], true)) {
                $errors[] = "Feld '{$key}' muss einer der folgenden Werte sein: " . implode(', ', $property['enum']);
            } else {
                // Wenn getrimmt wurde, verwende den getrimmten Wert
                if ($type === 'string' && is_string($value) && $valueToCheck !== $value) {
                    $validatedValue = $valueToCheck;
                }
            }
        }

        // Prüfe String-Constraints
        if ($type === 'string') {
            // MinLength
            if (isset($property['minLength']) && mb_strlen($value) < $property['minLength']) {
                $errors[] = "Feld '{$key}' muss mindestens {$property['minLength']} Zeichen lang sein";
            }

            // MaxLength
            if (isset($property['maxLength']) && mb_strlen($value) > $property['maxLength']) {
                $errors[] = "Feld '{$key}' darf maximal {$property['maxLength']} Zeichen lang sein";
            }

            // Pattern (Regex)
            if (isset($property['pattern'])) {
                if (!preg_match('/' . $property['pattern'] . '/', $value)) {
                    $errors[] = "Feld '{$key}' entspricht nicht dem erwarteten Format";
                }
            }
        }

        // Prüfe Number-Constraints
        if ($type === 'number' || $type === 'integer') {
            // Minimum
            if (isset($property['minimum']) && $value < $property['minimum']) {
                $errors[] = "Feld '{$key}' muss mindestens {$property['minimum']} sein";
            }

            // Maximum
            if (isset($property['maximum']) && $value > $property['maximum']) {
                $errors[] = "Feld '{$key}' darf maximal {$property['maximum']} sein";
            }
        }

        // Prüfe Array-Constraints
        if ($type === 'array') {
            // MinItems
            if (isset($property['minItems']) && count($value) < $property['minItems']) {
                $errors[] = "Feld '{$key}' muss mindestens {$property['minItems']} Elemente enthalten";
            }

            // MaxItems
            if (isset($property['maxItems']) && count($value) > $property['maxItems']) {
                $errors[] = "Feld '{$key}' darf maximal {$property['maxItems']} Elemente enthalten";
            }

            // Items-Validierung (wenn vorhanden)
            if (isset($property['items']) && is_array($value)) {
                foreach ($value as $index => $item) {
                    $itemValidation = $this->validateProperty("{$key}[{$index}]", $item, $property['items']);
                    if (!$itemValidation['valid']) {
                        $errors = array_merge($errors, $itemValidation['errors']);
                    }
                }
            }
        }

        // Prüfe Object-Constraints
        if ($type === 'object' && isset($property['properties']) && is_array($value)) {
            foreach ($property['properties'] as $subKey => $subProperty) {
                if (isset($value[$subKey])) {
                    $subValidation = $this->validateProperty("{$key}.{$subKey}", $value[$subKey], $subProperty);
                    if (!$subValidation['valid']) {
                        $errors = array_merge($errors, $subValidation['errors']);
                    }
                }
            }
        }

        return [
            'valid' => empty($errors),
            'value' => $validatedValue,
            'errors' => $errors,
        ];
    }

    /**
     * Validiert einen Wert gegen einen Typ
     */
    private function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            'null' => $value === null,
            default => true // Unbekannte Typen werden akzeptiert (für Erweiterbarkeit)
        };
    }
}

