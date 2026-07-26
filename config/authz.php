<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authz-Modus
    |--------------------------------------------------------------------------
    |
    | 'off'     — Kernel inaktiv, kein Gate-Hook.
    | 'shadow'  — Graph-Resolver läuft PARALLEL zu den bestehenden Policies,
    |             ändert KEINE Entscheidung, protokolliert nur Abweichungen
    |             in authz_shadow_log. Das ist der Beweis-/Punch-List-Modus.
    | 'enforce' — (später) Graph entscheidet via Gate::before.
    |
    */
    'mode' => env('AUTHZ_MODE', 'shadow'),

    /*
    |--------------------------------------------------------------------------
    | Modul-Zugang durchsetzen (Phase 3, Modul-Achse)
    |--------------------------------------------------------------------------
    |
    | false — Module::hasAccess() nutzt weiter die alte modulables-Matrix.
    | true  — Hybrid: migrierte User (mit Modul-Grant) werden vom Graphen
    |         entschieden; nicht migrierte User fallen auf modulables zurück
    |         (kein Lockout). Getrennt vom content-mode oben schaltbar.
    |
    */
    'enforce_modules' => (bool) env('AUTHZ_ENFORCE_MODULES', false),

    /*
    |--------------------------------------------------------------------------
    | Content-Enforcement pro Modul (Phase: Content-Achse)
    |--------------------------------------------------------------------------
    |
    | false — Modul nutzt weiter seine alte Sichtbarkeit (Mitgliedschaft etc.).
    | true  — Sichtbarkeit/Policies laufen über den Graphen: Ersteller (owns())
    |         ODER strukturell erreichbar (may()/authzVisibleTo). Alte Strukturen
    |         (z.B. planner projectUsers) greifen dann NICHT mehr.
    |
    | Pro Modul einzeln schaltbar, damit man Modul für Modul verifiziert cutovern
    | kann (Shadow → Enforce → alte Struktur entfernen).
    |
    */
    'enforce_planner' => (bool) env('AUTHZ_ENFORCE_PLANNER', false),
];
