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
 * Registriert global via `Schema::blueprintResolver(...)` im CoreServiceProvider::boot(). Explizit
 * gesetzte Namen bleiben unberührt (createIndexName läuft nur, wenn KEIN Name angegeben wurde).
 */
class SafeBlueprint extends Blueprint
{
    protected function createIndexName($type, array $columns)
    {
        $name = parent::createIndexName($type, $columns);

        if (strlen($name) <= 64) {
            return $name;
        }

        // 55 + '_' + 8 (md5-Prefix des VOLLEN Namens) = 64 Zeichen. Deterministisch aus dem
        // vollen Namen → ein späteres dropForeign/dropIndex(['spalte']) regeneriert exakt
        // denselben gekürzten Namen (Konsistenz bei Drops).
        return substr($name, 0, 55).'_'.substr(md5($name), 0, 8);
    }
}
