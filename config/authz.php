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
];
