<?php

return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'inference_model' => env('ANTHROPIC_INFERENCE_MODEL', 'claude-sonnet-4-20250514'),
    ],
];
