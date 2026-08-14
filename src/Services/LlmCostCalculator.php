<?php

namespace Platform\Core\Services;

/**
 * Rechnet LLM-Token-Verbrauch in Geld um.
 *
 * Preise und Cache-Multiplikatoren kommen aus config/ai.php. Die Kosten werden
 * primär in USD berechnet (so rechnet Anthropic ab); ist ein USD→EUR-Kurs
 * konfiguriert (ai.usd_eur), gibt es zusätzlich einen EUR-Wert.
 */
class LlmCostCalculator
{
    /**
     * Kosten eines LLM-Verbrauchs in USD.
     *
     * @param  string  $model  Model-ID (z. B. "claude-sonnet-5")
     * @param  array   $usage  {input_tokens, output_tokens, cache_creation_input_tokens?, cache_read_input_tokens?}
     */
    public function costUsd(string $model, array $usage): float
    {
        $prices = $this->pricesFor($model);
        $inPerToken = $prices['input'] / 1_000_000;
        $outPerToken = $prices['output'] / 1_000_000;

        $mult = config('ai.cache_multipliers', ['write' => 1.25, 'read' => 0.10]);

        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $cacheWrite = (int) ($usage['cache_creation_input_tokens'] ?? 0);
        $cacheRead = (int) ($usage['cache_read_input_tokens'] ?? 0);

        return $input * $inPerToken
            + $output * $outPerToken
            + $cacheWrite * $inPerToken * (float) ($mult['write'] ?? 1.25)
            + $cacheRead * $inPerToken * (float) ($mult['read'] ?? 0.10);
    }

    /**
     * Kosten in EUR, sofern ein Kurs konfiguriert ist — sonst null.
     */
    public function costEur(string $model, array $usage): ?float
    {
        $rate = config('ai.usd_eur');

        if (! $rate) {
            return null;
        }

        return $this->costUsd($model, $usage) * (float) $rate;
    }

    /**
     * Kompakte Kostenübersicht für Logs/UI.
     *
     * @return array{usd: float, eur: float|null, model: string, tokens: int}
     */
    public function breakdown(string $model, array $usage): array
    {
        $tokens = (int) ($usage['input_tokens'] ?? 0)
            + (int) ($usage['output_tokens'] ?? 0)
            + (int) ($usage['cache_creation_input_tokens'] ?? 0)
            + (int) ($usage['cache_read_input_tokens'] ?? 0);

        return [
            'usd' => round($this->costUsd($model, $usage), 4),
            'eur' => ($eur = $this->costEur($model, $usage)) !== null ? round($eur, 4) : null,
            'model' => $model,
            'tokens' => $tokens,
        ];
    }

    /**
     * Preistabelle für ein Modell (Substring-Match, sonst 'default').
     *
     * @return array{input: float, output: float}
     */
    protected function pricesFor(string $model): array
    {
        $table = config('ai.pricing', []);

        foreach ($table as $key => $prices) {
            if ($key !== 'default' && $model !== '' && str_contains($model, $key)) {
                return $prices;
            }
        }

        return $table['default'] ?? ['input' => 3.00, 'output' => 15.00];
    }
}
