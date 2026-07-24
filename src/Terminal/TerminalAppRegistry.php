<?php

namespace Platform\Core\Terminal;

use Platform\Core\Terminal\Contracts\TerminalApp;

/**
 * Registry of Terminal Apps. Modules register their apps here (typically from a
 * service provider's boot), the shell reads it to build the tab rail and to
 * render the active panel via a dynamic nested Livewire component.
 *
 * Static + array-backed, mirroring Platform\Notifications\NotificationTypeRegistry.
 */
class TerminalAppRegistry
{
    /** @var array<string, TerminalApp> */
    protected static array $apps = [];

    public static function register(TerminalApp $app): void
    {
        static::$apps[$app->key()] = $app;
    }

    public static function get(string $key): ?TerminalApp
    {
        return static::$apps[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return isset(static::$apps[$key]);
    }

    /**
     * All registered apps, sorted by group then order.
     *
     * @return array<string, TerminalApp>
     */
    public static function all(): array
    {
        $apps = static::$apps;
        uasort($apps, function (TerminalApp $a, TerminalApp $b) {
            return [$a->group(), $a->order()] <=> [$b->group(), $b->order()];
        });

        return $apps;
    }

    /**
     * Apps available for the given context, in rail order.
     *
     * @return array<string, TerminalApp>
     */
    public static function availableFor(TerminalContext $context): array
    {
        return array_filter(
            static::all(),
            fn (TerminalApp $app) => $app->isAvailable($context),
        );
    }

    /** Reset registry (for testing). */
    public static function flush(): void
    {
        static::$apps = [];
    }
}
