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
    | Der planner läuft fest über den Graphen (Ersteller ODER strukturell
    | erreichbar) — kein Flag mehr, alte Mitgliedschaft (projectUsers) entfernt.
    | Weitere Graph-Module folgen nach demselben Muster; Modul-weite Module
    | (Zugriff = Modul → alles sehen) brauchen hier nichts.
    |
    */
];
