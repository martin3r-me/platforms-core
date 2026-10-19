<?php

namespace Platform\Core\Inbox\Support;

/**
 * Führt alle registrierten LinkRules für ein Item aus. Jede Regel ist soft —
 * ein Fehler in einer Regel bricht die anderen nicht ab. Aufrufer: die
 * RunLinkRulesAutomation (auf ItemDelivered) und der Audio-Ingest (inline),
 * solange Aufnahmen noch nicht über Core-Delivery laufen. Regeln sind idempotent.
 */
class LinkRuleRunner
{
    public function __construct(protected LinkRuleRegistry $registry) {}

    public function run(int $itemId): void
    {
        foreach ($this->registry->all() as $rule) {
            try {
                $rule->apply($itemId);
            } catch (\Throwable $e) {
                // Regel soft — nie fatal für die übrigen.
            }
        }
    }
}
