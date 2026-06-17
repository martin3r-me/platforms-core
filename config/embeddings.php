<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | Provider-Name, der verwendet wird, wenn ein Aufruf von EmbeddingService
    | keinen Provider explizit angibt. Muss zu einem registrierten Provider
    | passen ('openai', 'gemini', ...).
    |
    */
    'default_provider' => env('EMBEDDING_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Embedding Settings
    |--------------------------------------------------------------------------
    |
    | text-embedding-3-large = 3072 Dimensionen.
    | API-Key wird zusätzlich aus config('services.openai.api_key') gelesen.
    |
    */
    'openai' => [
        'enabled' => env('EMBEDDING_OPENAI_ENABLED', true),
        'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-large'),
        'dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', 3072),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini Embedding Settings
    |--------------------------------------------------------------------------
    |
    | gemini-embedding-001 = 768 Dimensionen, liefert L2-normalisierte Vektoren.
    | Drop-in-kompatibel zur Cooking-Jarvis-Vorgängerlösung.
    |
    */
    'gemini' => [
        'enabled' => env('EMBEDDING_GEMINI_ENABLED', false),
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'dimensions' => (int) env('GEMINI_EMBEDDING_DIMENSIONS', 768),
    ],
];
