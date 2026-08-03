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
    | Store-Backend
    |--------------------------------------------------------------------------
    |
    | Welche EmbeddingStoreContract-Implementation gebunden wird:
    |   'mysql'  → MySqlJsonEmbeddingStore (Default, JSON + Cosine in PHP,
    |              gut bis wenige tausend Vektoren pro Tenant)
    |   'qdrant' → QdrantEmbeddingStore (ANN via Qdrant, für große Korpora 100k+)
    |
    | Umschaltung ist reine Config — Service, Job und Provider bleiben gleich.
    |
    */
    'store' => env('EMBEDDING_STORE', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Store-Routing pro Entity-Type (statischer Fallback)
    |--------------------------------------------------------------------------
    |
    | Überschreibt den globalen 'store'-Default je Entity-Type. So kann der große
    | Rezept-/Pairing-Korpus in Qdrant liegen, während kleinere team-scoped Daten
    | in MySQL bleiben — ohne dass Aufrufer den Store explizit angeben müssen.
    |
    | HINWEIS: Der bevorzugte, lose gekoppelte Weg ist NICHT diese zentrale Map,
    | sondern die Registrierung durch das jeweilige Modul in dessen ServiceProvider:
    |
    |   app(EmbeddingStoreRegistry::class)->route('recipe', 'qdrant');
    |
    | So bleibt core entity-agnostisch (core kennt keine Modul-Entities). Diese
    | Config dient nur als statischer Fallback für Fälle ohne Modul-Registrierung.
    |
    | Priorität: expliziter $store-Parameter  >  Modul-route()  >  dieses Routing  >  'store'.
    |
    | Beispiel:
    |   'routing' => [
    |       'recipe'       => 'qdrant',
    |       'food_pairing' => 'qdrant',
    |   ],
    |
    */
    'routing' => [
        // 'entity_type' => 'store_name',
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Qdrant Store Settings
    |--------------------------------------------------------------------------
    |
    | Nur relevant, wenn 'store' === 'qdrant'. URL zeigt bei Co-Location auf
    | localhost, bei eigenem Server (Hetzner Private Network) auf die private IP.
    | Qdrant NIE öffentlich exposen — an 127.0.0.1 / privates Netz binden und
    | API-Key setzen. 'quantization' = 'scalar' senkt den RAM-Bedarf deutlich.
    |
    */
    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://127.0.0.1:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'timeout' => (int) env('QDRANT_TIMEOUT', 30),
        'quantization' => env('QDRANT_QUANTIZATION'), // null | 'scalar'
        'collection_prefix' => env('QDRANT_COLLECTION_PREFIX', 'emb'),
    ],
];
