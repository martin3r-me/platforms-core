<?php

namespace Platform\Core\Tests\Unit\Terminal;

use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalAppRegistry;
use Platform\Core\Terminal\TerminalContext;
use Platform\Core\Tests\TestCase;

class TerminalAppRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TerminalAppRegistry::flush();
    }

    public function test_registered_app_is_retrievable(): void
    {
        $app = $this->makeApp('alpha');
        TerminalAppRegistry::register($app);

        $this->assertTrue(TerminalAppRegistry::has('alpha'));
        $this->assertSame($app, TerminalAppRegistry::get('alpha'));
        $this->assertNull(TerminalAppRegistry::get('missing'));
    }

    public function test_all_sorts_by_group_then_order(): void
    {
        TerminalAppRegistry::register($this->makeApp('b-late', group: 'native', order: 20));
        TerminalAppRegistry::register($this->makeApp('a-early', group: 'native', order: 10));
        TerminalAppRegistry::register($this->makeApp('c-module', group: 'module', order: 1));

        $this->assertSame(
            ['a-early', 'b-late', 'c-module'],
            array_keys(TerminalAppRegistry::all()),
        );
    }

    public function test_available_for_filters_by_context(): void
    {
        TerminalAppRegistry::register($this->makeApp('always'));
        TerminalAppRegistry::register($this->makeApp('needs-context', available: fn (TerminalContext $c) => $c->hasContext()));

        $without = TerminalAppRegistry::availableFor(new TerminalContext());
        $this->assertSame(['always'], array_keys($without));

        $with = TerminalAppRegistry::availableFor(new TerminalContext(contextType: 'organization', contextId: 5));
        $this->assertSame(['always', 'needs-context'], array_keys($with));
    }

    private function makeApp(string $key, string $group = 'native', int $order = 100, ?\Closure $available = null): TerminalApp
    {
        return new class($key, $group, $order, $available) implements TerminalApp {
            public function __construct(
                private string $key,
                private string $group,
                private int $order,
                private ?\Closure $available,
            ) {}

            public function key(): string { return $this->key; }
            public function label(): string { return ucfirst($this->key); }
            public function icon(): string { return 'beaker'; }
            public function group(): string { return $this->group; }
            public function order(): int { return $this->order; }
            public function livewireComponent(): string { return 'core.terminal.'.$this->key; }
            public function isAvailable(TerminalContext $context): bool
            {
                return $this->available ? ($this->available)($context) : true;
            }
        };
    }
}
