<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tool System Configuration
    |--------------------------------------------------------------------------
    |
    | Zentrale Konfiguration für das Tool-System (MCP-Pattern)
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('TOOLS_CACHE_ENABLED', true),
        'default_ttl' => env('TOOLS_CACHE_TTL', 3600), // 1 Stunde
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => env('TOOLS_RATE_LIMITING_ENABLED', true),
        'default_limit' => env('TOOLS_RATE_LIMIT_DEFAULT', 100), // pro Minute
        'per_user_limit' => env('TOOLS_RATE_LIMIT_PER_USER', 50), // pro Minute
        'per_team_limit' => env('TOOLS_RATE_LIMIT_PER_TEAM', 200), // pro Minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'enabled' => env('TOOLS_RETRY_ENABLED', true),
        'max_attempts' => env('TOOLS_RETRY_MAX_ATTEMPTS', 3),
        'backoff_strategy' => env('TOOLS_RETRY_BACKOFF', 'exponential'), // exponential, linear, fixed
        'initial_delay_ms' => env('TOOLS_RETRY_INITIAL_DELAY', 100), // 100ms
        'max_delay_ms' => env('TOOLS_RETRY_MAX_DELAY', 5000), // 5 Sekunden
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Configuration
    |--------------------------------------------------------------------------
    */
    'timeout' => [
        'enabled' => env('TOOLS_TIMEOUT_ENABLED', true),
        'default_timeout_seconds' => env('TOOLS_TIMEOUT_DEFAULT', 30), // 30 Sekunden
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI HTTP Client
    |--------------------------------------------------------------------------
    |
    | Netzwerk-/HTTP-Tuning für OpenAI Requests (Responses API).
    | Wichtig für Stabilität bei größeren Inputs/Tool-Ergebnissen.
    |
    */
    'openai' => [
        // Default Model für Responses API (muss im OpenAI Account verfügbar sein)
        // Beispiel: gpt-5, gpt-4.1, gpt-4o-mini, ...
        'model' => env('TOOLS_OPENAI_MODEL', 'gpt-5'),
        // Gesamt-Timeout pro Request (in Sekunden)
        'timeout_seconds' => env('TOOLS_OPENAI_TIMEOUT_SECONDS', 60),
        // Connect-Timeout (in Sekunden)
        'connect_timeout_seconds' => env('TOOLS_OPENAI_CONNECT_TIMEOUT_SECONDS', 10),
        // Retry-Versuche bei ConnectionException (z.B. cURL 28/52)
        'retry_attempts' => env('TOOLS_OPENAI_RETRY_ATTEMPTS', 3),
        // Retry-Delay (ms) – wird pro Attempt zufällig zwischen min/max gewählt
        'retry_sleep_min_ms' => env('TOOLS_OPENAI_RETRY_SLEEP_MIN_MS', 400),
        'retry_sleep_max_ms' => env('TOOLS_OPENAI_RETRY_SLEEP_MAX_MS', 1200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'enabled' => env('TOOLS_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('TOOLS_CB_FAILURE_THRESHOLD', 5), // Anzahl Fehler
        'timeout_seconds' => env('TOOLS_CB_TIMEOUT', 60), // Zeit bis Half-Open
        'success_threshold' => env('TOOLS_CB_SUCCESS_THRESHOLD', 2), // Erfolge für Closed
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Hier können spezifische Einstellungen pro Tool definiert werden.
    | Format: 'tool.name' => [ 'setting' => 'value' ]
    |
    */
    'tools' => [
        // Beispiel-Konfigurationen (können später erweitert werden)
        // 'core.teams.list' => [
        //     'cache' => true,
        //     'cache_ttl' => 1800, // 30 Minuten
        //     'timeout' => 10,
        //     'rate_limit' => 200,
        //     'retry' => false, // Read-only, kein Retry nötig
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'execution_tracking' => env('TOOLS_EXECUTION_TRACKING', true),
        'metrics' => env('TOOLS_METRICS', true),
        'analytics' => env('TOOLS_ANALYTICS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP (Micro-Capability Pattern)
    |--------------------------------------------------------------------------
    |
    | On-Demand Tool Injection – startet mit Discovery-Tools und lädt bei Bedarf nach.
    |
    */
    'mcp' => [
        /*
         * Wenn die Simulation in einen Loop läuft (z.B. wiederholt core.teams.GET),
         * und das erwartete Tool (z.B. planner.projects.GET) nicht verfügbar ist,
         * versucht der Playground im laufenden Run automatisch die passenden Tools
         * per internem tools.GET nachzuladen (on-demand, nicht "alle Tools").
         */
        'auto_injection_on_loop' => env('TOOLS_MCP_AUTO_INJECTION_ON_LOOP', true),
        // Persistiertes Objective/Current-Task pro Playground-Session (loose)
        'objective_enabled' => env('TOOLS_MCP_OBJECTIVE_ENABLED', true),

        /*
         * Session-Timeout (Sliding Window):
         * Bei jedem Tool-Call wird die Session-TTL automatisch verlängert,
         * damit bei langen Tool-Sequenzen der Kontext (Team, geladene Tools)
         * nicht still abläuft.
         *
         * session_ttl_seconds: Gesamte Session-Dauer (wird bei Aktivität verlängert)
         * session_warning_seconds: Warnung wenn Session bald abläuft
         */
        'session_ttl_seconds' => env('TOOLS_MCP_SESSION_TTL', 28800), // 8 Stunden
        'session_warning_seconds' => env('TOOLS_MCP_SESSION_WARNING', 1800), // 30 Minuten vor Ablauf

        /*
         * Loose Completion-Gate:
         * Wenn die LLM eine finale Text-Antwort gibt, aber Verifikation noch Issues sieht,
         * geben wir der LLM noch ein paar Recovery-Runden (ohne tool_choice=required).
         * Das ist NICHT hart – es verhindert nur ein zu frühes "Fertig", wenn noch etwas offen ist.
         */
        'completion_gate_enabled' => env('TOOLS_MCP_COMPLETION_GATE_ENABLED', true),
        'completion_gate_max_attempts' => env('TOOLS_MCP_COMPLETION_GATE_MAX_ATTEMPTS', 2),

        /*
         * Trivial-Short-Circuit (Greeting/Ack) - DEPRECATED:
         * Diese Funktion wurde entfernt, da sie nicht MCP-konform war.
         * Das LLM entscheidet selbst, ob es Tools benötigt oder direkt antworten kann.
         * MCP Best Practice: Alle Tools anbieten, LLM entscheidet selbst.
         * 
         * Diese Config-Option wird ignoriert, aber aus Kompatibilitätsgründen beibehalten.
         */
        'trivial_short_circuit_enabled' => env('TOOLS_MCP_TRIVIAL_SHORT_CIRCUIT_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intention Verification
    |--------------------------------------------------------------------------
    |
    | Prüft, ob die Tool-Results die ursprüngliche User-Intention erfüllen.
    | Wenn Probleme gefunden werden, kann die LLM automatisch korrigieren.
    |
    */
    'intention_verification' => [
        'enabled' => env('TOOLS_INTENTION_VERIFICATION_ENABLED', true),
        'max_correction_iterations' => env('TOOLS_INTENTION_VERIFICATION_MAX_ITERATIONS', 2), // Max 2 zusätzliche Iterationen für Korrektur
        'use_llm_extraction' => env('TOOLS_INTENTION_VERIFICATION_USE_LLM', true), // LLM für komplexe Intentionen nutzen
    ],
];

