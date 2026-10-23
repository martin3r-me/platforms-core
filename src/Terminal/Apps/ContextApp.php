<?php

namespace Platform\Core\Terminal\Apps;

use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalContext;

/**
 * Kontextbezogene Core-App (Aktivität, Dateien, Tags, Zeit, OKR, Zusatzfelder)
 * für das Kontext-Panel. Verfügbar nur, wenn die Seite die App per page-context
 * anbietet — das Terminal liefert keine apps mit und rendert diese Tabs weiter
 * selbst, dort tauchen sie also nicht doppelt auf.
 */
class ContextApp implements TerminalApp
{
    public function __construct(
        protected string $key,
        protected string $label,
        protected string $icon,
        protected int $order,
        protected string $component,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function group(): string
    {
        return 'context';
    }

    public function order(): int
    {
        return $this->order;
    }

    public function livewireComponent(): string
    {
        return $this->component;
    }

    public function isAvailable(TerminalContext $context): bool
    {
        return $context->hasContext() && $context->offers($this->key);
    }
}
