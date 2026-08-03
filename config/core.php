<?php

use Platform\Core\Enums\ContextDateTimeKind;

return [

    /*
    |--------------------------------------------------------------------------
    | Context-Date-Times: Dual-Write-Whitelist
    |--------------------------------------------------------------------------
    |
    | Zentrale Whitelist der Models, deren Datums-Spalten in die generische
    | core_context_date_times-Tabelle gespiegelt werden (Dual-Write). Der
    | Schlüssel ist die Model-Klasse (FQCN – muss NICHT installiert sein, der
    | Eintrag wird zur Laufzeit per class_exists() geguarded). Der Wert ist ein
    | Map "Spalte => Konfiguration":
    |
    |   'due_date' => ['kind' => ContextDateTimeKind::Due, 'label' => 'Fällig'],
    |
    | Erlaubte Kurzform, wenn kein Label nötig ist:
    |
    |   'due_date' => ContextDateTimeKind::Due,
    |
    | Wer schreibt?
    |   - Models, die das Trait Platform\Core\Traits\HasContextDateTimes nutzen,
    |     registrieren den Observer selbst (bootHasContextDateTimes()).
    |   - Für Models OHNE das Trait (z.B. weil das Modul-Package noch nicht
    |     angepasst wurde) registriert der CoreServiceProvider den Observer
    |     automatisch anhand dieser Whitelist. So funktioniert der Dual-Write
    |     auch ohne Änderung am Fremd-Package.
    |
    | Das `source`-Feld der erzeugten Row lautet stets
    | "migrated_from:<table>.<column>" und dient als idempotenter Identity-Key
    | für Re-Runs des Backfills.
    |
    */

    'context_date_times' => [

        'sync' => [

            \Platform\Planner\Models\PlannerTask::class => [
                'due_date' => ['kind' => ContextDateTimeKind::Due, 'label' => 'Fällig'],
            ],

            // Hinweis PlannerProject: Die früheren Spalten planner_projects.starts_at
            // / ends_at existieren NICHT mehr – die Projekt-Laufzeit wurde nach
            // organization_time_periods migriert (Trait Organization\...\HasPlannedPeriod,
            // siehe Migration 2026_06_01_..._migrate_dates_to_organization_time_periods).
            // Ein spalten-basierter Dual-Write ist hier deshalb nicht anwendbar; die
            // Anbindung braucht einen relationsbasierten Adapter im planner-Package und
            // ist bewusst NICHT als (leerlaufende) Spalten-Map eingetragen.
            // \Platform\Planner\Models\PlannerProject::class => [
            //     'starts_at' => ContextDateTimeKind::Start,
            //     'ends_at'   => ContextDateTimeKind::End,
            // ],

        ],

    ],

];
