<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM provider for the Verbalizer
    |--------------------------------------------------------------------------
    | Key matches what providers report via getName(). Falls back to the first
    | available provider when null.
    */
    'default_provider' => env('VERBALIZATION_PROVIDER', 'anthropic'),
];
