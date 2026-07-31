<?php

return [
    'routing_mode' => env('PLATFORM_ROUTING_MODE', 'subdomain'),

    'error_endpoint' => env('DEV_ERROR_ENDPOINT'),

    'documents' => [
        'chromium_path' => env('BROWSERSHOT_CHROMIUM_PATH'),
        'node_path' => env('BROWSERSHOT_NODE_PATH'),
        'npm_path' => env('BROWSERSHOT_NPM_PATH'),
        'paper' => [
            'format' => env('DOCUMENTS_PAPER_FORMAT', 'A4'),
            'margin_top' => env('DOCUMENTS_MARGIN_TOP', 20),
            'margin_right' => env('DOCUMENTS_MARGIN_RIGHT', 15),
            'margin_bottom' => env('DOCUMENTS_MARGIN_BOTTOM', 20),
            'margin_left' => env('DOCUMENTS_MARGIN_LEFT', 15),
            'print_background' => true,
        ],
    ],

    /*
     * Launchpad — App-Launcher (nx-launchpad).
     * anchors: Modul-Keys, die als neutrale Anker-Reihe VOR den farbigen
     * Kategorien stehen (strukturelle Einstiegspunkte, keine Kategorie).
     * Reihenfolge = Anzeige-Reihenfolge. Bewusst kurz halten (2–4).
     */
    'launchpad' => [
        'anchors' => ['home', 'organization'],
    ],
];
