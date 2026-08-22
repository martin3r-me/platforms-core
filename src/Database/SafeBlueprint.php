<?php

namespace Platform\Core\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * Blueprint, der auto-generierte Index-/FK-/Unique-Namen über dem MySQL/MariaDB-Identifier-
 * Limit (64 Zeichen) deterministisch kürzt.
 *
 * Grund: Laravels Namens-Konvention `{table}_{column}_foreign` (bzw. _index/_unique) sprengt bei
 * langen Tabellen-/Spaltennamen das 64er-Limit → die Migration scheitert erst zur LAUFZEIT beim
 * `migrate`/Deploy (php -l sieht das nie). Das ist der häufigste Grund, dass Worker-Migrations
 * fehlschlagen. Mit diesem Blueprint laufen naive Migrations (`->foreignId(...)->constrained()`)
 * ohne expliziten Kurznamen durch — für Menschen UND autonome Worker.
 *
 * Registriert global im CoreServiceProvider::boot() (db.schema als Singleton mit vor-gesetztem
 * Resolver — nur so bleibt der Resolver über alle Schema::-Zugriffe stabil; ein reines
 * `Schema::blueprintResolver()` verpufft, weil der db.schema-Bind pro Zugriff einen frischen
 * Builder liefert). Gekürzt werden auto-generierte Index-/FK-/Unique-Namen (createIndexName)
 * UND explizit vergebene Foreign-Key-Namen (foreign()).
 */
class SafeBlueprint extends Blueprint
{
    // Auto-generierte Namen (kein Name angegeben).
    protected function createIndexName($type, array $columns)
    {
        return $this->shortenIdentifier(parent::createIndexName($type, $columns));
    }

    // EXPLIZIT gesetzte Foreign-Key-Namen.
    public function foreign($columns, $name = null)
    {
        if (is_string($name) && $name !== '') {
            $name = $this->shortenIdentifier($name);
        }
        return parent::foreign($columns, $name);
    }

    // Deterministische Kürzung >64 → 55 + '_' + 8 (md5-Prefix). Regeneriert für dasselbe Eingabe-
    // muster exakt denselben Namen (Konsistenz bei Drops).
    protected function shortenIdentifier(string $name): string
    {
        if (strlen($name) <= 64) {
            return $name;
        }
        return substr($name, 0, 55).'_'.substr(md5($name), 0, 8);
    }
}
