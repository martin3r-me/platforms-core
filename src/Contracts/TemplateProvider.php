<?php

namespace Platform\Core\Contracts;

/**
 * TemplateProvider — PORT (Ports & Adapters).
 *
 * Liefert die Tätigkeits-Vorlagen („Tätigkeitsschlüssel"): je Vorlage Name + optional KldB-Code
 * + geforderte Vorsorgen. WICHTIG: Anlässe werden per STABILEM Schlüssel/Titel referenziert,
 * NICHT per Team-ID — der Consumer löst beim Anwenden auf den arbmedvv-Anlass SEINES Teams auf.
 * So passt eine team-übergreifende/plattform-weite Vorlage auf verschiedene Teams.
 *
 * templates(): [ ['key'=>'schweisser','name'=>'Schweißer','kldb_code'=>'24222','branch'=>'metall'], … ]
 * template(key): […, 'requirements'=>[ ['occasion_key'=>'laerm','occasion_title'=>'Lärm','care_type'=>'mandatory'], … ]]
 */
interface TemplateProvider
{
    /** @return array<int,array<string,mixed>> */
    public function templates(int $teamId, ?string $branch = null): array;

    /** @return array<string,mixed>|null */
    public function template(int $teamId, string $key): ?array;
}
