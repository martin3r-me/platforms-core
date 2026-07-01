<?php

namespace Platform\Core\Support;

/**
 * Berechnet die Jahres-Auswahl eines `date`-Extra-Fields aus den pro-Feld
 * konfigurierbaren Bereichen. Reine Logik (kein Framework) → unit-testbar und
 * von Renderer (Blade) UND Validierung (WithExtraFields) gemeinsam genutzt,
 * damit die Grenzen nicht auseinanderlaufen.
 *
 * Optionen am Feld:
 *   - year_range:        Jahre in die Vergangenheit (Default siehe
 *                        CoreExtraFieldDefinition::DATE_YEAR_RANGE_DEFAULT).
 *   - year_range_future: Jahre in die Zukunft (Default siehe
 *                        CoreExtraFieldDefinition::DATE_YEAR_RANGE_FUTURE_DEFAULT).
 *                        Für "Gültig bis"-Felder relevant; auf 0 setzen für
 *                        reine Vergangenheits-Felder (z.B. Geburtsdatum).
 */
class DateFieldRange
{
    /**
     * Auswahlliste, absteigend (größtes Jahr oben) — konsistent zum bisherigen
     * Verhalten für Geburtsdatum-artige Felder.
     *
     * @return int[]
     */
    public static function years(int $currentYear, int $past, int $future): array
    {
        $past = max(0, $past);
        $future = max(0, $future);

        $years = [];
        for ($y = $currentYear + $future; $y >= $currentYear - $past; $y--) {
            $years[] = $y;
        }
        return $years;
    }

    /** Oberes gültiges Jahr (für Validierungs-Grenze between:...). */
    public static function maxYear(int $currentYear, int $future): int
    {
        return $currentYear + max(0, $future);
    }
}
