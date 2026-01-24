<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Server Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguration für den Model Context Protocol (MCP) Server.
    | Diese Konfiguration kann in der Hauptanwendung überschrieben werden.
    |
    */

    'servers' => [
        // Standard-Server-Konfiguration
        // Kann in der Hauptanwendung überschrieben werden
        'default' => [
            'name' => env('MCP_SERVER_NAME', 'Platform MCP Server'),
            'version' => env('MCP_SERVER_VERSION', '1.0.0'),
            'class' => env('MCP_SERVER_CLASS', null), // Muss in Hauptanwendung gesetzt werden
            'description' => env('MCP_SERVER_DESCRIPTION', 'Platform MCP Server'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguration für MCP Routes.
    |
    */

    'routes' => [
        'prefix' => env('MCP_ROUTES_PREFIX', 'mcp'),
        'middleware' => env('MCP_ROUTES_MIDDLEWARE', ['api']),
        'auth_middleware' => env('MCP_ROUTES_AUTH_MIDDLEWARE', 'auth:sanctum'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP OpenAPI Configuration
    |--------------------------------------------------------------------------
    |
    | Konfiguration für OpenAPI Schema-Generierung.
    |
    */

    'openapi' => [
        'version' => '3.1.0',
        'title' => env('MCP_OPENAPI_TITLE', 'Platform MCP Server'),
        'description' => env('MCP_OPENAPI_DESCRIPTION', 'Platform MCP Server API'),
    ],
];
