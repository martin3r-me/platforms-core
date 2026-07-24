<?php

namespace Platform\Core\Terminal\Apps;

use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalContext;

/**
 * Throwaway app used to verify the TerminalAppRegistry seam end-to-end.
 * Registered only when TERMINAL_DEMO_APP=true. Remove once a real app
 * (ExtraFields) proves the seam.
 */
class DemoTerminalApp implements TerminalApp
{
    public function key(): string { return 'demo'; }

    public function label(): string { return 'Demo'; }

    public function icon(): string { return 'beaker'; }

    public function group(): string { return 'native'; }

    public function order(): int { return 999; }

    public function livewireComponent(): string { return 'core.terminal.demo'; }

    public function isAvailable(TerminalContext $context): bool { return true; }
}
