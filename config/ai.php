<?php

return [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'inference_model' => env('ANTHROPIC_INFERENCE_MODEL', 'claude-sonnet-5'),
    ],

    /*
     * LLM-Preise in USD pro 1 Mio Tokens. Quelle: Anthropic Pricing.
     * Bei Preisänderungen (z. B. Ende des Sonnet-5-Intro-Tarifs am 2026-08-31)
     * hier anpassen. Match erfolgt per Substring auf die Model-ID; 'default'
     * greift, wenn kein Eintrag passt.
     */
    'pricing' => [
        'claude-sonnet-5'   => ['input' => 2.00, 'output' => 10.00], // Intro bis 2026-08-31; danach 3.00 / 15.00
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-4-8'   => ['input' => 5.00, 'output' => 25.00],
        'claude-opus-4-7'   => ['input' => 5.00, 'output' => 25.00],
        'claude-haiku-4-5'  => ['input' => 1.00, 'output' => 5.00],
        'default'           => ['input' => 3.00, 'output' => 15.00],
    ],

    /*
     * Cache-Multiplikatoren relativ zum Input-Preis (Anthropic-Standard):
     * Cache-Write kostet 1.25×, Cache-Read 0.1× des Input-Preises.
     */
    'cache_multipliers' => [
        'write' => 1.25, // cache_creation_input_tokens
        'read'  => 0.10, // cache_read_input_tokens
    ],

    /*
     * Optionaler USD→EUR-Kurs für die Anzeige. null = nur USD ausweisen.
     */
    'usd_eur' => env('AI_USD_EUR'),
];
