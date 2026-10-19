<?php

namespace Platform\Core\Inbox\Support;

use Platform\Core\Inbox\Contracts\LinkRule;

/**
 * Sammelstelle für LinkRules. Quell-Module registrieren hier beim Boot ihre Regeln;
 * der LinkRuleRunner führt alle passenden aus. Als Singleton in Core gebunden.
 */
class LinkRuleRegistry
{
    /** @var array<int, LinkRule> */
    protected array $rules = [];

    public function register(LinkRule $rule): void
    {
        $this->rules[] = $rule;
    }

    /** @return array<int, LinkRule> */
    public function all(): array
    {
        return $this->rules;
    }
}
