<?php

namespace Platform\Core\SemanticLayer\Schema;

use Platform\Core\SemanticLayer\Exceptions\InvalidLayerSchemaException;

/**
 * Hartes JSON-Schema gemäß Canvas — keine freien Felder.
 *
 * Spezifikation:
 *   perspektive:  string, 1..500 chars
 *   ton:          array<string>, 1..12 items, je 1..120 chars
 *   heuristiken:  array<string>, 1..12 items, je 1..200 chars
 *   negativ_raum: array<string>, 1..12 items, je 1..120 chars
 *
 * Token-Budget: 150..200 (Soft-Warning bei <80 oder >250).
 */
class LayerSchemaValidator
{
    public const REQUIRED_FIELDS = ['perspektive', 'ton', 'heuristiken', 'negativ_raum'];

    public const MAX_PERSPEKTIVE_LEN = 500;
    public const MAX_TON_ITEM_LEN = 120;
    public const MAX_HEURISTIK_ITEM_LEN = 200;
    public const MAX_NEGATIV_ITEM_LEN = 120;
    public const MAX_ITEMS = 12;

    public const TOKEN_BUDGET_MIN = 80;
    public const TOKEN_BUDGET_MAX = 250;

    /**
     * Validiert ein Layer-Payload. Wirft InvalidLayerSchemaException bei Hard-Fehlern.
     *
     * @param array<string, mixed> $payload
     */
    public function validate(array $payload): void
    {
        $errors = [];

        // Keine freien Felder
        $extraKeys = array_diff(array_keys($payload), self::REQUIRED_FIELDS);
        if (!empty($extraKeys)) {
            $errors[] = 'Unerlaubte Felder: ' . implode(', ', $extraKeys);
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                $errors[] = "Feld '{$field}' fehlt";
            }
        }

        if (!empty($errors)) {
            throw new InvalidLayerSchemaException($errors);
        }

        // perspektive
        $p = $payload['perspektive'];
        if (!is_string($p)) {
            $errors[] = "'perspektive' muss ein String sein";
        } else {
            $len = mb_strlen(trim($p));
            if ($len < 1) {
                $errors[] = "'perspektive' darf nicht leer sein";
            } elseif ($len > self::MAX_PERSPEKTIVE_LEN) {
                $errors[] = "'perspektive' zu lang (max " . self::MAX_PERSPEKTIVE_LEN . " Zeichen, ist {$len})";
            }
        }

        // Array-Kanäle
        $this->validateStringArray($payload['ton'], 'ton', self::MAX_TON_ITEM_LEN, $errors);
        $this->validateStringArray($payload['heuristiken'], 'heuristiken', self::MAX_HEURISTIK_ITEM_LEN, $errors);
        $this->validateStringArray($payload['negativ_raum'], 'negativ_raum', self::MAX_NEGATIV_ITEM_LEN, $errors);

        if (!empty($errors)) {
            throw new InvalidLayerSchemaException($errors);
        }
    }

    /**
     * Berechnet Token-Approximation (mb_strlen / 4).
     * Nicht exakt, ausreichend für Budget-Check.
     */
    public function estimateTokens(string $rendered): int
    {
        return (int) ceil(mb_strlen($rendered) / 4);
    }

    /**
     * Soft-Check: gibt eine Warnung zurück, wenn Budget verletzt ist, sonst null.
     */
    public function checkTokenBudget(int $tokenCount): ?string
    {
        if ($tokenCount < self::TOKEN_BUDGET_MIN) {
            return "Token-Count {$tokenCount} unter Soft-Minimum " . self::TOKEN_BUDGET_MIN;
        }
        if ($tokenCount > self::TOKEN_BUDGET_MAX) {
            return "Token-Count {$tokenCount} über Soft-Maximum " . self::TOKEN_BUDGET_MAX;
        }
        return null;
    }

    /**
     * @param array<int, string> $errors (by-reference)
     */
    private function validateStringArray(mixed $value, string $field, int $maxItemLen, array &$errors): void
    {
        if (!is_array($value)) {
            $errors[] = "'{$field}' muss ein Array sein";
            return;
        }
        $count = count($value);
        if ($count < 1) {
            $errors[] = "'{$field}' darf nicht leer sein";
            return;
        }
        if ($count > self::MAX_ITEMS) {
            $errors[] = "'{$field}' zu viele Einträge (max " . self::MAX_ITEMS . ", sind {$count})";
        }
        if (!array_is_list($value)) {
            $errors[] = "'{$field}' muss ein List-Array sein (keine assoziativen Keys)";
            return;
        }
        foreach ($value as $i => $item) {
            if (!is_string($item)) {
                $errors[] = "'{$field}[{$i}]' muss ein String sein";
                continue;
            }
            $len = mb_strlen(trim($item));
            if ($len < 1) {
                $errors[] = "'{$field}[{$i}]' darf nicht leer sein";
            } elseif ($len > $maxItemLen) {
                $errors[] = "'{$field}[{$i}]' zu lang (max {$maxItemLen} Zeichen, ist {$len})";
            }
        }
    }
}
