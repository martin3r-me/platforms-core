<?php

namespace Platform\Core\Tools\Concerns;

/**
 * Normalisiert Lookup/FK-ID Felder, damit "0"/0/"" nicht als Foreign-Key geschrieben wird.
 *
 * Hintergrund:
 * - UI sendet bei "keine Auswahl" oft null/leer
 * - LLMs raten manchmal "0" oder "1" -> führt zu FK-Constraints oder falschen Lookups
 *
 * Regel:
 * - 0, "0", "" => null
 * - numerische Strings => int
 * - alle anderen Werte unverändert
 *
 * Wichtig: Nur für *nullable* Lookup/FK-Felder verwenden, NICHT für Pflicht-IDs wie contact_id/company_id/team_id etc.
 */
trait NormalizesLookupIds
{
    protected function normalizeNullableLookupId(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === 0 || $value === '0' || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            // "123" -> 123
            return (int) $value;
        }

        return $value;
    }

    /**
     * @param array $arguments
     * @param array<int, string> $fields List of keys to normalize (e.g. ['country_id','status_id'])
     * @return array
     */
    protected function normalizeLookupIds(array $arguments, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $arguments)) {
                $arguments[$field] = $this->normalizeNullableLookupId($arguments[$field]);
            }
        }

        return $arguments;
    }
}


