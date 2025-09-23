<?php

return [
    // Betriebsmodus: navigation_only | read_only | all
    'mode' => env('AGENT_MODE', 'read_only'),

    // Tool-Filter: include/exclude per Wildcard (module.tool oder prefix.*)
    'include' => [
        'planner.*',
        'core.*',
    ],
    'exclude' => [
        '*.destroy',
        '*.delete',
        '*.purge',
    ],

    // Impact-Policy: was ist ohne Confirm erlaubt
    'auto_allowed_impacts' => ['low'],

    // Keyword-Gewichte → bevorzugte Tool-Präfixe oder Verben
    'weights' => [
        // keyword => [boost_prefixes, weight]
        'öffne' => [ ['open','show'], 2.0 ],
        'open'  => [ ['open','show'], 2.0 ],
        'zeige' => [ ['list','get','query','show'], 1.5 ],
        'liste' => [ ['list','query'], 1.5 ],
        'suche' => [ ['query','list'], 1.5 ],
        'erstelle' => [ ['create'], 2.0 ],
        'create'   => [ ['create'], 2.0 ],
    ],

    // Mindestscore, sonst Tool ausfiltern (0.0–1.0)
    'min_score' => 0.0,

    // Capability-Scopes
    // Rolle/Team -> erlaubte Scopes
    'scopes' => [
        // 'admin' => ['*'],
        // 'user'  => ['read:*'],
    ],
];

?>

