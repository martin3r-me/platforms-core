<?php

namespace Platform\Core\Terminal\Apps;

use Closure;
use Platform\Core\Terminal\Contracts\HasBadge;
use Platform\Core\Terminal\TerminalContext;

/** ContextApp mit Zähler-Badge im Kontext-Panel (Dateien, Tags, Zeit). */
class CountedContextApp extends ContextApp implements HasBadge
{
    public function __construct(
        string $key,
        string $label,
        string $icon,
        int $order,
        string $component,
        protected Closure $counter,
    ) {
        parent::__construct($key, $label, $icon, $order, $component);
    }

    public function badge(TerminalContext $context): ?string
    {
        return ($this->counter)($context);
    }
}
